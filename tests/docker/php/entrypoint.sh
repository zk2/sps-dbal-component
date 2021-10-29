#!/bin/sh
set -e

# shellcheck disable=SC2039
if [[ "${1#-}" != "$1" ]]; then
	set -- php-fpm "$@"
fi

exec "$@"