<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Phpmig\Migration\Migration;

final class ExpandJobIdColumnInSyncProcesses extends Migration
{
    public function up(): void
    {
        Capsule::schema()->table('tinvest_sync_processes', static function ($table) {
            $table->string('job_id', 64)->change();
        });
    }

    public function down(): void
    {
        Capsule::schema()->table('tinvest_sync_processes', static function ($table) {
            $table->string('job_id', 32)->change();
        });
    }
}
