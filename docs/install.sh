#!/bin/bash
#Install script for clean debian-based systems.

cd ~ || exit
wget -q -O - https://packages.sury.org/php/README.txt | bash -s -
sudo apt -y install php8.0
sudo apt -y install postgresql
pg_pw=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 32 | head -n 1)
sudo su postgres -c "psql -c \"CREATE USER vwiddatalogger WITH PASSWORD '$pg_pw';\"; createdb vwid"
sudo apt -y install git
git clone https://github.com/robske110/IDDataLogger.git --recursive
cd IDDataLogger || exit
./config-wizard.sh --host localhost --user vwiddataloger --dbname vwid --password $pg_pw --allow-insecure-http --quiet
sudo mkdir /var/www/html/vwid/
sudo cp -r ./public/. /var/www/html/vwid
sudo cp ./.env /var/www/
echo "Installation complete! You can now enter cd IDDataLogger && ./start.sh to finish setting up."