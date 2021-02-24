# Setup Guide for beginners
Hi! You want to use this project, but have little to no experience setting up servers?
Please free up a few hours for setting this project up.

Setting this project up is recommended on a raspberrypi for beginners. This guide assumes you are running on a raspberrypi.
If you do not have a raspberrypi handy, any Debian installation will also work with the easy install scripts and this guide.

Before we begin, let's go through what this project needs and does from a technical overview standpoint:

This project contains a long-running program which will fetch data from VW APIs regarding your car and store this data in a database.
It also provides a website where you can view this data. Furthermore, it provides an API itself to quickly fetch the current car Status for displaying in, e.g. the iOS widget.

So we have three components: The long-running programm, the database and a webserver to serve you the stats website or the data for the iOS widget.
All of this can run on a raspberrypi.

Let's get to work then.

## Prerequisites
You'll need
- a raspberrypi with an internet connection and raspbian installed (alternatively any machine with a debian installation works)
- a publicly routable IPv4 address if you want to use the widget and website from outside your home network (Some fibre plans for example do not include this)

We assume you have your raspberrypi freshly setup and have the command prompt in front of you.
There are plenty of guides on the internet on how to archive this.

You should see the following line: `pi@raspberrypi:~ $`

We strongly recommend changing your password on the raspberrypi to a reasonably strong one.

Now you'll need to decide how you want to setup this project.
There is a one-line command which attempts to install this project automagically, but if you prefer to do some things manually and learn some things in the process jump to this [section](#installing-manually).

## Installing using the install script

The install script works and is tested on raspbian and debian.
It assumes you have a fresh OS, especially without any existing postgres or webserver installations.

Enter (or copy) the following command to download and run the install script:

`wget https://raw.githubusercontent.com/robske110/IDDataLogger/master/docs/install.sh; bash install.sh; rm install.sh`

The install script will produce a lot of output. After a few minutes you will be prompted for your VW account login information.
After you enter the information you should see `Installation complete, ...`.
You can now enter `./start.sh`, which will start the ID DataLogger and help you create an API key for your widget and a user for the web interface.
For more information on setting up the iOS widget using the API key see [Setting up the iOS Widget](ioswidget.md).

After creating the API key and the user you should see `Done. Ready! Fetching car status...` and `Writing new data for timestamp`.
This means you have successfully setup the ID DataLogger!

You can now access the ID DataLogger by entering the IP of your raspberrypi into your browser or using the iOS widget.

To find the IP of your raspberrypi simply enter the command `hostname -I`

If you want to view the website and have the iOS widget update outside your home network, please refer to [making the ID DataLogger available from the internet](#making-the-id-datalogger-available-from-the-internet).

## Installing manually

**THIS SECTION IS NOT COMPLETED YET**
Please consider using the install script or come back later!

We are going to execute a series of commands to setup this project on the pi.

#### 1. Installing software dependencies

We need to install some software on the raspberry needed for this project.

We need to install php, the language in which this project is written.

```
wget -q -O - https://packages.sury.org/php/README.txt | bash -s -
sudo apt install php8.0
```
The first command will install a repository which contains the latest version of PHP.
The second command will install php 8 and setup the webserver apache2.
The last command we'll need to execute is
`sudo apt install postgresql`
This will install the PostgreSQL database. It will store our information about the car.

cd

`setup-config.sh`

Here you will have to enter your db credentials created in the previous step

## Making the ID DataLogger available from the internet

**THIS SECTION IS NOT COMPLETED YET**
