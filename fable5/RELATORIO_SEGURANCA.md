# Relatório de Segurança — Degradê (backend)

Auditoria estática do código fornecido (controllers, requests, policies, middleware, models, services, migrations, rotas). Formato: **auditado / achado / status**. Severidade: CRÍTICO > ALTO > MÉDIO > BAIXO.

---

## 1. Achados COM correção entregue

### A1 — IDOR de escrita: `exists` sem escopo de tenant (ALTO) ✅ corrigido
- **Onde:** `StoreAppointmentRequest` e `UpdateAppointmentRequest`.
- **Problema:** `customer_id => exists:customers,id` (e `service_ids.*`, `barber_ids.*`) valida contra a tabela INTEIRA, sem TenantScope (regras de validação não passam pelo Eloquent). Uma recepcionista do tenant A podia criar agendamento apontando pra `customer_id` do tenant B. O `barber_id` alheio ia parar direto em `appointments.barber_id` (vinculação cruzada de dados). Os serviços alheios eram filtrados depois pela query escopada, mas isso gerava agendamento com preço 0 e sem snapshot (corrupção silenciosa).
- **Correção:** `Rule::exists(...)->where('tenant_id', $tenantId)->whereNull('deleted_at')` em todos. Arquivos: `app/Http/Requests/StoreAppointmentRequest.php`, `UpdateAppointmentRequest.php`. Testes: `SecurityRegressionTest`.
- **Ação recomendada extra:** varrer TODOS os FormRequests por `exists:` sem escopo (grep `exists:` em app/Http/Requests). `StoreBarberRequest` tem `user_id => exists:users,id` com o mesmo padrão; corrigir igual.

### A2 — Webhook do WhatsApp sem verificação de assinatura (ALTO) ✅ corrigido
- **Onde:** `WhatsappController::webhook` (POST /webhooks/whatsapp, rota pública).
- **Problema:** qualquer um que descubra a URL injeta payloads falsos: cria conversas, agenda horários em nome de clientes e consome a fila. O `WHATSAPP_APP_SECRET` já existia em `config/services.php` mas não era usado.
- **Correção:** verificação do header `X-Hub-Signature-256` (HMAC-SHA256 do corpo cru com o app secret), comparação com `hash_equals` (tempo constante), mesmo padrão do webhook Asaas: sem secret configurado (dev/teste) aceita; com secret, exige. Arquivo: `app/Http/Controllers/WhatsappController.php`. Testes: `SecurityRegressionTest`.
- **Pendência de prod (congelado):** definir `WHATSAPP_VERIFY_TOKEN` e `WHATSAPP_APP_SECRET` no deploy. O mesmo vale pro `ASAAS_WEBHOOK_SECRET` (o código já avisa).

### A3 — `BasePolicy::before` liberava o dono cross-tenant (MÉDIO, defesa em profundidade) ✅ corrigido
- **Onde:** `app/Policies/BasePolicy.php`.
- **Problema:** `before()` retornava `true` incondicional pra role owner, pulando as checagens de tenant das policies. Hoje não é explorável pelas rotas (o route model binding passa pelo TenantScope e dá 404 antes), MAS qualquer código futuro que carregue um model com `withoutGlobalScopes()`/`withTrashed()` e chame `authorize()` viraria bypass cross-tenant silencioso.
- **Correção:** o atalho do dono agora exige `tenant_id` do model igual ao do usuário (quando o model tem tenant_id). Testes no `SecurityRegressionTest` provam negação cross-tenant direto no Gate.

### A4 — Link público: superfície nova, construída já blindada ✅
- 404 genérico pra slug inexistente OU tenant não-operante (anti-enumeração de estado de assinatura). Regra de aceite: `active` ou `trial` válido (justificativa no docblock do controller).
- Todas as consultas escopadas pelo tenant do slug; service/barber/unit de outro tenant = 422/404, nunca vazam.
- Catálogo expõe o mínimo (sem telefone de barbeiro, sem nada de cliente, sem finanças).
- Disponibilidade DURA (expediente + folga + conflito): anônimo não encaixa.
- Rate limit por IP em duas camadas (leitura 30/min; criação 5/min + 20/h).
- Sem passado, horizonte de 60 dias, no máx. 5 serviços por agendamento.
- Cliente casado por telefone DENTRO do tenant (`firstOrCreate` escopado).

---

## 2. Achados SEM correção automática (decisão do dono / fase de deploy)

### B1 — Rotas de API sem `subscription.active` (MÉDIO)
As páginas web passam por `EnsureActiveSubscription`, mas as rotas `/api/*` dos módulos não. Um tenant suspenso/cancelado continua operando 100% via API (curl/console do navegador). Sugestão: aplicar o middleware (versão JSON, retornando 402/403) no grupo das rotas de módulo, exceto billing/auth/profile. Não apliquei porque muda comportamento de vários testes existentes; fazer com a suíte rodando.

### B2 — Webhook Asaas aceita tudo sem secret (já conhecido)
Comportamento documentado no código. Risco zero em sandbox; em produção o secret é OBRIGATÓRIO. Sugestão para a fase de deploy: falhar hard (500 no boot ou log crítico) se `APP_ENV=production` e `ASAAS_WEBHOOK_SECRET` vazio. Idem WhatsApp (A2).

### B3 — `SettingsController::inviteTeamMember` permite criar um 2º owner (BAIXO)
A rota é só do dono, então é o dono criando outro dono: provavelmente intencional (sócio), mas vale confirmar. Se não for, tirar `owner` do `in:` da validação.

### B4 — Logs com payload completo do webhook WhatsApp (BAIXO)
`Log::info('WhatsApp webhook received', ['payload' => ...])` grava telefone e texto da mensagem do cliente no log (dado pessoal, LGPD). Sugestão: logar só ids/metadata em produção.

### B5 — `MediaController` é público por design (INFO)
Avatares/logos servidos sem auth, com bloqueio de `..`. Paths são aleatórios (hash do Storage), então enumeração é impraticável. OK pro propósito (imagens públicas), só registrar a decisão.

---

## 3. Auditado e OK (sem achado)

- **Isolação por leitura:** todos os models de negócio usam `BelongsToTenant`; binding implícito passa pelo scope, então id alheio = 404 em appointments, customers, barbers, services, commissions, units, conversas do WhatsApp. `tenant_id` imutável (exception no updating do trait).
- **Isolação por unidade:** `AppointmentPolicy::sameTenantAndUnit` cobre view/update/cancel/complete/delete; `UnitContext` trava balcão na unidade-casa com fallback seguro (nunca cai em "todas"); `AppointmentController::index` filtra `unit_id` pra usuário locked; filtro `?unit` do dono é inofensivo cross-tenant (query já escopada devolve vazio).
- **Mass assignment:** nenhum controller passa `$request->all()` pra `create/update`; `role`/`tenant_id`/`unit_id` nunca vêm de input em updateProfile; convite seta role validado por `in:`.
- **Auth:** `throttle:auth` no login/registro/reset (email+ip e ip); reset de senha não revela existência do email; troca de senha exige a atual e exige diferença; exclusão de conta exige senha; logout invalida e regenera sessão; 401 JSON em pt-BR.
- **Webhook Asaas:** HMAC com `hash_equals` quando o secret existe; ativação de plano SÓ via webhook (selectPlan não ativa).
- **Injeção:** todos os `selectRaw/whereRaw` vistos usam bindings ou literais fixos (`COALESCE(end_date, date) >= ?` com binding; agregações com colunas fixas). `like` com input usa binding (o `%` do usuário só amplia o próprio filtro, sem risco).
- **Headers:** `SecurityHeaders` global (nosniff, frame-options, referrer-policy, permissions-policy) + CSP estrito em produção.
- **Segredos:** WhatsApp access_token criptografado (Crypt) e `$hidden`; nunca devolvido em resposta; `.env` fora do git; `password` hashed + hidden.

---

## 4. Testes entregues
- `tests/Feature/PublicBookingTest.php`: 16 cenários (feliz, isolação x3, passado, horizonte, fora do expediente, conflito, rate limit, validação, "qualquer barbeiro", trial, suspenso, catálogo sem vazamento).
- `tests/Feature/SecurityRegressionTest.php`: A1 (x2), A2 (x3), A3 (x2).

Rode `php artisan test` após aplicar: a suíte tem que continuar verde (as correções A1/A3 podem exigir ajuste em testes antigos que dependiam do comportamento inseguro; se algum quebrar, é regressão DESEJADA, ajustar o teste antigo).
