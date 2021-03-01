# Setup Guide for beginners
Hi! You want to use this project, but have little to no experience setting up servers?
Please free up about an hour for setting this project up.

Setting this project up is recommended on a Raspberry Pi for beginners. This guide assumes you are running raspbian.
If you do not have a Raspberry Pi handy, any Debian installation will also work with the easy install scripts and this guide.

Before we begin, let's go through what this project needs and does from a technical overview standpoint:

This project contains a long-running program which will fetch data from VW APIs regarding your car and store this data in a database.
It also provides a website where you can view this data. Furthermore, it provides an API itself to quickly fetch the current car Status for displaying in, e.g. the iOS widget.

So we have three components: The long-running programm, the database and a webserver to serve you the statistics website or the data for the iOS widget.
All of this can run on a Raspberry Pi.

Let's get to work then.

## Prerequisites
You'll need
- a Raspberry Pi with an internet connection and raspbian installed (alternatively any machine with a debian installation works)
- a publicly routable IPv4 address if you want to use the widget and website from outside your home network (Some fibre plans for example do not include this)

We assume you have your Raspberry Pi freshly setup and have the command prompt in front of you.
There are plenty of guides on the internet on how to archive this.

You should see the following line: `pi@Raspberry Pi:~ $`

We strongly recommend changing your password on the Raspberry Pi to a reasonably strong one.

Now you'll need to decide how you want to setup this project.
There is a one-line command which attempts to install this project automagically, but if you prefer to do some things manually and learn some things in the process jump to this [section](#installing-manually).

## Installing using the install script

The install script works and is tested on raspbian and debian.
It assumes you have a fresh OS, especially without any existing PostgreSQL or webserver installations.

Enter (or copy) the following command to download and run the install script:

`wget https://raw.githubusercontent.com/robske110/IDDataLogger/master/docs/install.sh; bash install.sh; rm install.sh`

The install script will produce a lot of output.
After a few minutes you will be prompted for your VW account login information.
It will ask for the username and password of your account. Note that the username is the E-Mail you use to log in.
After you entered the information you should see `Installation complete, ...`.

You'll now need to jump to [finishing setting up](#finishing-set-up).

## Installing manually

We are going to execute a series of commands to set up this project on the pi.
It is recommended to install this application in the home directory, although it is possible to use a different location.
To get to the home directory execute `cd ~`

#### 1. Installing software dependencies

We need to install some software on the raspberry needed for this project.

We need to install php, the language in which this project is written.

```
wget -q -O - https://packages.sury.org/php/README.txt | bash -s -
sudo apt install php8.0 php8.0-pgsql php8.0-curl php8.0-gd php8.0-dom
```
The first command will install a repository which contains the latest version of PHP.
The second command will install PHP 8, along with certain extensions we need.
The same command will also install and set up the webserver apache2.
The last command we'll need to execute is

`sudo apt install postgresql`

This will install the PostgreSQL database. It will store our information about the car.

#### 2. Setting up the database

We need to create a user and database in PostgreSQL for the ID DataLogger to be able to write and read from it.

The user will have a username and password. Since this password will never have to be entered by you, we can generate a
secure one automatically using the following command

To be able to create new users and databases in PostgreSQL we will log in as the user that can administer the PostgreSQL
database, which is called `postgres`:

`sudo su postgres`

We will first create a user in the database with the following two commands:
```
pg_pw=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 32 | head -n 1)
psql -c "CREATE USER iddatalogger WITH PASSWORD '$pg_pw';"
```
The first cpmmand will create a secure password for our database. The second one will connect to the database and use
the generated password to create the user iddatalogger. We must now echo this password using `echo "$pg_pw"`.
You'll need the password in the one of the next steps, please copy it to a temporary location.
Before we leave the postgres user we must create a database for the ID DataLogger: `createdb vwid`.
We are now finished with setting up the PostgreSQL database and can leave the postgres user using `exit`.

#### 3. Downloading and configuring the ID DataLogger

We can now install git and clone this repository. This will download the ID DataLogger software.
```
sudo apt install git
git clone https://github.com/robske110/IDDataLogger.git --recursive
```
Once we have done this we need to configure the ID DataLogger. We'll need to tell it the database details and our VW
account login information. We'll change into the directory of the program by executing `cd IDDataLogger` and then run
the config wizard with `./config-wizard.sh --allow-insecure-http`. The allow insecure http option allows us to test
and run the application in our home network.
The wizard will first ask for the username of the VW ID account. This is the E-Mail address you used to register at VW.
After that you'll need to enter the password for your VW account.
Now we need to configure the database parameters. It will ask us for the hostname of the database server. Since we run 
the database on the same machine the default value of `localhost` is correct and we can just press enter. Now it will
ask us for the name of the database. We used createdb vwid earlier, so we'll need to enter `vwid` here. The username of
the user we created earlier was `iddatalogger` so enter that for the next question. Now it will ask us for the password
of the database. Here we'll need to enter the password that was generated earlier. It will now ask us which database
driver it should use. The default of `pgsql` is fine, since it is an abbreviation for PostgreSQL.

For additional configuration options after completing the config wizard see [config.md](config.md)

#### 4. Copying files to the webserver

We successfully configured the application. Now we will have to do what the config wizard told us: Copy the contents of
the public folder to our webroot. This will copy the parts of the program that will be accessed from the internet (or
home network) to the webserver, which needs to "serve" them. We can do that using the following commands:
```
sudo rm /var/www/html/index.html # removes the default "It works!" page, this is optional!
sudo mkdir /var/www/html/vwid/   # creates a new directory for the ID DataLogger
sudo cp -r ./public/. /var/www/html/vwid # copies the files from the public folder
sudo ln -s "$PWD/.env" /var/www/ # links the .env file (created by the Config Wizard) to the appropiate location
```

#### 5. Creating a service for ID DataLogger

After these command we need to create a service for the ID DataLogger fetch program. This will ensure that it will be
started on every boot of the Raspberry PI and be able to fetch data continuously.

```
echo "[Unit]
Description=ID DataLogger php backend
After=network.target
Requires=postgresql.service

[Service]
ExecStart=/home/$(whoami)/IDDataLogger/start.sh
WorkingDirectory=/home/$(whoami)/IDDataLogger
StandardOutput=syslog
StandardError=syslog
SyslogIdentifier=iddatalogger
User=$(whoami)
RestartSec=5
Restart=always

[Install]
WantedBy=multi-user.target" | sudo tee /etc/systemd/system/iddatalogger.service > /dev/null
```
We need to enable the service using the command `sudo systemctl enable iddatalogger.service`.

You can now continue in the next section.

## Finishing set up

Make sure to be in the IDDataLogger directory. If you installed using the install script you will need to enter
`cd ./IDDataLogger`. If you come from the manual installation you should already be at the correct location.

You can now enter `./start.sh`, which will start the ID DataLogger.
It will now ask you if you want to generate an API key. If you want to use the iOS widget you will need to answer with `Y`.
For more information on setting up the iOS widget using the API key see [Setting up the iOS Widget](ioswidget.md).
After that it will ask you if you want to create an user. This user is used to log into the website. Make sure to choose
a strong and long password. It is recommended to store this in a password manager.
Note that you can create additional API keys or add additional users at any time using `./start.sh wizard`.

After creating the API key and the user you should see `Done. Ready! Fetching car status...` and `Writing new data for timestamp`.
This means you have successfully set up the ID DataLogger!
Please shutdown the ID DataLogger using the key combination CTRL+C and reboot the Raspberry Pi using `systemctl reboot`.

You can now access the ID DataLogger using the iOS widget or by entering `http://IP/vwid` into your browser where `IP` is the ip address or hostname of your raspberry.

To find the ip address of your Raspberry Pi simply enter the command `hostname -I`.

A command you might find useful from time to time is `sudo journalctl -u iddatalogger`. This will show the output of the fetch
program and can help you debug issues if you ever have any trouble.

If you want to view the website and have the iOS widget update outside your home network,
please refer to [making the ID DataLogger available from the internet](https://github.com/robske110/IDDataLogger/wiki/Making-the-ID-DataLogger-available-from-the-internet).