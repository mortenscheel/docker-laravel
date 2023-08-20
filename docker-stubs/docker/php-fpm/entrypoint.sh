#!/usr/bin/env bash

set -e

if [ ! -z "$WWWUSER" ] && [[ "$(id -u www-data)" != "$WWWUSER" ]]; then
    usermod -u $WWWUSER www-data
fi
if [ $# -gt 0 ]; then
    exec gosu $UID "$@"
else
    exec /usr/bin/supervisord
fi
