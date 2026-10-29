# CLAUDE.md — Backend (VaultOS, Laravel 12)

API REST do VaultOS. O frontend fica em `../expenses-control-frontend`.

## Regra de documentação (obrigatória)

**Todo commit atualiza a documentação que ficou desatualizada por ele, no mesmo commit.**
Antes de commitar, confira:

- `CLAUDE.md` (este arquivo) — arquitetura, padrões, regras de segurança
- `README.md` — instalação, domínios, rotas, deploy
- `docs/API.md` — endpoints e formatos de resposta (sempre que uma rota ou um campo mudar)
- `docs/ARCHITECTURE.md` — camadas e regras de negócio
- `tools/recovery/README.md` — quando mexer nas ferramentas de recuperação
- `../README.md` e `../CLAUDE.md` (pasta principal, fora do Git) — quando mudar algo do workspace
- Se o commit muda o que uma tela mostra: docs do frontend (`../expenses-control-frontend/CLAUDE.md`, `docs/`)

**Referência de design do produto:** a tela Visão Geral de Finanças do frontend
(`../expenses-control-frontend/docs/design-system.md`). Ao criar endpoints para telas, entregue os números
prontos (totais, saldos, categorias) como a Visão Geral consome: a tela não soma listas paginadas.

## Stack
- **PHP ^8.3** (localmente roda com o PHP 8.4 portátil em `../.runtime/php84`) | **Laravel 12** | **MySQL 8** | **Sanctum** (tokens)
- **Socialite** (login com Google) | Testes: PHPUnit | Lint: Laravel Pint | Logs: Pail

## Segurança: produção × local

- `.env` = `.env.local` → MySQL local. Produção **só** via `.env.production` / `--env=production`.
- Testes nunca tocam produção: `phpunit.xml` fixa `DB_HOST=127.0.0.1` e `tests/TestCase.php` recusa
  qualquer host não local antes do `RefreshDatabase`.
- Caminho de teste preferido nesta máquina: suíte `phpunit.recovery.xml` com banco `vaultos_recovery_*`
  (`RECOVERY_DATABASE`, `RECOVERY_LOCAL_PASSWORD`; o bootstrap força 127.0.0.1 e não lê `.env`).
- Alteração de dados de produção só com autorização explícita do usuário e backup verificado antes.

## Arquitetura

```
app/
├── Domains/
│   ├── Auth/        login, register, forgot/reset, perfil, senha, settings, Google OAuth
│   ├── Finance/     contas, cartões/faturas, transações, categorias, tags, orçamentos, metas, relatórios, recuperação
│   ├── Tasks/       tarefas, subtarefas, listas, tags, labels, recorrência
│   ├── Habits/      hábitos, logs, streaks, heatmap
│   ├── Calendar/    eventos
│   ├── Notes/       notas e tags de notas
│   ├── Bookmarks/   coleções e links
│   ├── Prices/      categorias, lojas, produtos, registros de preço, compras, vendas, patrimônio
│   ├── Purchases/   itens de compra e sessões de compra (shopping)
│   ├── Reports/     dashboard e produtividade
│   └── Shared/      DTOs compartilhados
├── Console/Commands/  rotinas agendadas, release-migrate, recuperação financeira
├── Models/User.php    UUID + SoftDeletes + locale/timezone/settings
├── Traits/ApiResponse success() created() noContent() paginatedSuccess() error()
└── Providers/         AppServiceProvider — policies registradas manualmente
```

Cada domínio segue (nem todo domínio tem todas as pastas):
```
DomainName/
├── Controllers/    Thin — apenas HTTP in/out, delega ao Service
├── Services/       Casos de uso e cálculos (ex.: FinanceReportService, CardOverview)
├── Actions/        Uma responsabilidade, reutilizável (ex.: CreateTransactionAction)
├── Models/         Eloquent + scopes + relationships
├── DTOs/           final readonly class, fromArray() factory
├── Requests/       Extends BaseFormRequest, rules()
├── Resources/      Extends JsonResource, toArray()
├── Policies/       view/update/delete — checam user_id
└── Enums/          Backed string enums com label()
```

### Domínio Finance — regras que importam

- **Transação:** `type` income/expense/transfer, `status` confirmed/pending. Só confirmada mexe no saldo
  da conta. Séries fixas usam `recurrence_group_id` + `recurrence_config`; parcelas, `installment_group_id`.
  Escopos de edição/exclusão de série: `this_only`, `this_and_future`, `all` (= só as futuras não pagas).
- **Cartão:** compra no cartão não debita conta; tem `card_id`, `statement_month`, `statement_due_on`.
  `CardCycle` calcula ciclos; `CardOverview` devolve fatura a pagar, aberta, próximas e limite real.
  Compras do cartão viram gasto na data (`finance:realize-card-charges`).
- **Visão econômica:** relatórios usam a parte própria (`economic_kind`, `economic_*_cents`): reembolso
  não é receita, despesa dividida conta só a sua parte, pagamento de fatura não é despesa.
- **Reserva:** conta `AccountType::Investment` é "Guardado" (caixinha): fica fora do saldo disponível e do
  previsto; `saved`/`reserved` no relatório = transferências líquidas para reservas + aportes em metas.
- **Conferência de saldo:** `balance-observations` (tabela `account_balance_checkpoints`); a primeira conferência de uma conta sem saldo inicial
  conferido vira a abertura dela. `AccountBalanceHistory` reconstrói saldos por data.
- **Registros importados** (`import_batch_id`) ficam travados em valor/data/conta/descrição; categoria,
  tags e notas podem mudar.
- **Datas "hoje"** sempre pelo fuso do usuário (`UserClock`), nunca `now()` cru.
- Consultas financeiras não escrevem dados; a manutenção fica nos comandos agendados.

## Padrões Obrigatórios

### Response Envelope
```json
{ "success": true, "data": {}, "message": "", "meta": {} }
{ "success": false, "message": "", "errors": {} }
```
Helpers do trait `ApiResponse`: `success()` 200 · `created()` 201 · `noContent()` 204 ·
`paginatedSuccess()` 200 com meta · `error($message, $code)` 4xx/5xx.

### Models
- UUID via `HasUuids` e `SoftDeletes` nas entidades principais
- Scopes no model: `scopeForUser()`, `scopeActive()` etc.
- Casts tipados: enums, decimal, boolean, array, datetime

### Controllers
- **Thin** — validar, autorizar, chamar service, retornar
- Injeção via construtor (`private readonly ServiceName $service`)
- `$this->authorize('action', $model)` para policies

### Services
- Orquestram Actions; retornam Models, Collections ou arrays de relatório já calculados
- Transações de banco (`DB::transaction`) quando o caso de uso grava em mais de uma tabela

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

### Requests
- Sempre extends `BaseFormRequest`; `authorize()` retorna `true` (autorização nas Policies)
- `'sometimes'` para updates parciais

### Migrations
- `$table->uuid('id')->primary()`; `$table->foreignUuid('user_id')->constrained()->cascadeOnDelete()`
- Índices compostos nas colunas filtradas juntas
- `decimal(15, 2)` para valores em reais; colunas `*_cents` (inteiro) nas tabelas novas de cartão/recuperação — nunca float
- **Toda migration nova precisa ir para `database/release-approved-migrations.txt`** para rodar em produção (ver Deploy)

### Policies
Registradas em `AppServiceProvider::boot()`. Nunca depender de auto-discovery (paths não-padrão).

## Comandos

```bash
# Local (a partir de expenses-control-backend)
../.runtime/php84/php.exe artisan serve --env=local --port=8001
../.runtime/php84/php.exe artisan migrate --env=local

# Testes (banco local isolado)
../.runtime/php84/php.exe vendor/bin/phpunit -c phpunit.recovery.xml [arquivo]
php artisan test --filter=NomeDoTeste        # phpunit.xml: banco productivy_test em 127.0.0.1

# Qualidade
./vendor/bin/pint          # formata
./vendor/bin/pint --test   # verifica sem alterar
```

### Comandos artisan do projeto

| Comando | O que faz |
|---|---|
| `finance:extend-recurrences [--prune]` | Mantém as séries fixas na janela rolante (agendado 03:00 UTC) |
| `finance:realize-card-charges` | Confirma compras/parcelas/assinaturas do cartão cuja data chegou (03:05 UTC) |
| `tasks:roll-recurrences` | Materializa a ocorrência de hoje das tarefas recorrentes (03:10 UTC) |
| `app:release-migrate` | No deploy, roda só as migrations aprovadas |
| `finance:merge-duplicate-categories [--dry-run]` | Funde categorias duplicadas |
| `purchases:prune-orphan-items [--dry-run]` | Remove itens de compra órfãos |
| `finance:recover` / `finance:rollback-recovery` | Importação e reversão auditadas (ver `tools/recovery/README.md`) |

## Deploy (Railway, `Dockerfile`)

O container roda: `optimize` → `app:release-migrate` → catch-up (`finance:extend-recurrences`,
`finance:realize-card-charges`) → `schedule:work` em segundo plano → `artisan serve`.
O agendador roda no próprio container (não há cron separado).

Release com migration: (1) backup completo de produção e restauração conferida; (2) listar a migration em
`database/release-approved-migrations.txt` no mesmo commit; (3) publicar e conferir que rodou; (4) limpar a
lista na release seguinte.

## Rotas API — `/api/v1/`

Rotas autenticadas usam `auth:sanctum`. Lista completa em `docs/API.md`.

| Módulo | Prefixo |
|--------|---------|
| Auth | `/auth` (login, forgot/reset e Google públicos; register fechado por padrão; logout, me, profile, password, settings privados) |
| Dashboard | `/dashboard` |
| Finanças | `/finance` (accounts, cards, transactions, categories, tags, reports, budgets, goals, balance) |
| Tarefas | `/tasks`, `/task-labels`, `/task-lists`, `/task-tags` |
| Hábitos | `/habits` |
| Agenda | `/calendar` |
| Relatórios | `/reports` |
| Notas | `/notes`, `/note-tags` |
| Bookmarks | `/bookmarks` |
| Preços | `/prices` |
| Compras | `/purchases`, `/shopping` |

## Adicionando um Novo Domínio

1. Criar estrutura em `app/Domains/NovoDominio/`
2. Criar migration com UUID PK e índices (e aprová-la para o deploy)
3. Registrar Policy em `AppServiceProvider`
4. Adicionar rotas em `routes/api.php` dentro do grupo `v1 + auth:sanctum`
5. Testes de feature (ver abaixo) e documentação (`README.md`, `docs/API.md`)

## TDD — Regras para Novas Features

**Toda nova feature de backend inclui testes de regressão no mesmo commit.**

1. Implementar a feature (migration, model, action, service, controller, resource)
2. Criar factory em `database/factories/` e `protected static function newFactory()` no model
   (Laravel não encontra factories de `app/Domains/*/Models/` sozinho)
3. Criar `tests/Feature/{Domain}/NomeTest.php` cobrindo:
   - Happy path
   - Isolamento entre usuários
   - Autorização (recurso de outro usuário → 403)
   - Validação (input inválido → 422)
   - Regra de negócio crítica (estado no banco)
4. Rodar os testes antes do commit

```php
namespace Tests\Feature\{Domain};

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class {Model}Test extends TestCase
{
    use RefreshDatabase;

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

### Banco de teste

- Só MySQL local (nunca SQLite; o driver não está disponível)
- `RefreshDatabase` em todos os testes de feature
- Testes financeiros em `tests/Feature/Finance/` (relatórios, cartões, faturas, reservas, recorrência, defeitos da auditoria)
- **Toda rota nova de Finanças que recebe um registro entra em `FinanceRoutesAccessTest`** (o dono consegue, outro usuário é recusado e nada muda). Os bugs de arquivar conta (500) e editar meta (403) passaram despercebidos porque nenhum teste fazia o caminho básico
