<?php

declare(strict_types=1);

use Phpmig\Migration\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;
use Tinvest\Entity\TinvestAccount;

final class CreateTinvestAccountsTable extends Migration
{
    /**
     * Do the migration
     */
    public function up(): void
    {
        Capsule::schema()->create(TinvestAccount::TABLE, function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('account_id', 64);
            $table->string('name', 255)->nullable();
            $table->boolean('analytics_enabled')->default(false);
            $table->unsignedTinyInteger('status');
            $table->unsignedTinyInteger('type');
            $table->string('opened_date', 255);
            $table->unsignedTinyInteger('access_level');
            $table->timestamps();

            $table->foreign('user_id', 'fk_tinkoff_accounts_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });

        Capsule::schema()->getConnection()->statement(sprintf(
            'CREATE UNIQUE INDEX idx_tinkoff_accounts_user_account ON %s(user_id, account_id)',
            TinvestAccount::TABLE
        ));
    }

    /**
     * Undo the migration
     */
    public function down(): void
    {
        Capsule::schema()->table(TinvestAccount::TABLE, function (Blueprint $table) {
            $table->dropForeign('fk_tinkoff_accounts_user_id');
        });

        Capsule::schema()->getConnection()->statement(
            sprintf('DROP INDEX idx_tinkoff_accounts_user_account ON %s', TinvestAccount::TABLE)
        );

        Capsule::schema()->drop(TinvestAccount::TABLE);
    }
}
