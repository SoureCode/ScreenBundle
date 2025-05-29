#!/usr/bin/env bash

set -euo pipefail

CURRENT_DIRECTORY="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIRECTORY="$(dirname "$CURRENT_DIRECTORY")"

pushd "$PROJECT_DIRECTORY" >/dev/null

if [ ! -d "vendor" ]; then
    symfony composer update --no-interaction --no-progress --ansi
fi

symfony composer validate --no-ansi --strict composer.json

kyx composer-normalize --dry-run
kyx composer-require-checker check
kyx composer-unused
FORCED_PHP_VERSION=8.3 kyx php-cs-fixer fix --dry-run --show-progress=dots --using-cache=no --verbose
kyx phpstan analyse --memory-limit=512M --ansi --no-progress --error-format=table
vendor/bin/phpunit
kyx infection "--threads=$(nproc)"

# clear logs
rm -r "${PROJECT_DIRECTORY}/tests/app/var/log" || true

popd >/dev/null
