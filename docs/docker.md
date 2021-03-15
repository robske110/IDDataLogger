# Setup with docker

A docker-compose setup with associated Dockerfiles is provided for running this application using docker.

There are three docker containers:
- db - based on postgres, hosts the database and persists its data
- web - based on php-apache, hosts the frontend and persists nothing
- app - based on php, hosts the background fetcher and persists the data directory (currently used for caching carPic)

All configuration is done using environment variables.

### Installing

Clone this repository and change into the docker subdirectory.

`git clone https://github.com/robske110/IDDataLogger.git --recursive && cd ./IDDataLogger/docker`

Create a .env file in the docker directory (using .env.example of the docker directory as a basis).
Make sure to be in the docker subdirectory! The .env file in the root directory IS NOT USED in the docker installation!

You need to set at least
`IDDATALOGGER_USERNAME`,
`IDDATALOGGER_PASSWORD`,
`IDDATALOGGER_IDVIEW_USERNAME` and
`IDDATALOGGER_IDVIEW_PASSWORD`
for the application to work.
`IDDATALOGGER_USERNAME` and `IDDATALOGGER_PASSWORD` have to be set to the credentials of your
VW account. The IDVIEW variants are for the account created for the web frontend of this application.

Note: If you plan to use the iOS widget and want to create a custom authentication key for it, set `IDDATALOGGER_API_KEY`
to your desired key. Otherwise, an API key will be automatically generated, and you'll need to copy it from the log
output on first startup.
```
iddatalogger_app | [Y]: Successfully generated the API key ad65c068e1a7cf6bee6f65a6f04157545ba22d870a0a1fe019b20989e26c6749
iddatalogger_app | Please enter this API key in the apiKey setting at the top of the iOS widget!
```

Further environment variables available are the same as defined in [config.md](docs/config.md). The environment variable
names for the configuration options will be all UPPERCASE with hyphens and dots replaced by underscores.
(For example `logging.debug-enable` becomes `LOGGING_DEBUG_ENABLE`)

After creating the .env file the last command to execute is `docker compose up`.

You can now visit `localhost:IDDATALOGGER_WEB_PORT` or [set up](docs/ioswidget.md) the iOS widget using the API key
you copied from the first startup log or specified using `IDDATALOGGER_API_KEY`!

If you want to access the ID DataLogger from the internet, please place it behind a reverse-proxy providing SSL
certificates and HTTPS support.

### Updating

To update the software at a later data execute `git pull && git submodule update` in the repository directory and
rebuild the docker containers web and app. (`docker build web && docker build app`)