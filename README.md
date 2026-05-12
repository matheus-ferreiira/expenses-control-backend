# Productivity Control — Backend API

API REST para aplicação pessoal de controle financeiro, tarefas, hábitos, metas e produtividade.

## Stack

| Tecnologia | Versão |
|-----------|--------|
| PHP | ^8.3 |
| Laravel | ^12.0 |
| PostgreSQL | 15+ |
| Sanctum | ^4.0 (token auth) |
| Socialite | ^5.0 (Google OAuth - futuro) |

## Pré-requisitos

- PHP 8.3+
- Composer
- PostgreSQL 15+
- Node.js 20+ (para assets)

## Instalação

```bash
# 1. Instalar dependências
composer install

# 2. Configurar ambiente
cp .env.example .env
php artisan key:generate

# 3. Configurar banco no .env
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=productivity_control
# DB_USERNAME=postgres
# DB_PASSWORD=

# 4. Criar banco e rodar migrations
php artisan migrate

# 5. Iniciar servidor de desenvolvimento
composer dev
```

## Estrutura de Domínios

```
app/Domains/
├── Auth/       Autenticação (register, login, forgot/reset password)
├── Tasks/      Tarefas com subtasks, labels, recorrência, arquivo
├── Habits/     Hábitos com streaks, heatmap, estatísticas
├── Finance/    Contas, cartões, transações, categorias, relatórios
├── Goals/      Metas com tracking de progresso automático
├── Calendar/   Agenda (preparado para Google Calendar)
├── Reports/    Dashboard consolidado e relatórios
└── Shared/     DTOs compartilhados
```

Cada domínio contém: `Controllers · Services · Actions · Models · DTOs · Requests · Resources · Policies · Enums`

## API

Base URL: `http://localhost:8000/api/v1`

### Autenticação

```
POST  /auth/register         Criar conta
POST  /auth/login            Login → retorna Bearer token
POST  /auth/logout           Logout (token atual)
POST  /auth/logout-all       Logout em todos os dispositivos
GET   /auth/me               Dados do usuário autenticado
POST  /auth/forgot-password  Enviar link de reset
POST  /auth/reset-password   Resetar senha
```

### Dashboard

```
GET   /dashboard             Dados consolidados (tasks + habits + finance + goals + calendar)
```

### Tarefas

```
GET    /tasks                Lista com filtros (status, priority, label, due_date, search)
POST   /tasks                Criar tarefa
GET    /tasks/{id}           Detalhes
PUT    /tasks/{id}           Atualizar
DELETE /tasks/{id}           Deletar (soft)
PATCH  /tasks/{id}/complete  Marcar como concluída
PATCH  /tasks/{id}/archive   Arquivar
PATCH  /tasks/{id}/unarchive Desarquivar
POST   /tasks/reorder        Reordenar (drag & drop)

POST   /tasks/{id}/subtasks         Criar subtask
PUT    /tasks/{id}/subtasks/{sid}   Atualizar subtask
DELETE /tasks/{id}/subtasks/{sid}   Deletar subtask

GET    /task-labels          Listar labels
POST   /task-labels          Criar label
PUT    /task-labels/{id}     Atualizar
DELETE /task-labels/{id}     Deletar
```

### Hábitos

```
GET    /habits               Lista paginada
POST   /habits               Criar hábito
GET    /habits/today         Hábitos do dia + status de conclusão
GET    /habits/{id}          Detalhes
PUT    /habits/{id}          Atualizar
DELETE /habits/{id}          Deletar (soft)
PATCH  /habits/{id}/archive  Arquivar
PATCH  /habits/{id}/unarchive Desarquivar
POST   /habits/{id}/log      Registrar conclusão do dia
DELETE /habits/{id}/log      Remover registro do dia
GET    /habits/{id}/stats    Estatísticas (streak, completion rate, etc.)
GET    /habits/{id}/heatmap  Heatmap de atividade (padrão 365 dias)
```

### Financeiro

```
GET  /finance/balance              Saldo consolidado de todas as contas

GET    /finance/accounts           Listar contas
POST   /finance/accounts           Criar conta
GET    /finance/accounts/{id}      Detalhes
PUT    /finance/accounts/{id}      Atualizar
DELETE /finance/accounts/{id}      Deletar

POST   /finance/accounts/{id}/cards Criar cartão vinculado à conta
GET    /finance/cards              Listar cartões
GET    /finance/cards/{id}         Detalhes
PUT    /finance/cards/{id}         Atualizar
DELETE /finance/cards/{id}         Deletar

GET    /finance/transactions       Lista com filtros avançados
POST   /finance/transactions       Criar transação (suporta parcelamento)
GET    /finance/transactions/{id}  Detalhes
PUT    /finance/transactions/{id}  Atualizar
DELETE /finance/transactions/{id}  Deletar

GET  /finance/categories           Listar categorias
POST /finance/categories           Criar categoria
PUT  /finance/categories/{id}      Atualizar
DELETE /finance/categories/{id}    Deletar

GET  /finance/reports/monthly      Resumo mensal (?year=&month=)
GET  /finance/reports/yearly       Resumo anual (?year=)
GET  /finance/reports/cashflow     Fluxo de caixa (?start_date=&end_date=)
```

### Metas

```
GET    /goals                Lista paginada
POST   /goals                Criar meta
GET    /goals/{id}           Detalhes
PUT    /goals/{id}           Atualizar
DELETE /goals/{id}           Deletar
PATCH  /goals/{id}/progress  Atualizar progresso atual
```

### Agenda

```
GET    /calendar             Eventos no período (?start_date=&end_date=)
POST   /calendar             Criar evento
GET    /calendar/upcoming    Próximos N dias (?days=7)
GET    /calendar/{id}        Detalhes
PUT    /calendar/{id}        Atualizar
DELETE /calendar/{id}        Deletar
```

### Relatórios

```
GET  /reports/weekly-productivity   Resumo semanal de produtividade
```

## Formato de Resposta

Todas as respostas seguem o envelope padrão:

```json
{
  "success": true,
  "data": {},
  "message": "Operation successful",
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 73
  }
}
```

Erros:
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

## Banco de Dados

Migrations em ordem de criação:

| # | Tabela | Descrição |
|---|--------|-----------|
| 000000 | users | UUID PK, locale, timezone, settings JSONB |
| 000002 | tasks | UUID PK, SoftDeletes, índices compostos |
| 000003 | task_labels | Labels de tarefas por usuário |
| 000004 | task_label_task | Pivot M2M tasks ↔ labels |
| 000005 | subtasks | Subtarefas com posição |
| 000006 | habits | Hábitos com frequência e cor |
| 000007 | habit_logs | Logs diários com unique(habit_id, date) |
| 000008 | transaction_categories | Categorias por usuário ou globais |
| 000009 | bank_accounts | Contas bancárias com saldo decimal(15,2) |
| 000010 | credit_cards | Cartões vinculados a contas |
| 000011 | transactions | Transações com suporte a parcelamento |
| 000012 | goals | Metas com progress tracking |
| 000013 | calendar_events | Eventos com source (manual/google/import) |

## Desenvolvimento

```bash
composer dev          # Inicia tudo: servidor, queue, logs, vite
composer test         # PHPUnit
./vendor/bin/pint     # Laravel Pint (formatação)
php artisan tinker    # REPL
```

## Roadmap

- [ ] Google OAuth (Socialite já instalado)
- [ ] Google Calendar sync
- [ ] Notificações (jobs + queues já configurados)
- [ ] WebSockets (broadcasting)
- [ ] App mobile (tokens Sanctum já funcionam)
- [ ] Analytics e relatórios avançados
