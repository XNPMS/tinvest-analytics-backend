<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Phpmig\Migration\Migration;
use Tinvest\Entity\TinvestSyncProcesses;

final class AddTotalCountToTinvestSyncProcessesTable extends Migration
{
    public function up(): void
    {
        Capsule::schema()->table(TinvestSyncProcesses::TABLE, function (Blueprint $table) {
            $table->unsignedInteger('total_count')->default(0)->after('synced_count');
        });
    }

    public function down(): void
    {
        Capsule::schema()->table(TinvestSyncProcesses::TABLE, function (Blueprint $table) {
            $table->dropColumn('total_count');
        });
    }
}
