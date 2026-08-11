#!/usr/bin/env bash

# Replays the CI locally, but on fresh dependencies: the development machine's vendor holds symlinks to the sibling repositories, which expose code not yet tagged on Packagist. This script works on a clean copy, where `composer update` only sees the published versions - exactly what GitHub sees.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_CI="8.4"

# The PHP version itself is not reproducible here: it depends on the installed interpreter
PHP_LOCAL="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
if [ "$PHP_LOCAL" != "$PHP_CI" ]; then
    echo "⚠  PHP $PHP_LOCAL en local, $PHP_CI en CI : les écarts liés à la version du langage ne seront pas détectés ici."
    echo
fi

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

# vendor/ and composer.lock are excluded to force a fresh resolution from Packagist; uncommitted changes are kept, since they are exactly what has to be validated before pushing
echo "→ Copie du dépôt vers $WORK"
rsync -a \
    --exclude '.git/' \
    --exclude 'vendor/' \
    --exclude 'composer.lock' \
    --exclude '.phpunit.cache/' \
    --exclude '.php-cs-fixer.cache' \
    --exclude '.phpunit.result.cache' \
    --exclude 'coverage/' \
    "$ROOT/" "$WORK/"

cd "$WORK"

echo "→ composer update (Packagist, sans les liens symboliques du vendor local)"
composer update --no-interaction --no-progress

echo "→ Contrôles qualité"
composer qa

echo
echo "✓ Les contrôles de la CI passent sur des dépendances fraîches."
