#!/usr/bin/env bash
#
# Deploy do Degradê num VPS Ubuntu. Idempotente: rodar duas vezes seguidas
# não quebra nada. Executar como o usuário dono do app (ex.: deploy), nunca root.
# Serve pra produção E pra staging (cada um no seu diretório, com o seu .env).
#
#   cd /var/www/degrade && bash deploy/deploy.sh
#
# Se QUALQUER passo falhar, o site FICA em manutenção (mostrar código novo contra
# banco velho, ou o contrário, é pior que ficar fora do ar) e o script imprime como
# voltar pro commit anterior e restaurar o backup feito antes das migrações.
#
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/degrade}"
PHP_BIN="${PHP_BIN:-php}"

cd "$APP_DIR"

PREVIOUS_COMMIT="$(git rev-parse HEAD)"
STEP="início"

on_error() {
    echo
    echo "!! Deploy FALHOU no passo: ${STEP}"
    echo "!! O site continua em MANUTENÇÃO de propósito."
    echo "!! Voltar pra versão anterior:"
    echo "     git reset --hard ${PREVIOUS_COMMIT}"
    echo "     composer install --no-dev --optimize-autoloader --no-interaction && npm ci && npm run build"
    echo "     # só se as migrações já tinham rodado: restaurar o backup pré-deploy (DEPLOY.md, seção Backup)"
    echo "     $PHP_BIN artisan config:cache && $PHP_BIN artisan route:cache && $PHP_BIN artisan up"
}
trap on_error ERR

echo "==> Modo manutenção"
STEP="manutenção"
$PHP_BIN artisan down --retry=15 || true

echo "==> Código (antes: ${PREVIOUS_COMMIT:0:7})"
STEP="git pull"
git pull --ff-only

echo "==> Dependências PHP (sem dev, autoloader otimizado)"
STEP="composer install"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Checagem do .env (erro aqui = configuração perigosa, deploy para)"
STEP="deploy:check"
$PHP_BIN artisan config:clear
$PHP_BIN artisan deploy:check

echo "==> Build do frontend"
STEP="build do frontend"
npm ci
npm run build

echo "==> Backup do banco ANTES das migrações"
STEP="backup pré-migração"
$PHP_BIN artisan db:backup

echo "==> Migrações"
STEP="migrate"
$PHP_BIN artisan migrate --force

echo "==> Caches de produção"
STEP="caches"
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache
$PHP_BIN artisan event:cache

echo "==> Permissões de storage e cache"
STEP="permissões"
chmod -R ug+rwX storage bootstrap/cache

echo "==> Reinicia workers da fila (pegam o código novo)"
STEP="queue:restart"
$PHP_BIN artisan queue:restart

echo "==> Saindo da manutenção"
STEP="up"
$PHP_BIN artisan up

echo "==> Deploy concluído: ${PREVIOUS_COMMIT:0:7} -> $(git rev-parse --short HEAD)"
