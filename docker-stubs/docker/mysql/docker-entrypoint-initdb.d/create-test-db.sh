#!/usr/bin/env bash

if [[ -n "$MYSQL_PASSWORD" ]] && [[ -n "$MYSQL_USER" ]]; then
    mysql --user=root --password="$MYSQL_ROOT_PASSWORD" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS ${MYSQL_DATABASE}_test;
    GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}_test%\`.* TO '${MYSQL_USER}'@'%';
EOSQL
else
    mysql --user=root <<-EOSQL
    CREATE DATABASE IF NOT EXISTS ${MYSQL_DATABASE}_test;
EOSQL
fi
