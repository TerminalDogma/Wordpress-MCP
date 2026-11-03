#!/bin/sh
set -eu
# Match browser requests to the stored network domain, including its port.
# Preserve explicit --url arguments when targeting a different test site.
for argument do
    case "$argument" in
        --url|--url=*) exec wp "$@" ;;
    esac
done
exec wp --url="$WORDPRESS_URL" "$@"
