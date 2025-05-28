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
kyx php-cs-fixer fix --dry-run --show-progress=dots --using-cache=no --verbose
kyx phpstan analyse --memory-limit=512M --ansi --no-progress --error-format=table
vendor/bin/phpunit
kyx infection --threads=4

popd >/dev/null
