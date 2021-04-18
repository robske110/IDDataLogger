#!/bin/bash

/usr/src/IDDataLogger/config-wizard.sh --use-env --fill-defaults --quiet
/usr/src/IDDataLogger/start.sh --wizard --frontend-username ${IDDATALOGGER_IDVIEW_USERNAME} --frontend-password ${IDDATALOGGER_IDVIEW_PASSWORD} --frontend-apikey ${IDDATALOGGER_API_KEY}
