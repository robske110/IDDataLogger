#!/bin/bash

DIR="$(cd -P "$( dirname "${BASH_SOURCE[0]}" )" && pwd)"
cd "$DIR" || exit 1

exec "php" "./src/vwid/Server.php configwizard" "$@"