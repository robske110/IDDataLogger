#!/bin/bash

DIR="$(cd -P "$( dirname "${BASH_SOURCE[0]}" )" && pwd)"
cd "$DIR" || exit 1

echo "What's your domain name?"
read -r domain
echo "Enter an E-Mail where you want to receive important certificate alerts (this will not be often)"
read -r email
sudo apt -y install certbot python-certbot-apache
sudo certbot --apache --agree-tos -n -m $email -d $domain

cd ..

sudo rm /var/www/.env
./config-wizard.sh --secure --quiet
sudo cp ./.env /var/www/