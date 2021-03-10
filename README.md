# ID DataLogger

Welcome to the ID DataLogger project.
This program allows you to log data about your Volkswagen ID vehicle.
It includes an iOS widget (using Scriptable) and a webpage for seeing current status and history graphs.

<p align="center">
  <img src="docs/img/idView.png" width="500">
  <img src="docs/img/widgets.png" width="300">
</p>

## Setup

### Setup for beginners

A quick heads-up beforehand: Setting this up for someone who has never set up a webserver before can be challenging.
Don't worry though, the [beginners guide](docs/beginnerguide.md) guides you through every step you need to take.
Should you have any remaining questions or issues please see [getting help](https://github.com/robske110/IDDataLogger/wiki/Getting-help).

### Setup for advanced users

#### Prerequisites

- PHP 8 cli with pdo-pgsql (or pdo-mysql), curl, gd, pcntl and dom
- A webserver serving .php files (PHP 8 with pdo-pgsql (or pdo-mysql))
- (strongly recommended) HTTPS enabled server with certificate
- A PostgreSQL server (Any version from 9 and up should work, although testing has only been done on 11 and up)
    - alternatively MySQL / MariaDB is supported, but PostgreSQL is recommended.

#### Overview of the setup process

Looking at the automated install script for debian [install.sh](docs/install.sh) alongside the following instructions
might be helpful.

Clone this repository.
   
`git clone https://github.com/robske110/IDDataLogger.git --recursive`

Create a database (and a user) in your PostgreSQL (or other) server for this project and fill in the details into
`config/config.example.json` and `.env.example.` We'll need these files later.
You can do this using the config setup wizard by running the `config-wizard.sh` script, or manually.
Note: for a detailed description of the possible config values visit [config.md](docs/config.md).

After creating the config.json from config.example.json run `./start.sh`.
The necessary tables in the database will be automatically created.
After a successful connection to the db, the setup wizard will help you create an API key for the widget and a user for
the website. You can create additional API keys or add additional users at any time using `./start.sh --wizard`.

All files in the `public` directory of this repository must now be placed somewhere in the webroot.
It is recommended to place them in the second level (not directly in webroot).

Then copy the `.env` file (created from `.env.example`) outside the webroot with the db credentials set in it.

Note:
`env.php` looks for a `.env` file two folders up from its location.
(If you put the contents of the public folder in `/path/to/webroot/vwid/` it will look in `/path/to/.env`)
If you place the files deeper inside the webroot, please consider editing env.php and configuring the correct path in
the first line. It is strongly recommended keeping the .env file out of the webroot.

You can alternatively set the environment variables through your webserver. (Or anything else that populates php's `$_ENV`)

You now need to set up your system to automatically start `start.sh` on system start. Using systemd is recommended.

You can now visit idView.php or use the iOS widget after [setting it up](docs/ioswidget.md)!

#### Updating

To update the software at a later data execute `git pull && git submodule update` in the repository directory and
replace the files in the webroot with the new contents of the `public` folder. Make sure to restart the php process.
(The one started by `start.sh`)

## Contributing

Contributions are always welcome! You can help to improve the documentation, fix bugs in the code or add new features.

Improving the beginners guide and documentation are currently something I would love to have help with.
Feel free to open a PR!

### A big 'Thank you!' to the following contributors

- @drego83 - Invaluable help with general testing and MySQL support

## Disclaimer

This project is not endorsed by Volkswagen in any way, shape or form. This project is to be used entirely at your own risk.
All brands and trademarks belong to their respective owners.

Copyright (C) 2021 robske_110

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.