# VaultOS — Backend API

API REST do VaultOS: finanças pessoais (módulo principal), tarefas, hábitos, agenda, notas, bookmarks,
preços e compras. O frontend fica em [`../expenses-control-frontend`](../expenses-control-frontend/README.md).

## Stack

| Tecnologia | Versão |
|-----------|--------|
| PHP | ^8.3 (local: PHP 8.4 portátil em `../.runtime/php84`) |
| Laravel | ^12.0 |
| MySQL | 8 |
| Sanctum | ^4.0 (token auth) |
| Socialite | ^5.0 (login com Google) |

## Ambientes

| Arquivo | Banco | Uso |
|---|---|---|
| `.env` | MySQL local `expenses_control` | padrão (cópia de `.env.local`) |
| `.env.local` | MySQL local `expenses_control` | local explícito |
| `.env.production` | Railway | só com `--env=production`, com cuidado |

Os três são ignorados pelo Git. Detalhes em [`../README.md`](../README.md).

## Executar localmente

```bash
composer install
../.runtime/php84/php.exe artisan config:clear
../.runtime/php84/php.exe artisan migrate --env=local
../.runtime/php84/php.exe artisan serve --env=local --port=8001
```

API local: `http://localhost:8001/api/v1`. Produção: `https://vault-api.matheus-dev.com/api/v1`.

## Estrutura de Domínios

```
app/Domains/
├── Auth/       Autenticação (e-mail/senha, Google), perfil, senha, preferências
├── Finance/    Contas, cartões e faturas, transações, categorias, tags, orçamentos, metas, relatórios
├── Tasks/      Tarefas com subtarefas, listas, tags, labels, recorrência
├── Habits/     Hábitos com streaks, heatmap, estatísticas
├── Calendar/   Agenda
├── Notes/      Notas e tags
├── Bookmarks/  Coleções de links
├── Prices/     Histórico de preços, compras, vendas e patrimônio
├── Purchases/  Itens e sessões de compra
├── Reports/    Dashboard e produtividade
└── Shared/     DTOs compartilhados
```

Cada domínio contém (conforme precisa): `Controllers · Services · Actions · Models · DTOs · Requests · Resources · Policies · Enums`.
Arquitetura em [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md); endpoints em [`docs/API.md`](docs/API.md).

## Finanças em resumo

A tela **Visão Geral** do frontend (referência de design do produto) é montada com:

| Card da tela | Endpoint |
|---|---|
| Card do mês e categorias | `GET /finance/reports/monthly?year=&month=` |
| Saldo "Nas contas" / "No fim do mês" | `GET /finance/accounts` + `GET /finance/balance/historical?year=&month=` |
| A confirmar | `GET /finance/transactions?status=pending&end_date=…` + `PATCH /finance/transactions/{id}/confirm` / `confirm-batch` |
| Cartões | `GET /finance/cards/{id}/overview` |
| Transações de uma categoria | `GET /finance/reports/category-transactions` |
| Lista de transações | `GET /finance/transactions` (filtros e paginação) |

Regras de negócio principais (saldo, cartões, reservas, visão econômica) estão no `CLAUDE.md`.

## Formato de Resposta

```json
{
  "success": true,
  "data": {},
  "message": "Operation successful",
  "meta": { "current_page": 1, "last_page": 5, "per_page": 15, "total": 73 }
}
```

Erros:
```json
{ "success": false, "message": "Validation failed", "errors": { "email": ["The email field is required."] } }
```

## Banco de Dados

66 migrations em `database/migrations/`. Convenções: PK UUID, `SoftDeletes` nas entidades principais,
`decimal(15,2)` para valores em reais e inteiros `*_cents` nas tabelas de cartão/recuperação.

Principais tabelas financeiras: `bank_accounts`, `credit_cards`, `transactions`, `transaction_categories`,
`transaction_tags`, `budgets`, `budget_items`, `finance_goals`. A migration
`2026_10_13_000001_create_financial_recovery_tables.php` criou `card_statements` (faturas),
`account_balance_checkpoints` (conferências de saldo), `expense_allocations` (despesas divididas) e as
tabelas de importação auditada (`import_batches`, `import_sources`, `transaction_sources`, `import_changes`).

## Comandos

```bash
composer dev                                                     # servidor, fila, logs e vite juntos
../.runtime/php84/php.exe vendor/bin/phpunit -c phpunit.recovery.xml   # testes (banco vaultos_recovery_*)
./vendor/bin/pint                                                # formatação
php artisan tinker                                               # REPL
```

Rotinas agendadas (`routes/console.php`): `finance:extend-recurrences --prune` (03:00 UTC),
`finance:realize-card-charges` (03:05 UTC), `tasks:roll-recurrences` (03:10 UTC).

## Testes e deploy com segurança

- **Testes**: só rodam contra MySQL local. O `phpunit.xml` fixa `DB_HOST=127.0.0.1` e o `tests/TestCase.php` recusa qualquer outro host antes do `RefreshDatabase` apagar o banco.
- **Deploy**: o container roda `php artisan app:release-migrate` em vez de `migrate --force`. Uma migration pendente só é aplicada em produção se estiver listada em `database/release-approved-migrations.txt`.
- **Release com migration**: (1) faça backup completo de produção e confira a restauração; (2) liste o nome da migration no arquivo de aprovação, no mesmo commit; (3) publique e confira que ela rodou; (4) limpe a lista na release seguinte.
- **Rotinas diárias**: o agendador (`schedule:work`) roda no mesmo container. Ao subir, o deploy estende as recorrências e confirma as compras de cartão já vencidas.

## Documentação

Todo commit atualiza a documentação afetada (`CLAUDE.md`, este README, `docs/API.md`, `docs/ARCHITECTURE.md`).

## Roadmap

- [ ] Google Calendar sync
- [ ] Notificações (jobs + queues já configurados)
- [ ] Decidir se o cadastro público (`POST /auth/register`) continua aberto
