<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Phpmig\Migration\Migration;

final class MigrateUsersIdToUuid extends Migration
{
    public function up(): void
    {
        $conn = Capsule::schema()->getConnection();
        $conn->statement('SET FOREIGN_KEY_CHECKS = 0');

        // 1. users: добавляем uuid-колонку и email_verified_at (идемпотентно)
        if (!$this->columnExists('users', 'new_uuid')) {
            $conn->statement('ALTER TABLE users ADD COLUMN new_uuid CHAR(36) DEFAULT NULL AFTER id');
        }
        $conn->statement('UPDATE users SET new_uuid = UUID() WHERE new_uuid IS NULL');

        if (!$this->columnExists('users', 'email_verified_at')) {
            $conn->statement('ALTER TABLE users ADD COLUMN email_verified_at TIMESTAMP NULL AFTER is_active');
        }

        // 2. Зависимые таблицы: добавляем new_user_id, заполняем (идемпотентно)
        foreach (['refresh_tokens', 'broker_tokens', 'tinvest_accounts', 'tinvest_sync_processes'] as $table) {
            if (!$this->columnExists($table, 'new_user_id')) {
                $conn->statement("ALTER TABLE {$table} ADD COLUMN new_user_id CHAR(36) DEFAULT NULL");
            }
            $conn->statement(
                "UPDATE {$table} t JOIN users u ON t.user_id = u.id"
                . ' SET t.new_user_id = u.new_uuid WHERE t.new_user_id IS NULL'
            );
        }

        // 3. Сначала убираем все FK из зависимых таблиц
        if ($this->columnType('refresh_tokens', 'user_id') !== 'char') {
            $conn->statement('ALTER TABLE refresh_tokens DROP FOREIGN KEY fk_refresh_tokens_user_id');
        }
        if ($this->columnType('broker_tokens', 'user_id') !== 'char') {
            $conn->statement('ALTER TABLE broker_tokens DROP FOREIGN KEY fk_broker_tokens_user_id');
        }
        if ($this->columnType('tinvest_accounts', 'user_id') !== 'char') {
            $conn->statement('ALTER TABLE tinvest_accounts DROP FOREIGN KEY fk_tinkoff_accounts_user_id');
        }
        if ($this->columnType('tinvest_sync_processes', 'user_id') !== 'char') {
            $conn->statement('ALTER TABLE tinvest_sync_processes DROP FOREIGN KEY fk_sync_processes_user');
        }

        // 4. users: меняем PK (только если id ещё INT)
        if ($this->columnType('users', 'id') !== 'char') {
            $conn->statement('ALTER TABLE users MODIFY COLUMN id INT UNSIGNED NOT NULL');
            $conn->statement('ALTER TABLE users DROP PRIMARY KEY');
            $conn->statement('ALTER TABLE users DROP COLUMN id');
            $conn->statement('ALTER TABLE users CHANGE new_uuid id CHAR(36) NOT NULL');
            $conn->statement('ALTER TABLE users ADD PRIMARY KEY (id)');
            $conn->statement('ALTER TABLE users MODIFY COLUMN id CHAR(36) NOT NULL FIRST');
        }

        // 5. refresh_tokens
        if ($this->columnType('refresh_tokens', 'user_id') !== 'char') {
            if ($this->indexExists('refresh_tokens', 'idx_refresh_tokens_active')) {
                $conn->statement('DROP INDEX idx_refresh_tokens_active ON refresh_tokens');
            }
            $conn->statement('ALTER TABLE refresh_tokens DROP COLUMN user_id');
            $conn->statement('ALTER TABLE refresh_tokens CHANGE new_user_id user_id CHAR(36) NOT NULL');
            $conn->statement('ALTER TABLE refresh_tokens ADD CONSTRAINT fk_refresh_tokens_user_id
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE');
            $conn->statement('CREATE INDEX idx_refresh_tokens_active ON refresh_tokens(user_id, revoked, expires_at)');
        }

        // 6. broker_tokens
        if ($this->columnType('broker_tokens', 'user_id') !== 'char') {
            if ($this->indexExists('broker_tokens', 'uq_broker_tokens_user')) {
                $conn->statement('DROP INDEX uq_broker_tokens_user ON broker_tokens');
            }
            $conn->statement('ALTER TABLE broker_tokens DROP COLUMN user_id');
            $conn->statement('ALTER TABLE broker_tokens CHANGE new_user_id user_id CHAR(36) NOT NULL');
            $conn->statement('ALTER TABLE broker_tokens ADD UNIQUE KEY uq_broker_tokens_user (user_id)');
            $conn->statement('ALTER TABLE broker_tokens ADD CONSTRAINT fk_broker_tokens_user_id
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE');
        }

        // 7. tinvest_accounts
        if ($this->columnType('tinvest_accounts', 'user_id') !== 'char') {
            if ($this->indexExists('tinvest_accounts', 'idx_tinkoff_accounts_user_account')) {
                $conn->statement('DROP INDEX idx_tinkoff_accounts_user_account ON tinvest_accounts');
            }
            $conn->statement('ALTER TABLE tinvest_accounts DROP COLUMN user_id');
            $conn->statement('ALTER TABLE tinvest_accounts CHANGE new_user_id user_id CHAR(36) NOT NULL');
            $conn->statement('ALTER TABLE tinvest_accounts ADD CONSTRAINT fk_tinkoff_accounts_user_id
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE');
            $conn->statement(
                'CREATE UNIQUE INDEX idx_tinkoff_accounts_user_account ON tinvest_accounts(user_id, account_id)'
            );
        }

        // 8. tinvest_sync_processes
        if ($this->columnType('tinvest_sync_processes', 'user_id') !== 'char') {
            if ($this->indexExists('tinvest_sync_processes', 'idx_sync_processes_user_status')) {
                $conn->statement('DROP INDEX idx_sync_processes_user_status ON tinvest_sync_processes');
            }
            $conn->statement('ALTER TABLE tinvest_sync_processes DROP COLUMN user_id');
            $conn->statement('ALTER TABLE tinvest_sync_processes CHANGE new_user_id user_id CHAR(36) NOT NULL');
            $conn->statement('ALTER TABLE tinvest_sync_processes ADD CONSTRAINT fk_sync_processes_user
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE');
            $conn->statement(
                'CREATE INDEX idx_sync_processes_user_status ON tinvest_sync_processes(user_id, status)'
            );
        }

        $conn->statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down(): void
    {
        throw new \LogicException('This migration cannot be rolled back automatically. Recreate the database.');
    }

    private function columnExists(string $table, string $column): bool
    {
        $rows = Capsule::schema()->getConnection()->select(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column],
        );

        return !empty($rows);
    }

    private function columnType(string $table, string $column): string
    {
        $rows = Capsule::schema()->getConnection()->select(
            'SELECT DATA_TYPE FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column],
        );

        return !empty($rows) ? strtolower((string)($rows[0]->DATA_TYPE ?? '')) : '';
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $rows = Capsule::schema()->getConnection()->select(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $indexName],
        );

        return !empty($rows);
    }
}
