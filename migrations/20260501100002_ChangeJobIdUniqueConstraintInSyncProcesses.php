<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Phpmig\Migration\Migration;

final class ChangeJobIdUniqueConstraintInSyncProcesses extends Migration
{
    public function up(): void
    {
        $conn = Capsule::schema()->getConnection();
        $conn->statement('DROP INDEX tinvest_sync_processes_job_id_unique ON tinvest_sync_processes');
        $conn->statement(
            'CREATE UNIQUE INDEX uq_sync_processes_job_account ON tinvest_sync_processes(job_id, account_id)'
        );
    }

    public function down(): void
    {
        $conn = Capsule::schema()->getConnection();
        $conn->statement('DROP INDEX uq_sync_processes_job_account ON tinvest_sync_processes');
        $conn->statement('CREATE UNIQUE INDEX tinvest_sync_processes_job_id_unique ON tinvest_sync_processes(job_id)');
    }
}