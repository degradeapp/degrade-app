#!/usr/bin/env bash
#
# Deploy do Degradê num VPS Ubuntu. Idempotente: rodar duas vezes seguidas
# não quebra nada. Executar como o usuário dono do app (ex.: deploy), nunca root.
#
#   cd /var/www/degrade && bash deploy/deploy.sh
#
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/degrade}"
PHP_BIN="${PHP_BIN:-php}"

cd "$APP_DIR"

echo "==> Modo manutenção (volta sozinho no fim, mesmo com erro)"
$PHP_BIN artisan down --retry=15 || true
trap '$PHP_BIN artisan up' EXIT

echo "==> Código"
git pull --ff-only

echo "==> Dependências PHP (sem dev, autoloader otimizado)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Build do frontend"
npm ci
npm run build

echo "==> Migrações"
$PHP_BIN artisan migrate --force

echo "==> Caches de produção"
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache
$PHP_BIN artisan event:cache

echo "==> Permissões de storage e cache"
chmod -R ug+rwX storage bootstrap/cache

echo "==> Reinicia workers da fila (pegam o código novo)"
$PHP_BIN artisan queue:restart

echo "==> Deploy concluído"
