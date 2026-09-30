#!/usr/bin/env bash
# Tests im Container, PHP ist lokal nicht installiert: ./bin-test.sh [phpunit-Argumente]
set -euo pipefail
cd "$(dirname "$0")"
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app -e COMPOSER_HOME=/tmp/composer composer:2 \
  sh -c 'test -d vendor || composer install -q --no-interaction; vendor/bin/phpunit "$@"' -- "$@"
