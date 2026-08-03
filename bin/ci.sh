#!/usr/bin/env bash

# Rejoue la CI en local, mais sur des dépendances fraîches : le vendor de la machine de développement contient des liens symboliques vers les dépôts frères, qui exposent du code non encore tagué sur Packagist. Ce script travaille sur une copie propre, où `composer update` ne voit que les versions publiées - exactement ce que voit GitHub.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_CI="8.4"

# La version de PHP, elle, n'est pas reproductible ici : elle dépend de l'interpréteur installé
PHP_LOCAL="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
if [ "$PHP_LOCAL" != "$PHP_CI" ]; then
    echo "⚠  PHP $PHP_LOCAL en local, $PHP_CI en CI : les écarts liés à la version du langage ne seront pas détectés ici."
    echo
fi

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

# vendor/ et composer.lock sont exclus pour forcer une résolution neuve depuis Packagist ; les modifications non commitées, elles, sont conservées, puisque ce sont justement celles que l'on cherche à valider avant de pousser
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
