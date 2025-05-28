#!/usr/bin/env bash

set -euo pipefail

CURRENT_DIRECTORY="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIRECTORY="$(dirname "$CURRENT_DIRECTORY")"

pushd "$PROJECT_DIRECTORY" >/dev/null

symfony composer update --no-interaction --no-progress --ansi
symfony composer validate --no-ansi --strict composer.json

kyx phpstan analyse --memory-limit=512M --ansi --no-progress --error-format=table --generate-baseline=phpstan-baseline.neon

popd >/dev/null
