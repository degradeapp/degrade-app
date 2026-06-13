# CLAUDE.md — Degradê (backend)

Mapa e convenções do repo pra não reler tudo a cada sessão. Atualizado em 12/06/2026.

## O que é
SaaS multi-tenant de gestão de barbearias. Laravel 12 + PHP 8.4, Inertia/Vue 3 no front (NÃO mexer nesta fase), SQLite em dev/test (`:memory:` + RefreshDatabase), Postgres em prod. Suíte Pest com 289+ testes verdes; rodar `php artisan test` a cada etapa.

## Fronteiras de segurança (NÃO quebrar)
1. **Tenant é a fronteira dura.** Todo model de negócio usa `BelongsToTenant` (app/Modules/Tenant/Traits): `TenantScope` global filtra por `app('tenant')` ou pelo `auth()->user()->tenant_id`; `tenant_id` é imutável após criado (exception no updating). `EnsureTenantContext` (middleware web global) resolve o tenant da request; `TenantContext::set()` também aplica o FUSO da loja na request inteira (datetime é wall-clock no fuso do app, NUNCA UTC).
2. **Unidade é escopo DENTRO do tenant** (plano Rede). `UnitContext` (singleton, resolvido por `ResolveUnitContext` após o tenant): dono/gerente trocam de unidade ou veem consolidado (`scopedUnitId()` null = todas); barbeiro/recepção são travados na unidade-casa (`isLocked()`). `currentUnitId()` nunca é null quando há unidades (cai na 1ª). `AppointmentPolicy::sameTenantAndUnit` isola por unidade. Clientes e serviços são da rede; agenda/equipe são por unidade.
3. **Papéis** (UserRole): owner / manager / receptionist / barber. 3 camadas: middleware `role:` nas rotas, gating de página em routes/modules/web.php, Policies. Balcão (recepção/barbeiro) não vê dinheiro/gestão. `BasePolicy::before` dá atalho ao dono SÓ dentro do próprio tenant (nunca cross-tenant).
4. **Validação `exists`** SEMPRE escopada por tenant: `Rule::exists(...)->where('tenant_id', $tenantId)->whereNull('deleted_at')`. `exists:tabela,id` puro é IDOR de escrita (a regra consulta sem o scope global).

## Estrutura
- `app/Modules/{Tenant,User,Auth,Customer,Barber,Service,Appointment,Commission,Whatsapp,Notification,Unit}`: Models / Actions (invokable readonly) / Services / Enums por feature.
- `app/Http/{Controllers,Middleware,Requests,Resources}` + `app/Policies`.
- Rotas: `routes/web.php` carrega `routes/api.php` + `routes/modules/*.php` (um arquivo por módulo; API sob prefixo `api`, middleware `auth:sanctum` + `role:` declarados no arquivo do módulo).
- Link público: `routes/modules/public_booking.php` + `PublicBookingController` (sem auth, tenant via slug, throttle por IP).

## Domínio (regras que pegam)
- Agendamento: bloco fixo de 30 min (`Appointment::DEFAULT_BLOCK_MINUTES`), serviço não tem duração. Status persistido x `effectiveStatus()` derivado (in_progress / awaiting_completion vêm do relógio; completed só por ação explícita, e é ela que gera comissão).
- Balcão PODE encaixar (CreateAppointment não bloqueia conflito; só o passado é barrado no Request). Link público NÃO encaixa (checa `AvailabilityService::isAvailable` antes).
- Comissão: gerada na conclusão via evento, com snapshots em appointment_services; o DONO nunca gera comissão. Resolução: pivot barber_service > service > barber default > settings do tenant.
- Billing Asaas: webhook é a ÚNICA fonte de verdade pra status=active. Sandbox; LIVE congelado. WhatsApp Cloud / Resend / S3-R2 também congelados (pode melhorar código, não ativar).
- Exclusão de conta: soft-delete do tenant + purge em 30 dias (`accounts:purge`); login dentro da janela recupera.
- Datas no SQLite: cuidado com colunas `date` guardando "Y-m-d 00:00:00" (ver BarberTimeOff Attribute e comentário no ReportsController). Migrations precisam rodar em SQLite E Postgres (nada de ALTER NOT NULL no SQLite; use nullable + garanta no app).

## Convenções de código
- pt-BR em TUDO visível ao usuário (mensagens, validação; lang/pt_BR/validation.php tem os attributes). Sem inglês em mensagem. Sem em-dash como conector em texto/comentário.
- Form Requests fazem authorize() por papel; Policies fazem tenant/unidade. Telefone: normalizar pra dígitos no prepareForValidation + `BrazilianPhone` (11 dígitos, DDD>=11, 9 na 3ª posição).
- Actions invokable `readonly` com parâmetros nomeados. Resources com `resolve()` quando o Inertia consome cru.
- Pint (preset laravel): `./vendor/bin/pint --dirty` antes de commitar. Commits pequenos e descritivos.
- Auditoria: AuditObserver em Customer/Barber/Service/Appointment/Commission grava em activity_log.

## Rate limiters (AppServiceProvider)
`auth` (5/min email+ip, 20/min ip), `api` (120/min user/ip), `public-booking` (30/min ip, leituras públicas), `public-booking-create` (5/min + 20/h ip).

## Como rodar
```bash
cp .env.example .env && php artisan key:generate
composer install && npm install
touch database/database.sqlite
php artisan migrate --seed
php artisan test   # tudo verde, sempre
```
Login de teste: owner@test.local / password. Não usar `migrate:fresh` em dev sem combinar.
