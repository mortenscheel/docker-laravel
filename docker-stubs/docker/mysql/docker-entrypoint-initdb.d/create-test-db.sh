#!/usr/bin/env bash

if [[ -n "$MYSQL_PASSWORD" ]] && [[ -n "$MYSQL_USER" ]]; then
    mysql --user=root --password="$MYSQL_ROOT_PASSWORD" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS ${MYSQL_TEST_DATABASE};
    GRANT ALL PRIVILEGES ON \`${MYSQL_TEST_DATABASE}%\`.* TO '${MYSQL_USER}'@'%';
EOSQL
else
    mysql --user=root <<-EOSQL
    CREATE DATABASE IF NOT EXISTS ${MYSQL_TEST_DATABASE};
EOSQL
fi
