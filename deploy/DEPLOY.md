# DEPLOY DO DEGRADÊ NUM VPS UBUNTU

Runbook completo, do servidor zerado ao app no ar com HTTPS, worker e cron.
Testado contra Ubuntu 24.04 LTS. Tempo estimado: 1h a 2h na primeira vez.

Pré-requisitos que só você pode providenciar:
- VPS Ubuntu 24.04 (2 GB de RAM já serve pro começo). Hetzner, DigitalOcean etc.
- Domínio com DNS apontando o registro A pro IP do VPS (fazer isso ANTES, o certificado HTTPS depende disso).
- Acesso SSH root ao servidor.

Convenções deste guia: app em `/var/www/degrade`, usuário de sistema `deploy`, banco e usuário Postgres `degrade`. Onde estiver `SEU_DOMINIO`, troque pelo domínio real.

---

## 1. Primeiro acesso e usuário

```bash
ssh root@IP_DO_SERVIDOR

adduser deploy            # cria o usuário que roda o app (defina uma senha forte)
usermod -aG sudo deploy

# firewall: só SSH e web
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw enable
```

Daqui em diante, tudo como `deploy` (`su - deploy` ou novo SSH).

## 2. Pacotes

```bash
sudo apt update && sudo apt upgrade -y

# PHP 8.4 (Ubuntu 24.04 traz 8.3; o PPA do Ondrej tem o 8.4)
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.4-fpm php8.4-cli php8.4-pgsql php8.4-mbstring \
  php8.4-xml php8.4-curl php8.4-zip php8.4-intl php8.4-bcmath php8.4-gd

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node 22 LTS (só pra buildar o frontend; não roda nada em produção)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs

# Servidor web, banco, worker, HTTPS, backup
sudo apt install -y nginx postgresql postgresql-contrib supervisor certbot python3-certbot-nginx git
```

`postgresql-contrib` é obrigatório: a busca usa as extensões `unaccent` e `pg_trgm` que vêm nele.

## 3. Banco de dados

```bash
sudo -u postgres psql
```

```sql
CREATE USER degrade WITH PASSWORD 'TROQUE_POR_SENHA_FORTE';
CREATE DATABASE degrade OWNER degrade;
\c degrade
-- As extensões de busca precisam de superusuário UMA vez; a migração
-- 'enable_postgres_extensions' também tenta criar, mas garantir aqui evita
-- falha de permissão no migrate:
CREATE EXTENSION IF NOT EXISTS unaccent;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
\q
```

Guarde a senha: ela vai no `.env` (`DB_PASSWORD`).

## 4. Código e configuração

```bash
sudo mkdir -p /var/www/degrade
sudo chown deploy:www-data /var/www/degrade
git clone URL_DO_REPOSITORIO /var/www/degrade
cd /var/www/degrade

cp .env.production.example .env
nano .env    # preencher linha a linha; o arquivo é comentado, siga os comentários
php artisan key:generate --force

composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build

php artisan migrate --force

php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache

# storage precisa ser gravável pelo php-fpm (grupo www-data)
sudo chgrp -R www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

Observação: NÃO precisa de `php artisan storage:link`. As imagens são servidas por rota própria (`MediaController`), de propósito.

## 5. Nginx e HTTPS

```bash
sudo cp deploy/nginx.conf.example /etc/nginx/sites-available/degrade
sudo nano /etc/nginx/sites-available/degrade   # trocar SEU_DOMINIO
sudo ln -s /etc/nginx/sites-available/degrade /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

# HTTPS (o certbot edita o bloco do nginx sozinho e agenda a renovação)
sudo certbot --nginx -d SEU_DOMINIO
```

Teste: `https://SEU_DOMINIO/up` deve responder 200 (health check do Laravel).

## 6. Worker da fila e cron

Lembretes de WhatsApp e outros jobs rodam numa fila em banco (`QUEUE_CONNECTION=database`). O worker fica de pé via supervisor:

```bash
sudo cp deploy/supervisor-degrade.conf.example /etc/supervisor/conf.d/degrade.conf
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status    # degrade-worker deve aparecer RUNNING
```

O agendador (lembretes, purga de contas, backup diário às 3h30) precisa de UMA linha no cron do usuário `deploy`:

```bash
crontab -e
```

```
* * * * * cd /var/www/degrade && php artisan schedule:run >> /dev/null 2>&1
```

## 7. Smoke test de produção

1. Abrir `https://SEU_DOMINIO/register` e criar uma conta real sua.
2. Completar o onboarding (negócio, horários, serviços).
3. Criar um agendamento pela agenda e concluir ele.
4. Abrir o link público `https://SEU_DOMINIO/agendar/SEU_SLUG` NUM CELULAR e agendar como cliente.
5. `php artisan db:backup` na mão e conferir que apareceu arquivo em `storage/app/backups`.

## 8. Backup

- Diário e automático às 3h30 (via cron da seção 6) em `storage/app/backups`, retém os últimos 7.
- Formato Postgres: dump custom (`.dump`). Restauração:
  `pg_restore --clean --no-owner -d degrade -h 127.0.0.1 -U degrade storage/app/backups/ARQUIVO.dump`
- Cópia externa: CONGELADA até existir conta R2/S3. Quando existir, preencha `R2_*` e `BACKUP_REMOTE_DISK=r2` no `.env` e rode `php artisan config:cache`. O comando `db:backup` passa a subir cada backup pro bucket sozinho, além da cópia local.
- Enquanto não houver cópia externa, backup mora no MESMO disco do servidor: se o VPS morrer, o backup morre junto. Prioridade alta assim que tiver a conta.

## 9. Monitoramento (Sentry)

1. Criar conta free em sentry.io, projeto tipo Laravel.
2. Colar o DSN no `.env` (`SENTRY_LARAVEL_DSN=...`) e rodar `php artisan config:cache`.
3. Testar: `php artisan sentry:test` deve criar um evento no painel.

Com o DSN vazio o Sentry fica desligado e não tem efeito nenhum.

## 10. Webhooks (Asaas e WhatsApp): fail-closed

Em produção, webhook SEM secret configurado é REJEITADO por segurança:

- **Asaas:** ao configurar o webhook no painel deles (URL `https://SEU_DOMINIO/api/webhooks/asaas`), defina um secret e cole o mesmo valor em `ASAAS_WEBHOOK_SECRET`. Se o secret ficar vazio no `.env`, NENHUM evento de pagamento entra (assinatura ninguém vira ativa).
- **WhatsApp:** `WHATSAPP_APP_SECRET` (App Secret do app Meta) valida o HMAC de cada POST; `WHATSAPP_VERIFY_TOKEN` responde o GET de verificação. Sem eles, o bot não recebe mensagem em produção.

Depois de qualquer mudança no `.env`: `php artisan config:cache`.

## 11. Pendências que o app NÃO resolve sozinho

- **E-mail:** enquanto `MAIL_MAILER=log`, reset de senha NÃO chega pra ninguém. Integrar Resend (verificar domínio, SPF/DKIM, colar `RESEND_API_KEY`, trocar `MAIL_MAILER=resend`) antes de ter cliente pagante.
- **Jurídico:** `resources/js/pages/Legal/Terms.vue` e `Privacy.vue` têm os marcadores `[RAZÃO SOCIAL]`, `[CNPJ]`, `[COMARCA]`, `[E-MAIL DE CONTATO]` e `[E-MAIL DO ENCARREGADO]` pra preencher com os dados reais da empresa (buscar por `[` nos dois arquivos). Depois de editar: novo deploy (o build embute as páginas).
- **demo:seed em produção:** bloqueado de propósito. Demonstração é local/staging.

## 12. Atualizações (todo deploy depois do primeiro)

```bash
cd /var/www/degrade && bash deploy/deploy.sh
```

O script põe o app em manutenção, puxa o código, instala dependências, builda, migra, refaz os caches e reinicia o worker. Se algo falhar no meio, ele sai do modo manutenção sozinho ao final do processo.
