# Backend Architecture Guide

## Visão Geral

O backend segue uma arquitetura Domain-Driven Design (DDD) leve, organizada em domínios de negócio independentes. Cada domínio encapsula seus próprios modelos, lógica e interfaces HTTP.

## Estrutura de Domínios

```
app/Domains/
├── Auth/           Autenticação e gerenciamento de sessão
├── Tasks/          Gerenciamento de tarefas
├── Habits/         Tracking de hábitos
├── Finance/        Controle financeiro
├── Goals/          Metas pessoais
├── Calendar/       Eventos e agenda
├── Reports/        Dashboard e relatórios consolidados
└── Shared/         Código compartilhado entre domínios
```

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
- **Valores monetários:** `decimal(15, 2)` — nunca `float`
- **JSON:** `jsonb` no PostgreSQL (melhor performance)
- **Índices:** Compostos nas colunas mais filtradas juntas

### Atualização de Saldo
`CreateTransactionAction` e `UpdateTransactionAction` atualizam automaticamente o `balance` da `BankAccount` após criar/editar/deletar transações.

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
2. Criar migration com UUID PK
3. Registrar Policy em `AppServiceProvider::boot()`
4. Adicionar rotas em `routes/api.php`

### Adicionando Notificações (Futuro)
- Criar `app/Domains/Notifications/`
- Jobs em `app/Jobs/`
- Queue driver: `database` (já configurado)

### Adicionando Google Calendar (Futuro)
- `CalendarEvent.source` enum já tem case `Google`
- `CalendarEvent.external_id` para armazenar ID do Google
- `laravel/socialite` já instalado para OAuth
