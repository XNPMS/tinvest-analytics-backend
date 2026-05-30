# Tinvest Analytics Backend

## Цель проекта

Платформа для аналитики инвестиционного портфеля через интеграцию с **Tinkoff Invest API v2** (gRPC).
Пользователь подключает свой токен, выбирает брокерские счета, система синхронизирует операции
и предоставляет аналитику по портфелю.

## Стек технологий

| Компонент        | Технология                                        |
|------------------|---------------------------------------------------|
| PHP Framework    | Mezzio (PSR-15 middleware)                        |
| DI Container     | Laminas ServiceManager + ReflectionBasedAbstractFactory |
| ORM              | Eloquent (illuminate/database) без Laravel        |
| Миграции         | Phpmig                                            |
| Очереди          | RabbitMQ (php-amqplib)                            |
| Кэш              | Memcached (PSR-16 CacheInterface)                 |
| WebSocket        | Ratchet (cboden/ratchet) + ReactPHP event loop   |
| Брокер API       | Tinkoff Invest API v2 gRPC (metaseller/tinkoff-invest-api-v2-php) |
| Аутентификация   | JWT (lcobucci/jwt), OAuth2-подобный flow          |
| Логирование      | Monolog                                           |
| CLI              | laminas-cli (symfony/console)                     |

## Структура директорий

```
src/
  App/          — базовый PingHandler
  Auth/         — регистрация, логин, логаут, JWT middleware
  System/       — инфраструктурный модуль (очереди, кэш, логи, WS-команда)
  Tinvest/      — бизнес-логика интеграции с Tinkoff
  User/         — сущность пользователя и UserService
migrations/     — Phpmig-миграции
config/         — конфиги Mezzio, роуты, autoload-конфиги
```

## Модули

### Auth

Аутентификация через JWT. Два токена: access (короткоживущий, в теле ответа)
и refresh (долгоживущий, в httpOnly cookie).

- `RegisterUserHandler` — создание пользователя
- `LoginUserHandler` — выдача пары токенов
- `LogoutUserHandler` — инвалидация refresh-токена
- `AuthMiddleware` — PSR-15 middleware, валидирует access-токен и кладёт `User` в атрибуты запроса

### Tinvest

Основной бизнес-модуль. Структура по слоям:

```
Handler/       — PSR-15 HTTP-обработчики (точки входа)
UseCase/       — юз-кейсы с бизнес-логикой
Service/       — сервисный слой
Repository/    — репозитории (Eloquent)
Entity/        — Eloquent-модели
DTO/           — объекты передачи данных
Message/       — сообщения для очередей
Worker/        — RabbitMQ-воркеры
WebSocket/     — WebSocket-приложение (Ratchet)
Enum/          — перечисления
Event/ EventListener/ — события и слушатели (Laminas EventManager)
InputFilter/   — валидация входных данных
Exception/     — доменные исключения
```

### System

Инфраструктурный модуль. Не содержит бизнес-логики.

```
Queue/
  Client/      — RabbitMQ-клиент
  Producer/    — QueueManager, RabbitMQProducer
  Worker/      — AbstractWorker (базовый класс воркеров)
  Command/     — QueueWorkerCommand, WebSocketServerCommand
  Config/      — RabbitMQConfig
  Enum/        — Workers (enum очередей → воркеры)
Factory/       — фабрики для DI
Repository/    — AbstractEloquentRepository
Service/       — UseInputFilter trait, InputFilterMessageResolver
```

## Flow синхронизации с Tinkoff

### 1. Подключение токена

```
POST /api/v1/tinvest/token
  → CreateTinvestTokenHandler
  → UserService::saveTinvestToken()
  → TinvestApiService::getAllAccountsTinvest()   // gRPC запрос
  → AccountsFetchedEvent → SaveAccountsListener  // сохранение счетов в tinvest_accounts
  ← {accounts: [...]}
```

### 2. Выбор счетов для синхронизации

```
POST /api/v1/tinvest/accounts/selection  { account_ids: [1, 2] }
  → TinvestAccountsSelectionHandler
  → QueueManager::send(Workers::SYNC_TINVEST_ACCOUNTS, AccountsMessage)
  ← {success: true, job_id: "1-1735000000-abc123"}
```

`account_ids` — числовые ID из таблицы `tinvest_accounts` (не Tinkoff строковые).

### 3. Синхронизация операций (воркер)

```
RabbitMQ: queue sync.tinvest.accounts
  → SyncTinvestAccountWorker::process()
  → SyncOperationsUseCase::execute(AccountsMessage, jobId)
    for each accountId:
      → TinvestAccountService::getTinvestAccountById()
      → TinvestSyncProcessesService::findActiveByAccountId()  // проверка дубля
      → TinvestSyncProcessesService::createProcess()          // status=RUNNING
      → TinvestApiService::streamOperations()                 // gRPC cursor pagination
        каждый батч 500 операций:
          → TinvestOperationService::saveOperationsBatch()
          → TinvestSyncProcessesService::updateSyncedCount()
          → SyncProgressPublisher::publish()                  // Memcached: sync:{jobId}
      → TinvestSyncProcessesService::markCompleted()          // status=COMPLETED, progress=100
      → SyncProgressPublisher::publish(COMPLETED)
```

### 4. WebSocket прогресс

```
WebSocketServerCommand (порт WS_PORT=8081)
  Ratchet IoServer + ReactPHP event loop

Клиент подключается к ws://host:8081
Клиент отправляет: {"action": "subscribe", "job_id": "..."}
Сервер сразу отвечает текущим прогрессом из Memcached.

Каждую 1 секунду (addPeriodicTimer):
  SyncProgressWsApp::broadcastAll()
    → SyncProgressPublisher::getProgress(jobId)   // читает Memcached
    → conn->send({job_id, accounts: {accountId: {status, synced_count}}})

Формат сообщения от сервера:
{
  "job_id": "1-1735000000-abc123",
  "accounts": {
    "42": {"status": 1, "synced_count": 1500, "error": null, "updated_at": 1735000060},
    "43": {"status": 2, "synced_count": 3200, "error": null, "updated_at": 1735000090}
  }
}

status: 0=PENDING, 1=RUNNING, 2=COMPLETED, 3=FAILED
```

## Схема базы данных (ключевые таблицы)

```
users
  id, email, password_hash, tinvest_token, created_at, updated_at

tinvest_accounts
  id, user_id, account_id (Tinkoff string), name, analytics_enabled,
  is_synced, status, type, opened_date, access_level, created_at, updated_at

tinvest_operations
  id, account_id (FK), operation_id, parent_operation_id, name,
  payment_currency, payment_units, payment_nano, price, state,
  quantity, quantity_rest, figi, instrument_type, date, operation_type,
  trades, asset_uid, position_uid, ticker, instrument_uid,
  description, child_operations, created_at, updated_at

tinvest_sync_processes
  id, user_id, account_id (FK→tinvest_accounts.id), job_id (unique),
  action (operations|portfolio), status (0-3), progress (0-100),
  synced_count, error_message, started_at, finished_at, created_at, updated_at

tinvest_sync_process_accounts
  id, process_id (FK), account_id (FK), created_at, updated_at
```

## Очереди RabbitMQ

| Queue name               | Workers enum               | Worker class                  |
|--------------------------|----------------------------|-------------------------------|
| sync.tinvest.accounts    | Workers::SYNC_TINVEST_ACCOUNTS | SyncTinvestAccountWorker   |

Запуск воркера:
```bash
php vendor/bin/laminas queue:worker sync.tinvest.accounts
```

## CLI-команды

```bash
# Запуск воркера очереди
php vendor/bin/laminas queue:worker sync.tinvest.accounts

# Запуск WebSocket-сервера (порт из WS_PORT)
php vendor/bin/laminas websocket:server
```

## Переменные окружения

```env
DATABASE_HOST, DATABASE_PORT, DATABASE_NAME, DATABASE_USERNAME, DATABASE_PASSWORD
MEMCACHED_HOST, MEMCACHED_PORT, MEMCACHED_WEIGHT
RABBITMQ_HOST, RABBITMQ_PORT, RABBITMQ_USER, RABBITMQ_PASSWORD, RABBITMQ_VHOST
JWT_PRIVATE_KEY, JWT_PUBLIC_KEY, JWT_ISS, JWT_AUD
ACCESS_TTL, REFRESH_TTL, COOKIE_PATH
WS_PORT=8081
```

## DI и автовайринг

`ReflectionBasedAbstractFactory` автоматически резолвит зависимости по type-hint конструктора.
Кастомные фабрики нужны только когда зависимость требует конфигурации (порт, ключ и т.п.)
или когда тип в конструкторе — интерфейс без alias.

Алиасы:
- `CacheInterface` → `SimpleCacheDecorator` (Memcached)
- `LoggerInterface` → `Logger` (Monolog)

## Соглашения по коду (обязательно соблюдать)

### Конкатенация строк

Всегда использовать `sprintf()`, никогда `.`:

```php
// ✓ правильно
$message = sprintf('Account %s not found for user %s', $accountId, $userId);

// ✗ неправильно
$message = 'Account ' . $accountId . ' not found for user ' . $userId;
```

### Стиль присваивания переменных

Без выравнивания по `=`. Одиночный пробел:

```php
// ✓ правильно
$qty = $position->getQuantity();
$currentPrice = $position->getCurrentPrice();
$avgPrice = $position->getAveragePositionPrice();

// ✗ неправильно
$qty          = $position->getQuantity();
$currentPrice = $position->getCurrentPrice();
$avgPrice     = $position->getAveragePositionPrice();
```

### Метод where в репозиториях

Всегда передавать оператор явно:

```php
// ✓ правильно
->where('user_id', '=', $userId)

// ✗ неправильно
->where('user_id', $userId)
```

### AuthMiddleware для /api/v1

`AuthMiddleware` применяется глобально для всего префикса `/api/v1` через `config/pipeline.php`:

```php
$app->pipe('/api/v1', AuthMiddleware::class);
```

Не нужно добавлять его в роуты вручную через массив `[AuthMiddleware::class, SomeHandler::class]`.

### Стиль комментариев

Над классами, свойствами и методами — `/** */` (PHPDoc):

```php
/** @param int[] $ids */
public function findByIds(array $ids): array
```

Внутри тела метода — `//`:

```php
public function execute(): void
{
    // проверяем наличие данных перед обработкой
    if (!$items) {
        return;
    }
}
```

### Лимиты в репозиториях

Каждый метод репозитория, обращающийся к базе данных, **обязан** устанавливать лимит через `->limit(self::MAX_LIMIT)`.
Запросы без лимита запрещены — независимо от ожидаемого размера таблицы:

```php
// ✓ правильно
private const MAX_LIMIT = 1000;

public function findByTickersGroupedByTicker(array $tickers): array
{
    return $this->createQueryBuilder()
        ->whereIn('ticker', $tickers)
        ->limit(self::MAX_LIMIT)
        ->get();
}

// ✗ неправильно — нет лимита
public function findAll(): array
{
    return $this->createQueryBuilder()->get();
}
```

Исключение: методы `insertBatch`, `upsert`, `update`, `delete`, `count`, `sum` и другие агрегирующие/пишущие операции лимит не требуют.

### Запуск phpcs

Не запускать phpcs после каждого изменения — пользователь делает это самостоятельно.

## Важные ограничения Tinkoff API

- **GetOperationsByCursor** не возвращает total count — прогресс в % недоступен до завершения.
  Транслируется `synced_count` (фактическое кол-во сохранённых операций).
- Rate limiting на операции: реализован в `RateLimiter` (LimitTokens::MAX_TOKENS_SERVICE_OPERATIONS).
- У пользователя может быть до 10 брокерских счетов.

<!-- rtk-instructions v2 -->
# RTK (Rust Token Killer) - Token-Optimized Commands

## Golden Rule

**Always prefix commands with `rtk`**. If RTK has a dedicated filter, it uses it. If not, it passes through unchanged. This means RTK is always safe to use.

**Important**: Even in command chains with `&&`, use `rtk`:
```bash
# ❌ Wrong
git add . && git commit -m "msg" && git push

# ✅ Correct
rtk git add . && rtk git commit -m "msg" && rtk git push
```

## RTK Commands by Workflow

### Build & Compile (80-90% savings)
```bash
rtk cargo build         # Cargo build output
rtk cargo check         # Cargo check output
rtk cargo clippy        # Clippy warnings grouped by file (80%)
rtk tsc                 # TypeScript errors grouped by file/code (83%)
rtk lint                # ESLint/Biome violations grouped (84%)
rtk prettier --check    # Files needing format only (70%)
rtk next build          # Next.js build with route metrics (87%)
```

### Test (60-99% savings)
```bash
rtk cargo test          # Cargo test failures only (90%)
rtk go test             # Go test failures only (90%)
rtk jest                # Jest failures only (99.5%)
rtk vitest              # Vitest failures only (99.5%)
rtk playwright test     # Playwright failures only (94%)
rtk pytest              # Python test failures only (90%)
rtk rake test           # Ruby test failures only (90%)
rtk rspec               # RSpec test failures only (60%)
rtk test <cmd>          # Generic test wrapper - failures only
```

### Git (59-80% savings)
```bash
rtk git status          # Compact status
rtk git log             # Compact log (works with all git flags)
rtk git diff            # Compact diff (80%)
rtk git show            # Compact show (80%)
rtk git add             # Ultra-compact confirmations (59%)
rtk git commit          # Ultra-compact confirmations (59%)
rtk git push            # Ultra-compact confirmations
rtk git pull            # Ultra-compact confirmations
rtk git branch          # Compact branch list
rtk git fetch           # Compact fetch
rtk git stash           # Compact stash
rtk git worktree        # Compact worktree
```

Note: Git passthrough works for ALL subcommands, even those not explicitly listed.

### GitHub (26-87% savings)
```bash
rtk gh pr view <num>    # Compact PR view (87%)
rtk gh pr checks        # Compact PR checks (79%)
rtk gh run list         # Compact workflow runs (82%)
rtk gh issue list       # Compact issue list (80%)
rtk gh api              # Compact API responses (26%)
```

### JavaScript/TypeScript Tooling (70-90% savings)
```bash
rtk pnpm list           # Compact dependency tree (70%)
rtk pnpm outdated       # Compact outdated packages (80%)
rtk pnpm install        # Compact install output (90%)
rtk npm run <script>    # Compact npm script output
rtk npx <cmd>           # Compact npx command output
rtk prisma              # Prisma without ASCII art (88%)
```

### Files & Search (60-75% savings)
```bash
rtk ls <path>           # Tree format, compact (65%)
rtk read <file>         # Code reading with filtering (60%)
rtk grep <pattern>      # Search grouped by file (75%). Format flags (-c, -l, -L, -o, -Z) run raw.
rtk find <pattern>      # Find grouped by directory (70%)
```

### Analysis & Debug (70-90% savings)
```bash
rtk err <cmd>           # Filter errors only from any command
rtk log <file>          # Deduplicated logs with counts
rtk json <file>         # JSON structure without values
rtk deps                # Dependency overview
rtk env                 # Environment variables compact
rtk summary <cmd>       # Smart summary of command output
rtk diff                # Ultra-compact diffs
```

### Infrastructure (85% savings)
```bash
rtk docker ps           # Compact container list
rtk docker images       # Compact image list
rtk docker logs <c>     # Deduplicated logs
rtk kubectl get         # Compact resource list
rtk kubectl logs        # Deduplicated pod logs
```

### Network (65-70% savings)
```bash
rtk curl <url>          # Compact HTTP responses (70%)
rtk wget <url>          # Compact download output (65%)
```

### Meta Commands
```bash
rtk gain                # View token savings statistics
rtk gain --history      # View command history with savings
rtk discover            # Analyze Claude Code sessions for missed RTK usage
rtk proxy <cmd>         # Run command without filtering (for debugging)
rtk init                # Add RTK instructions to CLAUDE.md
rtk init --global       # Add RTK to ~/.claude/CLAUDE.md
```

## Token Savings Overview

| Category | Commands | Typical Savings |
|----------|----------|-----------------|
| Tests | vitest, playwright, cargo test | 90-99% |
| Build | next, tsc, lint, prettier | 70-87% |
| Git | status, log, diff, add, commit | 59-80% |
| GitHub | gh pr, gh run, gh issue | 26-87% |
| Package Managers | pnpm, npm, npx | 70-90% |
| Files | ls, read, grep, find | 60-75% |
| Infrastructure | docker, kubectl | 85% |
| Network | curl, wget | 65-70% |

Overall average: **60-90% token reduction** on common development operations.
<!-- /rtk-instructions -->