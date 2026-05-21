# CLAUDE.md — Backend (Laravel 12)

## Stack
- **PHP 8.3** | **Laravel 12** | **MySQL 8.0** | **Sanctum** (tokens)
- Testes: PHPUnit | Linting: Laravel Pint | Logs: Pail

## Arquitetura

```
app/
├── Domains/
│   ├── Auth/           login, register, forgot/reset password
│   ├── Tasks/          tasks, subtasks, labels, recurrence
│   ├── Habits/         habits, logs, streaks, heatmap
│   ├── Finance/        bank accounts, credit cards, transactions, categories
│   ├── Goals/          goals with progress tracking
│   ├── Calendar/       events (Google Calendar ready)
│   ├── Reports/        dashboard, weekly productivity
│   └── Shared/         DTOs compartilhados
├── Models/User.php     UUID + SoftDeletes + locale/timezone/settings
├── Traits/ApiResponse  success() created() noContent() paginatedSuccess() error()
└── Providers/          AppServiceProvider — policies registradas manualmente
```

Cada domínio segue a estrutura:
```
DomainName/
├── Controllers/    Thin — apenas HTTP in/out, delega ao Service
├── Services/       Orquestra Actions para operações complexas
├── Actions/        Uma responsabilidade, reutilizável
├── Models/         Eloquent + scopes + relationships
├── DTOs/           final readonly class, fromArray() factory
├── Requests/       Extends BaseFormRequest, rules() method
├── Resources/      Extends JsonResource, toArray()
├── Policies/       view/update/delete — checam user_id ownership
└── Enums/          Backed string enums com label() helper
```

## Padrões Obrigatórios

### Response Envelope
Todas as respostas seguem este formato:
```json
{ "success": true, "data": {}, "message": "", "meta": {} }
{ "success": false, "message": "", "errors": {} }
```

Usar sempre os helpers do `ApiResponse` trait:
- `$this->success($data, $message)` — 200
- `$this->created($data, $message)` — 201
- `$this->noContent()` — 204
- `$this->paginatedSuccess($collection)` — 200 com meta de paginação
- `$this->error($message, $code)` — 4xx/5xx

### Models
- UUID via `HasUuids` em todas entidades principais
- `SoftDeletes` em todas entidades principais
- Scopes no model: `scopeForUser()`, `scopeActive()` etc.
- Casts tipados: enums, decimal, boolean, array, datetime

### Controllers
- **Thin controllers** — apenas validar, autorizar, chamar service, retornar
- Injeção via construtor (`private readonly ServiceName $service`)
- `$this->authorize('action', $model)` para policies
- Nunca lógica de negócio no controller

### Services
- Orquestram Actions para casos de uso complexos
- Retornam Models ou Collections, nunca arrays brutos de DB
- Construtores com Actions injetadas

### DTOs
```php
final readonly class ExampleDTO
{
    public function __construct(
        public string $field,
        public ?string $optional = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(field: $data['field'], ...);
    }
}
```

### Requests (Form Requests)
- Sempre extends `BaseFormRequest`
- `authorize()` retorna `true` (autorização nas Policies)
- Regras com `'sometimes'` para updates parciais

### Migrations
- `$table->uuid('id')->primary()` — nunca auto-increment em entidades principais
- `$table->foreignUuid('user_id')->constrained()->cascadeOnDelete()`
- Índices compostos em colunas frequentemente filtradas juntas
- `decimal(15, 2)` para valores monetários — nunca float

### Policies
Registradas em `AppServiceProvider::boot()`. Nunca depender de auto-discovery (paths não-padrão).

## Comandos de Desenvolvimento

```bash
# Desenvolvimento
composer dev

# Database
php artisan migrate
php artisan migrate:fresh --seed
php artisan migrate:rollback

# Testes
php artisan test
php artisan test --filter=NomeDoTeste

# Qualidade
./vendor/bin/pint          # formata
./vendor/bin/pint --test   # verifica sem alterar

# Tinker
php artisan tinker
```

## Rotas API — `/api/v1/`

Todas as rotas autenticadas usam `middleware('auth:sanctum')`.

| Módulo | Prefix | Auth |
|--------|--------|------|
| Auth | `/api/v1/auth` | público: register, login; privado: logout, me |
| Dashboard | `/api/v1/dashboard` | ✅ |
| Tasks | `/api/v1/tasks` | ✅ |
| Habits | `/api/v1/habits` | ✅ |
| Finance | `/api/v1/finance` | ✅ |
| Goals | `/api/v1/goals` | ✅ |
| Calendar | `/api/v1/calendar` | ✅ |
| Reports | `/api/v1/reports` | ✅ |

## Adicionando um Novo Domínio

1. Criar estrutura em `app/Domains/NovoDominio/`
2. Criar migration com UUID PK e índices
3. Registrar Policy em `AppServiceProvider`
4. Adicionar rotas em `routes/api.php` dentro do grupo `v1 + auth:sanctum`
5. Criar orchestrator em `.tasks/`

## TDD — Regras para Novas Features

**Toda nova feature de backend DEVE incluir testes de regressão no mesmo sprint/commit.**

### Fluxo obrigatório

1. Implementar a feature (migration, model, action, service, controller, resource)
2. Criar factory para o novo model em `database/factories/` — padrão:
   ```php
   class NewModelFactory extends Factory
   {
       protected $model = NewModel::class;
       public function definition(): array { ... }
   }
   ```
3. Adicionar `protected static function newFactory(): NewModelFactory` no model (necessário para DDD — Laravel não encontra factories em `app/Domains/*/Models/` automaticamente)
4. Criar `tests/Feature/{Domain}/NewModelTest.php` cobrindo:
   - Happy path (CRUD básico funciona)
   - User isolation (usuário só vê seus próprios dados)
   - Authorization (usuário não acessa dados de outro — assertForbidden 403)
   - Validação de input (campos inválidos → assertUnprocessable 422)
   - Qualquer lógica de negócio crítica da feature
5. Rodar os testes antes do commit: `php artisan test --filter=NewModelTest`

### Estrutura dos testes

```php
namespace Tests\Feature\{Domain};

use App\Domains\{Domain}\Models\{Model};
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class {Model}Test extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_{model}(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/{endpoint}', [...])
            ->assertCreated();
    }

    public function test_user_cannot_access_another_users_{model}(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $model = {Model}::factory()->create(['user_id' => $other->id]);
        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/{endpoint}/{$model->id}")
            ->assertForbidden();
    }
}
```

### Cobertura mínima por feature

| Cenário | Assertion esperada |
|---------|-------------------|
| Criar recurso | `assertCreated()` + `assertDatabaseHas` |
| Listar recursos (isolação) | `meta.total` correto |
| Acessar recurso alheio | `assertForbidden()` (403) |
| Input inválido | `assertUnprocessable()` (422) |
| Lógica crítica de negócio | assertar o estado no banco |

### Banco de teste

- Conexão: MySQL `productivy_test` (configurado em `phpunit.xml`)
- Usar `RefreshDatabase` em todos os tests de feature
- Nunca usar SQLite (driver não disponível neste ambiente)
