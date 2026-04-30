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

## Важные ограничения Tinkoff API

- **GetOperationsByCursor** не возвращает total count — прогресс в % недоступен до завершения.
  Транслируется `synced_count` (фактическое кол-во сохранённых операций).
- Rate limiting на операции: реализован в `RateLimiter` (LimitTokens::MAX_TOKENS_SERVICE_OPERATIONS).
- У пользователя может быть до 10 брокерских счетов.
