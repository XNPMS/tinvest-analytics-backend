<?php

declare(strict_types=1);

use Phpmig\Migration\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;
use Tinvest\Entity\TinvestOperation;

final class CreateTinvestOperations extends Migration
{
    /**
     * Do the migration
     */
    public function up(): void
    {
        Capsule::schema()->create(TinvestOperation::TABLE, function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('account_id');
            $table->string('operation_id', 128);
            $table->string('parent_operation_id', 128)->nullable();
            $table->string('name', 128);
            $table->string('payment_currency', 16)->nullable();
            $table->bigInteger('payment_units')->nullable();
            $table->bigInteger('payment_nano')->nullable();
            $table->json('price')->nullable();
            $table->string('state', 64)->nullable();
            $table->bigInteger('quantity')->nullable();
            $table->bigInteger('quantity_rest')->nullable();
            $table->string('figi', 64)->nullable();
            $table->string('instrument_type', 32)->nullable();
            $table->dateTime('date')->nullable();
            $table->string('operation_type', 128)->nullable();
            $table->json('trades')->nullable();
            $table->string('asset_uid', 128)->nullable();
            $table->string('position_uid', 128)->nullable();
            $table->string('ticker', 128)->nullable();
            $table->string('instrument_uid', 128)->nullable();
            $table->string('description', 128)->nullable();
            $table->json('child_operations')->nullable();
            $table->timestamps();
        });

        $conn = Capsule::schema()->getConnection();

        // Уникальный индекс на account_id + operation_id
        $conn->statement(sprintf(
            'CREATE UNIQUE INDEX idx_tinvest_operations_account_opid ON %s(account_id, operation_id)',
            TinvestOperation::TABLE
        ));

        // Индекс для выбора по figi
        $conn->statement(sprintf(
            'CREATE INDEX idx_tinvest_operations_figi ON %s(figi)',
            TinvestOperation::TABLE
        ));

        // Индекс для instrument_uid
        $conn->statement(sprintf(
            'CREATE INDEX idx_tinvest_operations_insuid ON %s(instrument_uid)',
            TinvestOperation::TABLE
        ));

        // Индекс по дате операции
        $conn->statement(sprintf(
            'CREATE INDEX idx_tinvest_operations_date ON %s(date)',
            TinvestOperation::TABLE
        ));

        $conn->statement(sprintf(
            'ALTER TABLE %s ADD CONSTRAINT fk_operations_account_id
                FOREIGN KEY(account_id) REFERENCES tinvest_accounts(id)
                ON DELETE CASCADE ON UPDATE CASCADE',
            TinvestOperation::TABLE
        ));
    }

    /**
     * Undo the migration
     */
    public function down(): void
    {
        $conn = Capsule::schema()->getConnection();
        $conn->statement(sprintf(
            'ALTER TABLE %s DROP FOREIGN KEY fk_operations_account_id',
            TinvestOperation::TABLE
        ));

        $conn->statement(sprintf(
            'DROP INDEX idx_tinvest_operations_account_opid ON %s',
            TinvestOperation::TABLE
        ));
        $conn->statement(sprintf(
            'DROP INDEX idx_tinvest_operations_figi ON %s',
            TinvestOperation::TABLE
        ));
        $conn->statement(sprintf(
            'DROP INDEX idx_tinvest_operations_insuid ON %s',
            TinvestOperation::TABLE
        ));
        $conn->statement(sprintf(
            'DROP INDEX idx_tinvest_operations_date ON %s',
            TinvestOperation::TABLE
        ));

        Capsule::schema()->drop(TinvestOperation::TABLE);
    }
}
