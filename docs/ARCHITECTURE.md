# Backend Architecture Guide

## Visão Geral

O backend segue uma arquitetura Domain-Driven Design (DDD) leve, organizada em domínios de negócio independentes. Cada domínio encapsula seus próprios modelos, lógica e interfaces HTTP.

## Estrutura de Domínios

```
app/Domains/
├── Auth/           Autenticação (e-mail/senha, Google), perfil e preferências
├── Finance/        Controle financeiro: contas, cartões/faturas, transações, orçamentos, metas, relatórios
├── Tasks/          Tarefas, listas, tags, subtarefas, recorrência
├── Habits/         Tracking de hábitos
├── Calendar/       Eventos e agenda
├── Notes/          Notas
├── Bookmarks/      Coleções de links
├── Prices/         Histórico de preços e patrimônio
├── Purchases/      Itens e sessões de compra
├── Reports/        Dashboard e relatórios de produtividade
└── Shared/         Código compartilhado entre domínios
```

O módulo principal é **Finance**. A tela Visão Geral do frontend é a referência de design do produto
e consome os relatórios e resumos calculados aqui (ver seção "Domínio Finance").

## Estrutura Interna de Cada Domínio

```
DomainName/
├── Controllers/    Camada HTTP — thin, sem lógica de negócio
├── Services/       Orquestração de casos de uso
├── Actions/        Operações atômicas e reutilizáveis
├── Models/         Entidades Eloquent com scopes e relationships
├── DTOs/           Objetos de transferência de dados (imutáveis)
├── Requests/       Validação de entrada HTTP
├── Resources/      Transformação de saída HTTP
├── Policies/       Autorização por recurso
└── Enums/          Tipos enumerados com helpers
```

## Fluxo de uma Requisição

```
Request HTTP
    ↓
Route (routes/api.php)
    ↓
Form Request (validação automática)
    ↓
Controller (autorização via Policy, monta DTO)
    ↓
Service (orquestra o caso de uso)
    ↓
Action(s) (lógica atômica)
    ↓
Model / Eloquent (persistência)
    ↓
Resource (transformação para API)
    ↓
Response JSON (envelope padronizado)
```

## Camadas em Detalhe

### Controller
**Responsabilidade:** Receber requisição HTTP, autorizar, delegar, retornar resposta.
**Regra:** Nunca contém lógica de negócio.

```php
class TaskController extends Controller
{
    public function __construct(
        private readonly TaskService $taskService,
    ) {}

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $this->authorize('create', Task::class);  // opcional
        $task = $this->taskService->create(
            $request->user(),
            TaskDTO::fromArray($request->validated())
        );
        return $this->created(new TaskResource($task), 'Task created');
    }
}
```

### Service
**Responsabilidade:** Orquestrar Actions para implementar casos de uso complexos. Gerenciar transações quando necessário.

```php
final class TaskService
{
    public function __construct(
        private readonly CreateTaskAction $createTask,
        private readonly NotifyUserAction $notifyUser, // futuro
    ) {}

    public function create(User $user, TaskDTO $dto): Task
    {
        $task = $this->createTask->execute($user, $dto);
        // $this->notifyUser->execute($user, $task); // futuro
        return $task;
    }
}
```

### Action
**Responsabilidade:** Uma operação específica e atômica. Pode ser reutilizada por múltiplos Services.

```php
final class CreateTaskAction
{
    public function execute(User $user, TaskDTO $dto): Task
    {
        return Task::create([
            'user_id' => $user->id,
            'title'   => $dto->title,
            // ...
        ]);
    }
}
```

### DTO (Data Transfer Object)
**Responsabilidade:** Transportar dados validados entre camadas de forma type-safe e imutável.

```php
final readonly class TaskDTO
{
    public function __construct(
        public string $title,
        public TaskPriority $priority = TaskPriority::Medium,
        public ?string $dueDate = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            title:    $data['title'],
            priority: TaskPriority::from($data['priority'] ?? 'medium'),
            dueDate:  $data['due_date'] ?? null,
        );
    }
}
```

### Model
**Responsabilidade:** Representar entidade do banco, definir relacionamentos e scopes.

```php
class Task extends Model
{
    use HasUuids, SoftDeletes;

    // Casts tipados
    protected $casts = [
        'priority' => TaskPriority::class,
        'due_date' => 'datetime',
    ];

    // Relacionamentos
    public function user(): BelongsTo { ... }
    public function subtasks(): HasMany { ... }

    // Scopes reutilizáveis
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
```

### Request (Form Request)
**Responsabilidade:** Validar entrada HTTP antes de chegar ao Controller.

```php
class StoreTaskRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title'    => ['required', 'string', 'max:500'],
            'priority' => ['nullable', new Enum(TaskPriority::class)],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
```

### Resource (API Resource)
**Responsabilidade:** Transformar Model em array JSON para a resposta da API.

```php
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'title'    => $this->title,
            'priority' => $this->priority?->value,
            'due_date' => $this->due_date?->toISOString(),
            'subtasks' => SubtaskResource::collection($this->whenLoaded('subtasks')),
        ];
    }
}
```

### Policy
**Responsabilidade:** Autorizar operações baseadas em ownership.

```php
class TaskPolicy
{
    public function update(User $user, Task $task): bool
    {
        return $user->id === $task->user_id;
    }
}
```

Registradas em `AppServiceProvider`:
```php
Gate::policy(Task::class, TaskPolicy::class);
```

### Enum
**Responsabilidade:** Representar tipos enumerados com comportamento.

```php
enum TaskStatus: string
{
    case Pending    = 'pending';
    case InProgress = 'in_progress';
    case Done       = 'done';

    public function isCompleted(): bool
    {
        return $this === self::Done;
    }
}
```

## Banco de Dados

### Convenções
- **PKs:** UUID via `$table->uuid('id')->primary()` + `HasUuids` no Model
- **FKs:** `$table->foreignUuid('user_id')->constrained()->cascadeOnDelete()`
- **Soft deletes:** Em todas as entidades principais
- **Valores monetários:** `decimal(15, 2)` em reais; inteiros `*_cents` nas tabelas de cartão e recuperação — nunca `float`
- **JSON:** `json` no MySQL
- **Índices:** Compostos nas colunas mais filtradas juntas

### Atualização de Saldo
`CreateTransactionAction`, `UpdateTransactionAction` e `ConfirmTransactionAction` ajustam o `balance` da
`BankAccount` (e da conta de destino em transferências) **só para transações confirmadas**. Previsões
(`status = pending`) não mexem no saldo até serem confirmadas. Compras no cartão nunca debitam conta:
entram na fatura e só o pagamento da fatura (`PayCardStatementAction`, que grava uma transação vinculada
ao cartão e ao mês da fatura) sai da conta.

## Domínio Finance

| Peça | Responsabilidade |
|---|---|
| `TransactionService` + Actions | Criar, editar, confirmar e excluir transações (séries fixas, parcelas, escopo `this_only` / `this_and_future` / `all`) |
| `RecurrenceSchedule` + `ExtendRecurringTransactionsAction` | Janela rolante das séries fixas (comando `finance:extend-recurrences`) |
| `CardCycle` | Ciclo da fatura (fechamento/vencimento) de uma data |
| `CardOverview` | Fatura a pagar, fatura aberta, próximas faturas e limite disponível real |
| `CardStatementSummary` + `PayCardStatementAction` | Detalhe e pagamento de fatura (`card_statements`) |
| `RealizeDueCardChargesAction` | Confirma compras do cartão na data delas (comando `finance:realize-card-charges`) |
| `FinancialEffects` | Visão econômica: parte própria, reembolso, despesa dividida, pagamento de fatura |
| `FinanceReportService` | Relatório mensal/anual, série, totais e transações por categoria, fluxo de caixa |
| `AccountBalanceHistory` | Saldo de uma conta em uma data e saldo previsto do fim do mês |
| `AccountMovements` | Entradas e saídas de caixa de cada conta no mês |
| `BalanceObservationService` | "Conferir saldo" (`account_balance_checkpoints`); a primeira conferência vira a abertura |
| `UserClock` | "Hoje" no fuso do usuário |
| `RecoveryImporter` / `RecoveryPlan` / `RecoveryRecordGuard` | Importação auditada e reversível de extratos |

Regras:
- **Reserva (Guardado):** contas `AccountType::Investment` ficam fora do saldo disponível e do previsto.
- **Saldo no fim do mês:** saldo atual + previstos do mês − faturas de cartão que vencem até lá.
- **Relatórios** usam a parte própria das despesas e não contam reembolso como receita.
- **Consultas não escrevem.** Manutenção de séries e compras de cartão fica nos comandos agendados
  (`routes/console.php`), que também rodam no boot do container para recuperar dias perdidos.
- **Registros importados** (`import_batch_id`) têm valor, data, conta e descrição travados.

## API Response Envelope

Todas as respostas usam o trait `ApiResponse`:

```php
// 200 OK
$this->success($data, 'mensagem')

// 201 Created
$this->created($data, 'mensagem')

// 204 No Content
$this->noContent()

// 200 OK com paginação
$this->paginatedSuccess(TaskResource::collection($tasks))

// 4xx/5xx
$this->error('mensagem', 404)
```

Envelope de sucesso:
```json
{
  "success": true,
  "data": { "id": "uuid", "title": "..." },
  "message": "Task created",
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 42
  }
}
```

## Autenticação

Sanctum com tokens (Bearer token no header `Authorization`).

```
POST /api/v1/auth/login
→ { "data": { "token": "1|abc...", "user": {...} } }

Requisições autenticadas:
Authorization: Bearer 1|abc...
```

## Tratamento de Exceções

Configurado em `bootstrap/app.php`:
- `AuthenticationException` → 401 JSON
- `ModelNotFoundException` → 404 JSON
- `AccessDeniedHttpException` → 403 JSON
- `NotFoundHttpException` → 404 JSON (rota não encontrada)
- `ValidationException` → 422 JSON via `BaseFormRequest`

## Extensibilidade

### Adicionando um Novo Domínio

1. Criar `app/Domains/NovoDominio/` com a estrutura padrão
2. Criar migration com UUID PK e listá-la em `database/release-approved-migrations.txt` para o deploy
3. Registrar Policy em `AppServiceProvider::boot()`
4. Adicionar rotas em `routes/api.php`
5. Testes de feature e documentação (`README.md`, `docs/API.md`, este arquivo) no mesmo commit

### Adicionando Notificações (Futuro)
- Criar `app/Domains/Notifications/`
- Jobs em `app/Jobs/`
- Queue driver: `database` (já configurado)

### Adicionando Google Calendar (Futuro)
- `CalendarEvent.source` enum já tem case `Google`
- `CalendarEvent.external_id` para armazenar ID do Google
- `laravel/socialite` já é usado no login com Google
