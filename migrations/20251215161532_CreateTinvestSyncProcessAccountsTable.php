<?php

declare(strict_types=1);

use Phpmig\Migration\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class CreateTinvestSyncProcessAccountsTable extends Migration
{
    /**
     * Do the migration
     */
    public function up(): void
    {
        Capsule::schema()->create('tinvest_sync_process_accounts', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('process_id');
            $table->unsignedInteger('account_id');

            $table->timestamps();

            $table->unique(
                ['process_id', 'account_id'],
                'ux_sync_process_account'
            );

            $table->foreign('process_id', 'fk_sync_accounts_process')
                ->references('id')
                ->on('tinvest_sync_processes')
                ->onDelete('cascade');

            $table->foreign('account_id', 'fk_sync_accounts_account')
                ->references('id')
                ->on('tinvest_accounts')
                ->onDelete('cascade');
        });
    }

    /**
     * Undo the migration
     */
    public function down(): void
    {
        Capsule::schema()->drop('tinvest_sync_process_accounts');
    }
}
