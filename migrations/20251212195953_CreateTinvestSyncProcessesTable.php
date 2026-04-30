<?php

declare(strict_types=1);

use Phpmig\Migration\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;
use Tinvest\Entity\TinvestSyncProcesses;

final class CreateTinvestSyncProcessesTable extends Migration
{
    /**
     * Do the migration
     */
    public function up(): void
    {
        Capsule::schema()->create(TinvestSyncProcesses::TABLE, function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('job_id', 32)->unique();
            // 0 = pending, 1 = running, 2 = completed, 3 = failed
            $table->unsignedTinyInteger('status')->default(0);
            $table->unsignedTinyInteger('progress')->default(0);
            $table->text('error_message')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();

            $table->timestamps();

            $table->foreign('user_id', 'fk_sync_processes_user')
                ->references('id')
                ->on('users');
        });

        Capsule::schema()->getConnection()->statement(sprintf(
            'CREATE INDEX idx_sync_processes_user_status ON %s(user_id, status)',
            TinvestSyncProcesses::TABLE
        ));
    }

    /**
     * Undo the migration
     */
    public function down(): void
    {
        Capsule::schema()->table(TinvestSyncProcesses::TABLE, function (Blueprint $table) {
            $table->dropForeign('fk_sync_processes_user');
        });

        Capsule::schema()->getConnection()->statement(sprintf(
            'DROP INDEX idx_sync_processes_user_status ON %s',
            TinvestSyncProcesses::TABLE
        ));

        Capsule::schema()->drop(TinvestSyncProcesses::TABLE);
    }
}
