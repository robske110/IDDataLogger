# Config

## config.json

`username` The E-Mail Address used for your VW ID account

`password` The password used for your VW ID account

`vin` The vin of the vehicle for which data should be logged.
Can be `null`, which causes the first vehicle to be used. (Recommended when you only have one vehicle in your account)

`base-updaterate` Updaterate for the car values. (Note: Data will only be written to db when data changed.)

`increased-updaterate` This updaterate will be used while the car is charging or hvac is active.

`db.host` The host of the postgres db server

`db.dbname` The name of the database that this application can use

`db.user` The username for the database

`db.password` The password for the database

`db.driver` The driver for the database. See https://www.php.net/manual/en/pdo.drivers.php for possible values.

`carpic.flip` Whether to flip the carpic (default: true)

The keys
`carpic.viewDirection` and
`carpic.angle`
can have the following values:

| viewDirection | angle |
| ----- | ------ |
| side  | right  |
| side  | left   |
| back  | left   |
| back  | right  |
| front | center |
| front | left   |
| front | right  |

Note: For changes to the carpic settings to apply, delete data/carPic.png

`timezone` Server timezone. This is used for correct timestamps in logs. (Overrides settings in php.ini)

`logging.debug-enable` Enables debug output

`logging.file-enable` Enables debug output

`logging.log-dir` The directory in which to store log files. Can be `null` for default directory (`program_directory/log`).

Note: If you run config-wizard.sh with the `--use-env` option (default in docker) the ENV variable names will be the
config names in uppercase with dots and hyphens are replaced by underscores and the prefix IDDATALOGGER.
(For example `logging.log-dir` becomes `IDDATALOGGER_LOGGING_LOG_DIR`.)

## .env file

`DB_HOST` The host of the postgres db server

`DB_NAME` The name of the database that this application can use

`DB_USER` The username for the database

`DB_PASSWORD` The password for the database

`DB_DRIVER` The driver for the database. See https://www.php.net/manual/en/pdo.drivers.php for possible values.

`FORCE_ALLOW_HTTP` Set this option to true to force the login system to allow http access.
It is strongly recommended to omit this option whenever possible, especially on installations accessible over the internet.