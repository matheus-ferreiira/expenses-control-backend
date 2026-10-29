# API Reference

Base URL local: `http://localhost:8001/api/v1` · produção: `https://vault-api.matheus-dev.com/api/v1`

> Mantenha este arquivo em dia: todo commit que cria, remove ou muda uma rota ou um campo de resposta
> atualiza esta página no mesmo commit. A lista completa de rotas está em `routes/api.php`.

## Índice de rotas

🔒 = exige `Authorization: Bearer {token}`.

| Módulo | Rotas |
|---|---|
| Auth | `POST auth/register` · `POST auth/login` · `POST auth/forgot-password` · `POST auth/reset-password` (throttle 10/min) · `GET auth/google/redirect` · `GET auth/google/callback` · 🔒 `POST auth/logout` · `POST auth/logout-all` · `GET auth/me` · `PATCH auth/profile` · `PATCH auth/password` · `PATCH auth/settings` |
| Dashboard 🔒 | `GET dashboard` |
| Tarefas 🔒 | `tasks` (CRUD) · `POST tasks/reorder` · `PATCH tasks/{id}/complete\|archive\|unarchive` · `GET tasks/{id}/recurrence-history` · `tasks/{id}/subtasks` · `task-labels` · `task-lists` · `task-tags` |
| Hábitos 🔒 | `habits` (CRUD) · `GET habits/today` · `PATCH habits/{id}/archive\|unarchive` · `POST\|DELETE habits/{id}/log` · `GET habits/{id}/stats` · `GET habits/{id}/heatmap` |
| Finanças 🔒 | ver seção **Finance** |
| Agenda 🔒 | `calendar` (CRUD) · `GET calendar/upcoming` |
| Relatórios 🔒 | `GET reports/weekly-productivity` · `GET reports/habits-log-count` |
| Notas 🔒 | `notes` (CRUD) · `PATCH notes/{id}/pin\|favorite\|archive\|unarchive` · `note-tags` |
| Bookmarks 🔒 | `bookmarks/collections` (CRUD + reorder) · `bookmarks/collections/{id}/links` (+ reorder) · `bookmarks/links/{id}` · `PATCH bookmarks/links/{id}/favorite` |
| Preços 🔒 | `GET prices/dashboard\|patrimony\|compare` · `GET prices/products/{id}/price-history` · `prices/categories\|stores\|products\|price-records\|purchases\|sales` (CRUD) |
| Compras 🔒 | `purchases` (CRUD) · `shopping/sessions` (CRUD + finish/reopen) · `shopping/sessions/{id}/items` · `shopping/items/{id}` · `GET shopping/items/frequent` |

## Autenticação

A API usa **Bearer Token** via Laravel Sanctum.

Inclua o header em todas as rotas autenticadas:
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

---

## Auth

### POST /auth/register
Criar nova conta.

**Body:**
```json
{
  "name": "João Silva",
  "email": "joao@example.com",
  "password": "senha123",
  "password_confirmation": "senha123",
  "timezone": "America/Sao_Paulo",
  "locale": "pt_BR"
}
```

**Response 201:**
```json
{
  "success": true,
  "data": {
    "user": { "id": "uuid", "name": "João Silva", "email": "joao@example.com" },
    "token": "1|abc..."
  },
  "message": "Registration successful"
}
```

---

### POST /auth/login
Autenticar e obter token.

**Body:**
```json
{
  "email": "joao@example.com",
  "password": "senha123",
  "remember": false
}
```

**Response 200:**
```json
{
  "success": true,
  "data": {
    "user": { "id": "uuid", "name": "João Silva", ... },
    "token": "1|abc..."
  },
  "message": "Login successful"
}
```

---

### POST /auth/logout 🔒
Invalidar token atual.

**Response 200:** `{ "success": true, "message": "Logged out successfully" }`

---

### GET /auth/me 🔒
Dados do usuário autenticado.

**Response 200:**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "name": "João Silva",
    "email": "joao@example.com",
    "avatar": null,
    "locale": "pt_BR",
    "timezone": "America/Sao_Paulo",
    "email_verified_at": "2026-05-12T..."
  }
}
```

---

### POST /auth/forgot-password
Enviar e-mail de recuperação.

**Body:** `{ "email": "joao@example.com" }`

---

### POST /auth/reset-password
Redefinir senha com token do e-mail.

**Body:**
```json
{
  "token": "abc123...",
  "email": "joao@example.com",
  "password": "novasenha123",
  "password_confirmation": "novasenha123"
}
```

---

## Dashboard

### GET /dashboard 🔒
Dados consolidados para a tela inicial.

**Response 200:**
```json
{
  "success": true,
  "data": {
    "tasks": {
      "pending_count": 5,
      "due_today_count": 2,
      "overdue_count": 1,
      "due_today": [...]
    },
    "habits": {
      "total": 6,
      "completed_today": 4,
      "pending_today": 2,
      "completion_rate_today": 66.7
    },
    "finance": {
      "total_balance": 5420.50,
      "reserve_balance": 500.00,
      "month_reserved": 500.00,
      "month_income": 8000.00,
      "month_expenses": 3200.00,
      "month_balance": 4800.00,
      "month": 5,
      "year": 2026
    },
    "goals": {
      "active_count": 3,
      "near_deadline": 1,
      "recent": [...]
    },
    "calendar": {
      "upcoming_events": [...],
      "today_events_count": 2
    }
  }
}
```

---

## Tasks

### GET /tasks 🔒
Lista paginada de tarefas.

**Query Params:**
| Param | Tipo | Descrição |
|-------|------|-----------|
| `status` | string | `pending`, `in_progress`, `done`, `cancelled` |
| `priority` | string | `low`, `medium`, `high`, `urgent` |
| `label_id` | uuid | Filtrar por label |
| `search` | string | Busca por título |
| `due_date` | date | Filtrar por data |
| `archived` | boolean | Listar arquivadas |
| `sort_by` | string | `position`, `due_date`, `created_at`, `priority` |
| `sort_direction` | string | `asc`, `desc` |
| `per_page` | integer | Default: 15, Max: 100 |

**Response 200** com paginação:
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "title": "Estudar Laravel",
      "description": null,
      "priority": "high",
      "status": "pending",
      "due_date": "2026-05-15T00:00:00Z",
      "completed_at": null,
      "recurrence_type": "none",
      "position": 1,
      "is_archived": false,
      "labels": [{ "id": "uuid", "name": "Trabalho", "color": "#3B82F6" }],
      "subtasks": [],
      "subtasks_count": 0,
      "completed_subtasks_count": 0
    }
  ],
  "meta": { "current_page": 1, "last_page": 2, "per_page": 15, "total": 20 }
}
```

---

### POST /tasks 🔒
Criar nova tarefa.

**Body:**
```json
{
  "title": "Estudar Laravel",
  "description": "Capítulo 5 do livro",
  "priority": "high",
  "status": "pending",
  "due_date": "2026-05-15",
  "recurrence_type": "none",
  "label_ids": ["uuid1", "uuid2"]
}
```

**Valores de `priority`:** `low` · `medium` · `high` · `urgent`
**Valores de `status`:** `pending` · `in_progress` · `done` · `cancelled`
**Valores de `recurrence_type`:** `none` · `daily` · `weekly` · `monthly` · `yearly` · `weekdays` · `custom`

---

### PATCH /tasks/{id}/complete 🔒
Marcar tarefa como concluída (atualiza status e completed_at).

---

### POST /tasks/reorder 🔒
Reordenar tarefas (drag & drop).

**Body:** `{ "ids": ["uuid1", "uuid2", "uuid3"] }`

---

### POST /tasks/{id}/subtasks 🔒
Adicionar subtarefa.

**Body:** `{ "title": "Subtarefa 1", "position": 1 }`

---

## Habits

### GET /habits 🔒
Lista paginada de hábitos.

### GET /habits/today 🔒
Hábitos do dia com status de conclusão.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "name": "Beber 2L de água",
      "frequency_type": "daily",
      "color": "#10B981",
      "icon": "💧",
      "logs": [{ "completed_date": "2026-05-12", "notes": null }]
    }
  ]
}
```

### POST /habits/{id}/log 🔒
Registrar conclusão do hábito.

**Body:** `{ "completed_date": "2026-05-12", "notes": "Feito às 8h" }`

### DELETE /habits/{id}/log 🔒
Remover registro (desmarcar).

**Body:** `{ "date": "2026-05-12" }`

### GET /habits/{id}/stats 🔒
Estatísticas do hábito.

**Response:**
```json
{
  "success": true,
  "data": {
    "habit": { "id": "uuid", "name": "..." },
    "stats": {
      "current_streak": 7,
      "longest_streak": 21,
      "completion_rate_30d": 86.7,
      "completion_rate_7d": 100.0,
      "completed_today": true,
      "completed_this_week": 5,
      "completed_this_month": 26,
      "total_completed": 142
    }
  }
}
```

### GET /habits/{id}/heatmap 🔒
Mapa de calor de atividade.

**Query:** `?days=365` (padrão: 365)

**Response:**
```json
{
  "success": true,
  "data": {
    "2026-01-01": false,
    "2026-01-02": true,
    "2026-01-03": true,
    ...
  }
}
```

---

## Finance

Todas as rotas abaixo ficam em `/finance` e exigem token 🔒. Valores com sufixo `_cents` são inteiros em
centavos; os demais são reais (`decimal`). "Hoje" é sempre o dia no fuso do usuário.

### Rotas

| Grupo | Rotas |
|---|---|
| Saldo | `GET balance` · `GET balance/historical?year=&month=` |
| Contas | `accounts` (CRUD) · `GET accounts/movements?year=&month=` · `GET accounts/{id}/balance-at?date=` · `GET\|POST accounts/{id}/balance-observations` |
| Cartões | `GET cards` · `POST cards` · `POST accounts/{id}/cards` · `GET\|PUT\|PATCH\|DELETE cards/{id}` · `GET cards/{id}/overview` · `GET cards/{id}/statement?cycle=` · `GET cards/{id}/statement-payment` · `POST cards/{id}/pay-statement` |
| Transações | `transactions` (CRUD) · `PATCH transactions/{id}/confirm` · `PATCH transactions/confirm-batch` |
| Categorias / tags | `GET\|POST categories` · `PUT\|DELETE categories/{id}` · `GET\|POST tags` · `PUT\|DELETE tags/{id}` |
| Relatórios | `GET reports/monthly` · `reports/yearly` · `reports/cashflow` · `reports/series` · `reports/categories` · `reports/category-transactions` · `reports/balance` |
| Orçamentos | `GET budgets` · `GET budgets/previous` · `POST budgets` · `PUT\|DELETE budgets/{id}` |
| Metas | `GET goals` · `POST goals` · `PUT goals/{id}` · `POST goals/{id}/complete` · `DELETE goals/{id}` |

### Endpoints da tela Visão Geral

A Visão Geral do frontend (referência de design do produto) é montada com estes endpoints:

| Card | Endpoint |
|---|---|
| Card do mês, categorias | `GET reports/monthly` |
| Saldo "Nas contas" | `GET accounts` (soma das contas ativas que não são reserva) |
| Saldo "No fim do mês" | `GET balance/historical` |
| A confirmar | `GET transactions?status=pending&end_date=…` · `PATCH transactions/{id}/confirm` · `PATCH transactions/confirm-batch` |
| Cartões | `GET cards/{id}/overview` |
| Transações de uma categoria | `GET reports/category-transactions` |
| Lista de transações | `GET transactions` |

---

### GET /finance/balance 🔒
Saldo consolidado das contas ativas. `total` não inclui as contas de reserva (tipo `investment`);
elas somam em `reserve`.

```json
{
  "success": true,
  "data": {
    "total": 2540.04,
    "reserve": 500.00,
    "by_currency": { "BRL": 2540.04 },
    "accounts": [
      { "id": "uuid", "name": "Nubank", "balance": 540.04, "is_reserve": false },
      { "id": "uuid", "name": "Caixinha Nubank", "balance": 500.00, "is_reserve": true }
    ]
  }
}
```

---

### GET /finance/balance/historical 🔒
Saldo no fim do mês pedido. **Query:** `?year=2026&month=10` (padrão: mês atual).

```json
{
  "success": true,
  "data": {
    "balance": 2540.04,
    "projected_balance": 1830.20,
    "card_due": 722.21,
    "card_statements": [
      { "card_id": "uuid", "card_name": "Nubank", "cycle": "2026-11", "due_on": "2026-11-20", "remaining_cents": 72221 }
    ],
    "unverified_accounts": ["uuid"],
    "reserve_balance": 500.00,
    "projected_reserve_balance": 500.00
  }
}
```

`projected_balance` = saldo + previstos do mês − faturas de cartão que vencem até o fim do mês.
`unverified_accounts` lista contas somadas pelo saldo registrado, ainda sem saldo inicial conferido.

---

### POST /finance/accounts 🔒
Criar conta.

```json
{
  "name": "Nubank",
  "type": "checking",
  "bank_name": "Nubank",
  "balance": 1000.00,
  "currency": "BRL",
  "color": "#8B5CF6"
}
```

**Valores de `type`:** `checking` · `savings` · `investment` (reserva / "Guardado") · `cash` · `wallet`

### PUT/PATCH /finance/accounts/{id} 🔒
Atualização parcial: só os campos enviados mudam. Arquivar = `{ "is_active": false }`, desarquivar =
`{ "is_active": true }`. O saldo não muda por aqui (só por transações ou "Conferir saldo").

### POST /finance/accounts/{id}/balance-observations 🔒
"Conferir saldo". **Body:** `{ "date": "2026-10-19", "balance": "540.04", "evidence": "app do banco" }`.
Resposta: `observed_cents`, `calculated_cents`, `difference_cents`, `opening_created` (a primeira conferência
de uma conta sem abertura conferida vira a abertura).

### GET /finance/accounts/movements 🔒
Entradas e saídas de caixa de cada conta no mês: `[{ "account_id": "uuid", "in": 5000.00, "out": 3120.55 }]`.

---

### GET /finance/transactions 🔒
Lista paginada.

| Param | Descrição |
|---|---|
| `type` | `income` · `expense` · `transfer` |
| `status` | `confirmed` · `pending` |
| `account_id`, `card_id`, `category_id` | filtros por UUID |
| `month` + `year`, ou `start_date` + `end_date` | período |
| `search` | busca na descrição |
| `is_recurring` | `1` / `0` (fixas) |
| `sort_by`, `sort_direction` | ordenação |
| `per_page` | até 500 |

### POST /finance/transactions 🔒
Criar transação. Uma despesa vai para **uma conta ou um cartão**, nunca os dois.

```json
{
  "type": "expense",
  "amount": 150.00,
  "description": "Supermercado",
  "transaction_date": "2026-10-12",
  "account_id": "uuid",
  "category_id": "uuid"
}
```

Parcelada no cartão: troque `account_id` por `card_id` e informe `total_installments`.
Data futura cria uma previsão (`status: pending`), que não mexe no saldo até ser confirmada.

### PUT/DELETE /finance/transactions/{id} 🔒
Em série fixa, `scope` (padrão `this_only`): na edição `this_only` · `this_and_future` · `all`
(= só as ocorrências futuras não pagas); na exclusão `this_only` · `this_and_future` (o histórico pago fica).
Registros importados de extrato aceitam só categoria, tags e notas.

### PATCH /finance/transactions/{id}/confirm 🔒
"Paguei / Recebi": confirma a previsão com o valor e a data reais (data não pode ser futura).

### PATCH /finance/transactions/confirm-batch 🔒
**Body:** `{ "ids": ["uuid", "uuid"] }` (1 a 100) — confirma várias previsões já vencidas. Ids de outro usuário são ignorados.

---

### GET /finance/cards/{id}/overview 🔒
Resumo real do cartão.

```json
{
  "success": true,
  "data": {
    "card_id": "uuid",
    "today": "2026-10-29",
    "open_cycle": "2026-12",
    "to_pay": [
      { "cycle": "2026-11", "start_on": "2026-10-13", "closing_on": "2026-11-12", "due_on": "2026-11-20",
        "status": "closed", "charges_cents": 72221, "paid_cents": 0, "remaining_cents": 72221 }
    ],
    "open": { "cycle": "2026-12", "status": "open", "charges_cents": 18000, "...": "..." },
    "upcoming": [ { "cycle": "2027-01", "charges_cents": 10757, "...": "..." } ],
    "upcoming_total_cents": 10757,
    "limit_cents": 300000,
    "used_cents": 100978,
    "available_cents": 199022
  }
}
```

**`status` da fatura:** `future` · `open` · `closed` · `partial` · `overdue` · `paid` · `empty` · `historical`.
O limite usado inclui faturas a pagar, a aberta e parcelas futuras (previsões de assinatura não ocupam limite).

### POST /finance/cards/{id}/pay-statement 🔒
Paga uma fatura a partir de uma conta (gera a transação de pagamento, que não conta como despesa nos relatórios).

---

### GET /finance/reports/monthly 🔒
Resumo do mês. **Query:** `?year=2026&month=10`. Despesas pela parte própria; reembolso não conta como receita.

```json
{
  "success": true,
  "data": {
    "year": 2026,
    "month": 10,
    "income": 5000.00,
    "expenses": 3200.00,
    "saved": 500.00,
    "reserved": 500.00,
    "balance": 1800.00,
    "pending_income": 2500.00,
    "pending_expenses": 410.00,
    "pending_saved": 0,
    "transactions_count": 42,
    "largest_expense": { "id": "uuid", "description": "Aluguel", "amount": 1200.00, "transaction_date": "2026-10-05", "category": "Moradia" },
    "expenses_by_category": [
      { "category_id": "uuid", "category": "Moradia", "color": "#5CA8F5", "total": 1393.05, "count": 3, "percentage": 43.5 }
    ],
    "income_by_category": [
      { "category_id": "uuid", "category": "Salário", "color": "#34d399", "total": 5000.00, "count": 2, "percentage": 100 }
    ]
  }
}
```

- `saved` = aportes em metas + transferências líquidas para contas de reserva (poupança, não gasto); `reserved` = só a parte das reservas.
- `pending_*` = previsões do mês ainda não confirmadas.

### GET /finance/reports/category-transactions 🔒
Transações de uma categoria no mês (paginado). **Query:** `year`, `month`, `type` (`income`/`expense`), `category_id` (vazio = sem categoria), `page`.

### GET /finance/reports/series 🔒
Receitas e despesas por período. **Query:** `start_date`, `end_date`, `group` (`day`/`month`).
Resposta: `[{ "period": "2026-10", "income": 5000.00, "expenses": 3200.00 }]`.

### GET /finance/reports/categories 🔒
Gasto confirmado por categoria entre duas datas. **Query:** `start_date`, `end_date`.

---

## Calendar

### GET /calendar 🔒
Eventos no período.

**Query:** `?start_date=2026-05-01&end_date=2026-05-31`

### GET /calendar/upcoming 🔒
Próximos eventos.

**Query:** `?days=7`

---

## Erros Comuns

| Status | Descrição |
|--------|-----------|
| 401 | Token ausente, expirado ou inválido |
| 403 | Sem permissão para o recurso |
| 404 | Recurso não encontrado |
| 422 | Falha de validação — `errors` contém detalhes |
| 500 | Erro interno |

### Exemplo de erro 422:
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  }
}
```
