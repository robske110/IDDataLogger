#Config
`username` The E-Mail Address used for your VW ID account

`password` The password used for your VW ID account

`vin` The vin of the vehicle for which data should be logged.
Can be `null`, which causes the first vehicle to be used. (Recommended when you only have one vehicle in your account)

`base-updaterate` Updaterate for the car values. (Note: Data will only be written to db when data changed.)

`increased-updaterate` This updaterate will be used while the car is charging or hvac is active.

`carpic.flip` Whether to flip the carpic (default: true)

side, right
side, left
back, left
back, right
front, center
front, left
front, right

`carpic.viewDirection` 

`carpic.angle` 

`db.host` The host of the postgres db server

`db.dbname` The name of the database that this application can use

`db.user` The username for the database

`db.password` The password for the database

`timezone` Server timezone. This is used for correct timestamps in logs.

`logging.debug-enable` Enables debug output

`logging.file-enable` Enables debug output

`logging.log-dir` The directory in which to store log files. Can be `null` for default directory. (`program_directory/log`)