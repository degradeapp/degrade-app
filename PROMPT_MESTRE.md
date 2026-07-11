# PROMPT MESTRE — DEGRADÊ: DO CÓDIGO PRONTO AO DEPLOY E À PRIMEIRA VENDA

Você é o engenheiro de implementação do Degradê, um SaaS multi-tenant de gestão de barbearias. Você tem acesso de escrita ao repositório (branch main), roda a suíte de testes e trabalha de forma autônoma, sem perguntar a cada passo. Este documento é auto-contido: ele diz exatamente o que JÁ ESTÁ PRONTO, o que falta, e em que ordem atacar.

Trabalhe em fases. Cada fase só termina com `php artisan test` 100% verde, `npm run build` e `npm run type-check` limpos. Nunca avance com a suíte quebrada.

---

## 1. CONTEXTO DO PROJETO

**Stack:** Laravel 12 / PHP 8.4 / Vue 3 (`<script setup lang="ts">`) / Inertia 2 / Tailwind v4 (tokens em `resources/css/app.css`) / SQLite em dev, `:memory:` nos testes (Pest/PHPUnit), PostgreSQL em produção (ainda NUNCA exercitado de verdade — ver Fase A).

**Arquitetura:** Modular monolith em `app/Modules/*` (Models, Actions, Services, Enums por módulo). Controllers thin: FormRequest → Action → Resource. Eloquent direto, sem repository pattern, sem abstração prematura.

**Multi-tenancy (fronteira de segurança dura):** `tenant_id` em toda entidade via trait `BelongsToTenant` + `TenantScope` global + `TenantContext` resolvido em middleware. NUNCA cruzar tenants. Nenhuma mudança pode causar regressão de isolação (`TenancyIsolationTest` é intocável).

**Fuso horário:** datetime gravado wall-clock no fuso do tenant (NÃO UTC). Default America/Manaus. Não mude isso.

**Papéis:** owner / manager / receptionist / barber, em 3 camadas (middleware `role:`, gating de página com redirect /403, Policies).

**Logins de dev:** `owner@test.local / password`. Tenant demo: `php artisan demo:seed` → `demo@degrade.test / demo1234`, link público `/agendar/demo-degrade`.

---

## 2. O QUE ESTÁ PRONTO (estado em 03/07/2026)

**Qualidade:** 320 testes verdes (1198 assertions) · `npm run build` limpo · `npm run type-check` limpo (vue-tsc 3 + TS 5.9) · `npm audit` 0 vulnerabilidades.

**Produto completo e funcional:**
- Agenda dia/semana com status derivado (in_progress/awaiting_completion derivados de starts_at+duração vs now(), nunca persistidos), empilhamento de cards, filtros.
- Clientes (CRM, histórico, busca) + **exportação CSV** (owner-only, com trilha na auditoria).
- Serviços, equipe (barbeiros, horários, folgas, fotos User↔Barber sincronizadas).
- Comissões: geradas SÓ na conclusão explícita, por serviço com snapshots; dono-barbeiro NÃO gera comissão; pagamento em lote e individual.
- Dashboard com fuso POR LOJA, ocupação (exclui no-show/cancelado), "Hoje" derivado.
- Relatórios, busca global (caminho pgsql com unaccent/pg_trgm + fallback SQLite), auditoria paginada em pt-BR.
- Onboarding 4 passos (negócio → horários → serviços → pronto), sem beco sem saída.
- Ciclo de conta: cancelar assinatura (imediato) vs excluir conta (re-auth, soft-delete, grace 30 dias, `accounts:purge`).
- Link público `/agendar/{slug}`: single-location, wizard serviços→profissional→data/hora→contato, rate-limit, anti-enumeração, 404/422/429, não encaixa.
- Bot de WhatsApp completo (máquina de estados, spam threshold, handoff humano, webhook HMAC fail-closed) — funciona com credenciais manuais de dev (`/whatsapp/setup`).
- Billing Asaas **SANDBOX** funcional: select-plan, webhooks fail-closed, gate 402 nas rotas pagas.

**Decisão de produto consolidada (03/07):** multiunidade/plano Rede REMOVIDOS por completo (código, telas, rotas, tabela `units` e colunas `unit_id` dropadas; dados `plan='rede'` migrados pra `'barbearia'`). Planos finais: **Solo R$ 59 (1 profissional)** e **Barbearia R$ 119 (até 10)**. O ÚNICO diferencial é o nº de profissionais; bot 24h em AMBOS. `test_billing_plan_commercial_contract` e `test_bot_works_on_solo_plan` travam esse contrato de propósito. `Tenant::currentPlan()` usa tryFrom defensivo (valor legado cai em barbearia com warning).

**Ferramentas de operação/venda:**
- `php artisan demo:seed` — tenant de demonstração completo (equipe, serviços, clientes, semana concluída com comissões reais, agenda viva). Idempotente, barrado em produção.
- `php artisan db:backup` — backup local diário (03h30 no scheduler) em `storage/app/backups`, retém 7. pg_dump -Fc em Postgres, cópia de arquivo em SQLite.
- `CriticalFlowTest` — smoke automatizado ponta a ponta pela API real: registro → onboarding → barbeiro → serviço → cliente → agendar → concluir → comissão → dashboard.

**Commits recentes (main):** remoção multiunidade backend → frontend+copy dos planos → fechamento MVP (smoke/export/demo/backup) → fix do type-check (vue-tsc 3, 10 erros de tipo, componentes mortos deletados, audit fix).

---

## 3. FASE A — O QUE FALTA E PODE SER CODADO JÁ (sem contas externas)

Ordem recomendada. Nada aqui vai ao ar; é preparação.

### A1. Validar PostgreSQL de verdade (maior risco técnico escondido)
A suíte roda 100% em SQLite `:memory:`. As migrações e o SearchService têm caminhos específicos de Postgres que NUNCA rodaram contra um Postgres real.
- Subir um Postgres 16+ descartável (Docker Desktop existe na máquina do dono mas o daemon costuma estar desligado — peça pra ele abrir o Docker Desktop, ou use um Postgres local se houver).
- Rodar `php artisan migrate` numa base VAZIA (isso não é migrate:fresh; base descartável nova é ok) e verificar a cadeia inteira, incluindo `2026_05_20_000001_enable_postgres_extensions` (unaccent/pg_trgm), `2026_06_13_120000_add_search_indexes` e as duas migrações de 03/07 (convert rede + drop units).
- Rodar a SUÍTE INTEIRA com `DB_CONNECTION=pgsql` apontando pra essa base. Corrigir o que quebrar (atenção a diferenças SQLite↔Postgres: case-sensitivity de LIKE, ordenação, tipos de data, `DB::raw`).
- Critério de aceite: migrate limpo + 320 testes verdes em pgsql.

### A2. CI (GitHub Actions)
- `.github/workflows/ci.yml`: PHP 8.4, composer install, `php artisan test` (SQLite), `npm ci` + `npm run build` + `npm run type-check`.
- Job adicional com service container `postgres:16` rodando a suíte em pgsql (valida o A1 pra sempre).

### A3. Receita de deploy (o dono executa amanhã com as contas na mão)
- `deploy/DEPLOY.md`: runbook passo a passo pra um VPS Ubuntu (provisionamento: php8.4-fpm + extensões, nginx, postgres, node só pra build ou build no CI, certbot).
- `deploy/deploy.sh`: idempotente — git pull, `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, `php artisan migrate --force`, `config:cache route:cache view:cache event:cache`, `queue:restart`, permissões storage.
- Config templates: nginx server block, supervisor pro `queue:work` (QUEUE_CONNECTION=database — a tabela jobs já existe; o `.env.example` hoje diz redis, simplifique pra database), cron `* * * * * php artisan schedule:run`.
- `.env.production.example` documentado linha a linha: APP_ENV=production, APP_DEBUG=false, APP_KEY, DB pgsql, MAIL (Resend), ASAAS_* (live, vazio com comentário), WHATSAPP_* secrets, SENTRY DSN vazio. Webhooks são fail-closed sem secret em produção — documente isso.

### A4. Monitoramento code-ready
- `composer require sentry/sentry-laravel`, configurado mas inerte com DSN vazio. Zero efeito em dev; o dono cola o DSN amanhã.

### A5. Backup externo preparado (sem ativar)
- No `db:backup`, deixar o gancho de envio externo pronto porém gated por env vazio (ex.: `BACKUP_REMOTE_DISK`), documentado no DEPLOY.md. Destino R2/S3 segue CONGELADO até o dono criar a conta.

### A6. Jurídico placeholder
- `Legal/Terms.vue` e `Legal/Privacy.vue`: revisar onde precisa de razão social/CNPJ/e-mail de contato e deixar marcadores claros pro dono preencher (não inventar dados).

---

## 4. FASE B — O QUE SÓ O DONO PODE FAZER (integração)

Em ordem de dependência. O papel do engenheiro aqui é acompanhar, configurar e testar cada integração assim que o dono entregar a credencial.

1. **Infra:** contratar VPS (Hetzner/DigitalOcean/Forge), domínio + DNS + HTTPS, Postgres de produção. Executar o `DEPLOY.md` da Fase A.
2. **E-mail (Resend):** verificar domínio (SPF/DKIM), colar a chave. CRÍTICO: reset de senha depende de e-mail real; hoje MAIL=log e cliente que esquecer a senha fica trancado. Quando integrar, convite de equipe deve virar convite por LINK (nunca senha em texto puro).
3. **Asaas LIVE:** conta verificada (precisa de CNPJ/MEI), chave live, webhook com secret apontando pro domínio. Fazer UMA cobrança real de teste antes de vender.
4. **WhatsApp — decisão de produto:** Embedded Signup exige aprovação da Meta como Tech Provider (semanas). Alternativa pros primeiros 5-10 clientes: setup manual por cliente na tela `/whatsapp/setup`. ⚠️ ATENÇÃO: a copy dos planos promete "Bot de WhatsApp 24h" em AMBOS; se lançar sem WhatsApp plugável, ou o dono faz o setup manual por cliente, ou a copy precisa ser ajustada pra não prometer o que não entrega no dia 1.
5. **Sentry:** criar conta free, colar DSN. UptimeRobot no domínio.
6. **Validação visual pendente do dono (TESTING_GUIDE.md):** Histórico/auditoria, Horários, tela Barbearia, e o link público ponta a ponta NUM CELULAR REAL.
7. **Go-to-market:** 2-3 barbearias beta em Manaus (visita presencial com `demo:seed`, desconto de fundador) antes de abrir tráfego.

---

## 5. RESTRIÇÕES INVIOLÁVEIS

- **Tenant é a fronteira de segurança.** Nunca cruzar tenants. Nenhuma regressão de isolação, em nenhuma fase. `TenancyIsolationTest` intocável.
- **pt-BR em tudo visível ao usuário.** Sem em-dash como conector em texto ou comentário (permitido só como placeholder de valor vazio). Sem frases-dica gratuitas sob inputs (rótulo curto + "(opcional)" basta).
- **Banco:** `php artisan migrate` sempre; `migrate:fresh` NUNCA sem autorização explícita do dono (exceção: base Postgres descartável e vazia criada só pra validação). Nunca editar migração já aplicada; toda mudança de schema é migração nova, rodando em SQLite E PostgreSQL.
- **Suíte sempre verde.** Cada mudança vem com teste. `php artisan test` + `npm run build` + `npm run type-check` ao final de cada fase, no mínimo.
- **Congelado até o dono entregar a credencial (pode CODAR, não pode ATIVAR):** WhatsApp Cloud API de produção (Embedded Signup), envio real de e-mail (Resend), Asaas live, storage S3/R2. **Deploy:** a PREPARAÇÃO está liberada (scripts, CI, validação pgsql); nada vai ao ar sem o dono.
- **Design:** mobile-first, fundo #0A0A0A, accent #FFD60A, tokens de `resources/css/app.css`, componentes canônicos reutilizados, touch targets adequados. Datetime sempre wall-clock no fuso do tenant.
- **Sem overengineering.** Diffs mínimos, sem renomear o que funciona, sem extrair interfaces, sem abstração especulativa.
- **Autonomia:** trabalhe nas 3 macro-etapas (desenvolver em massa → validar e corrigir → finalizar) sem pedir aprovação a cada passo. PARE e reporte apenas se: um teste de isolação por tenant quebrar sem causa óbvia, uma ação ameaçar dados reais, ou faltar credencial/decisão que só o dono tem.

---

## 6. CHECKLIST DE ACEITE FINAL (pronto pra vender)

- [ ] Suíte 100% verde em SQLite **e** PostgreSQL; build e type-check limpos; CI verde no GitHub
- [ ] `deploy.sh` + `DEPLOY.md` executados num VPS real: app no ar com HTTPS, worker e cron rodando
- [ ] Backup diário local + cópia externa configurada
- [ ] E-mail real funcionando (reset de senha testado de ponta a ponta)
- [ ] Asaas live com UMA cobrança real de teste concluída (criar → pagar → webhook → status ativo)
- [ ] Sentry recebendo erros; uptime monitorado
- [ ] Decisão do WhatsApp tomada (manual vs adiar) e copy dos planos coerente com ela
- [ ] Validação visual do dono concluída (TESTING_GUIDE) num celular real
- [ ] Termos/Privacidade com razão social real
- [ ] `demo:seed` rodado em produção? NÃO — é barrado em produção de propósito; demo é local/staging
