# Financial recovery tooling

Phase 3 adds the real Laravel importer (`finance:recover`) and selective rollback
(`finance:rollback-recovery`). Both default to dry-run. Targets outside local
`vaultos_recovery_*` MySQL databases require explicit host/database/user/plan-hash
approval configuration, disabled by default. No production approval is implied.

`mysql_bootstrap.php` never loads the application's `.env` or cached configuration.
It uses 127.0.0.1:3306 and an isolated database selected by `RECOVERY_DATABASE`.
The local password can be supplied via `RECOVERY_LOCAL_PASSWORD`; it is never
printed. `mysql_prepare.php` creates this isolated schema and runs real migrations.
`mysql_homologate.php` restores the scoped audit snapshot with a synthetic user,
tests the command/service, races two importer processes, verifies balances,
statements, reimbursements and rollback, and writes the local result artifact.
It requires a fresh isolated database because a reversed batch remains audited.
`mysql_backup_restore.php` dumps every table/routine/event/trigger of the isolated
database, restores into a new isolated database and compares every row. This is
not a production backup. Runtime and private financial artifacts stay outside Git.

Run `php vendor/bin/phpunit -c phpunit.recovery.xml` for the MySQL financial tests.
The default test database is `vaultos_recovery_unit`; prepare it first. This test
suite uses RefreshDatabase and must never target a live database.

## Preserved Phase 2 tools

The following Phase 2 tools **do not import into Laravel or MySQL**. They validate a reviewed,
local plan against a financial snapshot using SQLite. No production URL, `.env`,
credentials, mail, queue worker, or scheduler is required.

- `simulator.py`: transactional staging, preconditions, source uniqueness,
  before/after journal, replay prevention, backup and guarded rollback.
- `test_simulator.py`: 14 synthetic tests, including rollback conflicts,
  cross-user protection, duplicate sources and separate partial payments.
- `laravel_probe.php`: restore the financial subset through the existing Laravel
  migrations into a **new local SQLite file**, then exercise real financial
  actions. It requires PDO's driver list to contain only SQLite and does not load
  `.env`. It uses a synthetic local user and fake notification/mail/queue drivers.

Run the Python tests with `python -m unittest discover -s tools/recovery -p
test_simulator.py -v` from the backend root. A Python standard-library runtime is
sufficient. Provide an environment that can write to its temporary directory.

For the PHP probe, from the workspace root on Windows, use the bundled PHP with
`-n`, `-d extension_dir=.runtime/php84/ext`, and enable only `pdo_sqlite`,
`mbstring`, `openssl`, `fileinfo`. Pass the snapshot path, a **new** `.sqlite`
destination and a result JSON path to `tools/recovery/laravel_probe.php`.
Do not substitute the production PHP configuration or run the ordinary test
configuration against a production `.env`.

Private inputs, generated plans, databases and financial results belong outside
this repository. The phase-2 workspace contains the orchestration scripts and
their exact provenance. No personal financial fixture is committed here.

The staging schema deliberately stores proposed extra fields separately from the
application schema. Passing these checks is **not** evidence that the current
application supports all proposed fields or that a MySQL restore was tested.
The application adapter, migrations, common financial reporting policy and fresh
production backup/restore rehearsal remain prerequisites for production import.
