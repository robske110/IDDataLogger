if [ ! -e /usr/config/config.json ]; then
    echo "-- No container config found: start configuration --"
    /usr/src/IDDataLogger/config-wizard.sh --host db --user vwiddatalogger --dbname vwid --allow-insecure-http --quiet --password ${IDDATALOGGER_DBPWD}
    cp /usr/src/IDDataLogger/config/config.json /usr/config
    cp -f /usr/src/IDDataLogger/.env /usr/config
    echo "-- container configuration done: start app --"
    /usr/src/IDDataLogger/start.sh
else
    echo "-- container config found: start app --"
    cp -f /usr/config/config.json /usr/src/IDDataLogger/config
    cp -f /usr/config/.env /usr/src/IDDataLogger
    /usr/src/IDDataLogger/start.sh
fi