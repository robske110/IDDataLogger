#!/bin/bash

DIR="$(cd -P "$( dirname "${BASH_SOURCE[0]}" )" && pwd)"
cd "$DIR" || exit 1

exec "/usr/bin/php8.0" "./src/vwid/Server.php" "$@"
