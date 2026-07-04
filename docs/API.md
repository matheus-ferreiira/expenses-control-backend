# API Reference

Base URL: `http://localhost:8000/api/v1`

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

### GET /finance/balance 🔒
Saldo consolidado de todas as contas.

**Response:**
```json
{
  "success": true,
  "data": {
    "total": 15420.50,
    "by_currency": { "BRL": 15420.50 },
    "accounts": [
      { "id": "uuid", "name": "Nubank", "balance": 5420.50 }
    ]
  }
}
```

---

### POST /finance/accounts 🔒
Criar conta bancária.

**Body:**
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

**Valores de `type`:** `checking` · `savings` · `investment` · `cash` · `wallet`

---

### POST /finance/transactions 🔒
Criar transação. Suporta parcelamento.

**Body (simples):**
```json
{
  "type": "expense",
  "amount": 150.00,
  "description": "Supermercado",
  "transaction_date": "2026-05-12",
  "account_id": "uuid",
  "category_id": "uuid"
}
```

**Body (parcelado):**
```json
{
  "type": "expense",
  "amount": 1200.00,
  "description": "iPhone",
  "transaction_date": "2026-05-12",
  "card_id": "uuid",
  "category_id": "uuid",
  "total_installments": 12
}
```

**Valores de `type`:** `income` · `expense` · `transfer`

---

### GET /finance/reports/monthly 🔒
Resumo financeiro mensal.

**Query:** `?year=2026&month=5`

**Response:**
```json
{
  "success": true,
  "data": {
    "year": 2026,
    "month": 5,
    "income": 8000.00,
    "expenses": 3200.00,
    "balance": 4800.00,
    "transactions_count": 42,
    "expenses_by_category": [
      { "category": "Alimentação", "total": 1200.00, "percentage": 37.5 }
    ]
  }
}
```

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
