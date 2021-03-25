#!/bin/bash
#Install script for clean debian-based systems.

cd ~ || exit
wget -q -O - https://packages.sury.org/php/README.txt | bash -s -
sudo apt -y install php8.0 php8.0-pgsql php8.0-curl php8.0-gd php8.0-dom
sudo apt -y install postgresql
pg_pw=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 32 | head -n 1)
sudo su postgres -c "psql -c \"DO \\$\\$
BEGIN
IF EXISTS (SELECT FROM pg_roles WHERE rolname='iddatalogger') THEN
  ALTER ROLE iddatalogger WITH PASSWORD '$pg_pw';
ELSE
  CREATE USER iddatalogger WITH PASSWORD '$pg_pw';
END IF;
END \\$\\$;\"; createdb vwid"
sudo apt -y install git
git clone https://github.com/robske110/IDDataLogger.git --recursive
cd IDDataLogger || exit
./config-wizard.sh --host localhost --user iddatalogger --dbname vwid --password "$pg_pw" --driver pgsql --allow-insecure-http --quiet
sudo rm /var/www/html/index.html #remove default "It works!" page
sudo mkdir /var/www/html/vwid/
sudo cp -r ./public/. /var/www/html/vwid
sudo ln -s "$PWD/.env" /var/www/
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
sudo systemctl enable iddatalogger.service
echo "Installation complete! You can now enter cd IDDataLogger && ./start.sh to finish setting up."