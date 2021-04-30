#!/bin/bash
#Update script for debian-based systems.

cd ~ || exit
cd IDDataLogger || exit
git pull
git submodule update
sudo rm -r /var/www/html/vwid/
sudo cp -r ./public/. /var/www/html/vwid
sudo systemctl restart iddatalogger.service
echo "Update complete!"