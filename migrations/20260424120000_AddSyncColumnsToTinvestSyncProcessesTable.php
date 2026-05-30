<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Phpmig\Migration\Migration;
use Tinvest\Entity\TinvestAccount;
use Tinvest\Entity\TinvestSyncProcesses;

final class AddSyncColumnsToTinvestSyncProcessesTable extends Migration
{
    public function up(): void
    {
        Capsule::schema()->table(TinvestSyncProcesses::TABLE, function (Blueprint $table) {
            $table->unsignedInteger('account_id')->nullable()->after('user_id');
            $table->string('action', 50)->default('operations')->after('account_id');
            $table->unsignedInteger('synced_count')->default(0)->after('progress');

            $table->foreign('account_id', 'fk_sync_processes_account')
                ->references('id')
                ->on(TinvestAccount::TABLE)
                ->onDelete('set null');
        });

        Capsule::schema()->getConnection()->statement(sprintf(
            'CREATE INDEX idx_sync_processes_account ON %s(account_id)',
            TinvestSyncProcesses::TABLE
        ));
    }

    public function down(): void
    {
        Capsule::schema()->table(TinvestSyncProcesses::TABLE, function (Blueprint $table) {
            $table->dropForeign('fk_sync_processes_account');
            $table->dropColumn(['account_id', 'action', 'synced_count']);
        });

        Capsule::schema()->getConnection()->statement(sprintf(
            'DROP INDEX idx_sync_processes_account ON %s',
            TinvestSyncProcesses::TABLE
        ));
    }
}