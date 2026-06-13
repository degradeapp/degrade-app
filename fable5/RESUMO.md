# RESUMO — o que foi feito, como aplicar, o que falta

## ⚠️ Contexto importante
Este pacote foi produzido FORA do repo (ambiente sem PHP/composer e sem a suíte de testes). Todo o código foi escrito lendo o código-fonte real fornecido e segue as convenções do projeto, mas **nada aqui rodou `php artisan test`**. Aplicar no repo com o Claude Code e validar a suíte é o passo obrigatório.

## O que está no pacote

### Feature nova: link público de agendamento
| Arquivo | O que faz |
|---|---|
| `app/Http/Controllers/PublicBookingController.php` | Catálogo, horários e criação, tudo escopado pelo slug |
| `app/Http/Requests/PublicBookingRequest.php` | Validação rígida (nome, BrazilianPhone, máx. 5 serviços) |
| `routes/modules/public_booking.php` | Rotas públicas com throttle |
| `app/Modules/Appointment/Actions/CreateAppointment.php` | PATCH: parâmetro `unitId` opcional (retrocompatível) |
| `app/Providers/AppServiceProvider.php` | PATCH: limiters `public-booking` e `public-booking-create` |
| `tests/Feature/PublicBookingTest.php` | 16 cenários (feliz + abusos + isolação + rate limit) |

Decisões registradas no docblock do controller: trial válido aceita agendamento (demo real do produto); suspenso/vencido/cancelado = 404 genérico; link público respeita disponibilidade dura (não encaixa); source = `customer` ("Auto-agendamento", enum já existente, sem migration).

### Correções de segurança (ver RELATORIO_SEGURANCA.md)
| Arquivo | Achado |
|---|---|
| `app/Http/Requests/StoreAppointmentRequest.php` | A1: exists escopado por tenant (IDOR de escrita) |
| `app/Http/Requests/UpdateAppointmentRequest.php` | A1 idem |
| `app/Http/Controllers/WhatsappController.php` | A2: assinatura X-Hub-Signature-256 no webhook |
| `app/Policies/BasePolicy.php` | A3: atalho do dono restrito ao próprio tenant |
| `tests/Feature/SecurityRegressionTest.php` | Regressões de A1/A2/A3 |

### Documentos
`CLAUDE.md` (mapa/convenções), `RELATORIO_SEGURANCA.md`, `RELATORIO_EFICIENCIA.md`, este resumo.

## Como aplicar (no repo, com Claude Code)
1. Copiar os arquivos preservando os caminhos (os 4 PATCHes substituem os arquivos atuais; diffe antes pra conferir que o original não mudou desde o snapshot).
2. Registrar as rotas novas em `routes/web.php`, junto dos outros requires:
   ```php
   require __DIR__.'/modules/public_booking.php';
   ```
3. (Opcional, recomendado) Adicionar ao `.env.example`:
   ```
   WHATSAPP_VERIFY_TOKEN=
   WHATSAPP_APP_SECRET=
   ```
4. `php artisan test`. Esperado: 289 antigos verdes + ~23 novos. Se algum antigo quebrar em A1/A3, é regressão desejada (o teste antigo dependia do comportamento inseguro); ajustar o teste antigo.
5. `./vendor/bin/pint --dirty` e commits pequenos:
   - `feat: link publico de agendamento (/agendar/{slug}) com rate limit e isolacao`
   - `fix(seguranca): escopa exists por tenant nos requests de agendamento`
   - `fix(seguranca): verifica assinatura do webhook do whatsapp`
   - `fix(seguranca): atalho do dono na BasePolicy nao cruza tenant`
   - `docs: CLAUDE.md + relatorios de seguranca e eficiencia`

## O que falta (próximos passos sugeridos, em ordem)
1. **B1 do relatório de segurança:** aplicar `subscription.active` (variante JSON) nas rotas de API; é a brecha de maior valor pendente e precisa da suíte rodando.
2. **E1 da eficiência:** busca no banco (pg_trgm) em vez de em PHP; é o maior ganho de escala.
3. E2/E3 (N+1 do dashboard e do pricer) com testes de contagem de queries.
4. E5 (paginação da auditoria), que já estava na sua lista.
5. Frontend da página pública `/agendar/{slug}` (fase 2, Vue, fora do escopo atual).
6. Grep geral por `exists:` sem escopo nos demais FormRequests (`StoreBarberRequest.user_id` é o próximo).

## Riscos conhecidos
- A correção A1 pode quebrar testes antigos que criavam agendamento com factory de outro tenant por engano (sinal de teste mal escrito, não da correção).
- `PublicBookingTest` assume os factories existentes (Tenant/Barber/Service/Customer) e schedules via `DB::table` cru; se a convenção da suíte for outra (helpers próprios), alinhar.
- O teste de rate limit depende de `CACHE_STORE=array` no phpunit.xml (já é o caso).
