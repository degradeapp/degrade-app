# Relatório de Eficiência — Degradê (backend)

Auditoria estática de N+1, índices, paginação, caching e agregação. Ordenado por impacto. Nenhuma destas mudanças foi aplicada automaticamente (todas mexem em comportamento coberto por testes existentes; aplicar com a suíte rodando, uma por commit).

## E1 — SearchService faz a busca em PHP, não no banco (ALTO impacto)
`searchCustomers/searchBarbers/searchServices` fazem `->get()` da tabela INTEIRA do tenant e filtram com `str_contains` em memória. Com 10k clientes, cada busca carrega 10k models. O TESTING_GUIDE fala em pg_trgm, mas o código não usa.
**Fix:** em Postgres, `whereRaw("unaccent(name) ILIKE unaccent(?)", ["%{$q}%"])` (ou similarity do pg_trgm) + índice GIN `gin_trgm_ops` em customers.name/barbers.name/services.name; em SQLite (dev), fallback `where('name','like',...)`. Paginar no banco. Bônus: `clearCache()` usa `Cache::flush()`, que apaga o cache de TODOS os tenants; trocar por chaves versionadas por tenant (`search:{tenant}:v{N}:...`).

## E2 — Dashboard: N+1 e trabalho em PHP (MÉDIO-ALTO)
- `occupationToday` faz `timeOffs()->exists()` POR barbeiro (N+1). Fix: eager `with(['timeOffs' => janela de hoje])` ou uma query agregada.
- Contagens de status (`completed/awaiting/pending`) e `bookedMinutes` são feitas iterando a collection do dia. Pra um dia, ok; mas a query do dia carrega TODOS os agendamentos com 4 relações pra no fim usar 5 + 5 itens. Fix barato: manter (volume diário é pequeno); fix ideal: uma query agregada por status derivado + duas queries `take(5)`.
- `revenueLast7Days` já agrega no banco. OK.

## E3 — AppointmentPricer::resolveCommission: N+1 por serviço (MÉDIO)
`Barber::find($barberId)` + `barber->services()->where(...)->first()` dentro do loop de snapshot. Com 3 serviços, ~6 queries extras por criação. Fix: pré-carregar os barbeiros envolvidos com o pivot (`Barber::with(['services'])->whereIn('id', $barberIds)`) uma vez e resolver em memória.

## E4 — AppointmentController::indexPage carrega ~60 dias sem teto (MÉDIO)
Janela de -14 a +45 dias com `->get()`. Barbearia movimentada (40/dia) = ~2.400 registros + 4 relações por render da agenda. Fix: reduzir a janela ao que a tela mostra (semana visível) e buscar o resto sob demanda via API paginada; ou no mínimo `select` enxuto.

## E5 — ActivityLogController: limit(100) sem paginação (BAIXO-MÉDIO)
A tarefa já pedia: trocar `limit(100)` por `paginate()` (cursor pagination é ideal aqui, ordenado por created_at desc, índice `[tenant_id, created_at]` já existe).

## E6 — CommissionController::pendingSummary agrupa em PHP (BAIXO-MÉDIO)
`->get()->groupBy()` carrega todas as comissões pendentes pra agrupar em memória. Fix: `selectRaw('barber_id, COUNT(*) c, SUM(amount) total')->groupBy('barber_id')` pro cabeçalho e buscar `items` só do barbeiro expandido (lazy).

## E7 — Índices (revisão das migrations)
**Já cobertos (bom):** appointments `[tenant_id, starts_at]`, `[tenant_id, barber_id, starts_at]`, `[tenant_id, status, starts_at]`, `[tenant_id, unit_id, starts_at]`; commissions `[tenant_id, barber_id, status, reference_date]`; activity_log `[tenant_id, created_at]`; barbers `[tenant_id, unit_id]`; whatsapp_conversations `[tenant_id, phone_number]` unique.
**Faltando (adicionar em migration nova):**
- `tenants.slug` já é unique (cobre o link público). OK.
- `customers (tenant_id, name)` pra ordenação/busca da listagem (hoje só `[tenant_id, created_at]` e phone). Com E1 em Postgres, índice GIN trgm em name.
- `appointments (barber_id, starts_at)` puro: o `hasConflict`/availability consulta via relação `barber->appointments()` SEM tenant na cláusula (o scope filtra por tenant_id mas o planner usa `[tenant_id, barber_id, starts_at]`; na prática esse composto cobre, então só monitorar com EXPLAIN).
- `units (tenant_id, is_active)` já existe. OK.

## E8 — Caching
- Disponibilidade: `InvalidateAvailabilityCache` esquece uma chave `barber:{id}:availability` que NADA grava (cache morto). Ou implementar cache de slots por `barber:{tenant}:{id}:{date}` com TTL curto + invalidação nos eventos (created/rescheduled/cancelled/completed), ou remover o listener. Chave sempre com tenant.
- Dashboard: cachear `revenueLast7Days` por `dash:{tenant}:{unit|all}:rev7:{hoje}` com TTL de alguns minutos é ganho fácil e sem risco de vazar (chave por tenant).
- Search: já cacheia por tenant (30s), ver E1 sobre o flush global.

## E9 — Pequenos
- `UnitContext::resolve()` roda uma query de units em TODA request web. Aceitável (tabela minúscula), mas dá pra cachear por tenant com invalidação no CRUD de unidades.
- `HandleInertiaRequests::share` chama `UnitContext` 4x (4 resoluções do container, 1 query já feita). Consolidar em uma variável.
- `WhatsappController::listConversations/showConversation` com `take(50/100)`: ok por ora; paginação cursor quando a caixa crescer.

## Benchmarks sugeridos (com a suíte)
Usar contagem de queries em teste (`DB::enableQueryLog()` ou `expectsDatabaseQueryCount` do Laravel 11+/12) nos pontos E2 e E3:
```php
it('cria agendamento com 3 servicos sem N+1 de comissao', function () {
    // arrange...
    DB::enableQueryLog();
    // act: POST /api/appointments
    expect(count(DB::getQueryLog()))->toBeLessThan(15);
});
```
