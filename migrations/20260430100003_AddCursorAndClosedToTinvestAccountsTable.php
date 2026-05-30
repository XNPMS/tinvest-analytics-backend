<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Phpmig\Migration\Migration;
use Tinvest\Entity\TinvestAccount;

final class AddCursorAndClosedToTinvestAccountsTable extends Migration
{
    public function up(): void
    {
        Capsule::schema()->table(TinvestAccount::TABLE, function (Blueprint $table) {
            $table->string('last_sync_cursor', 64)->nullable()->after('is_synced');
            $table->tinyInteger('is_closed')->unsigned()->default(0)->after('last_sync_cursor');
            $table->date('closed_date')->nullable()->after('is_closed');
        });
    }

    public function down(): void
    {
        Capsule::schema()->table(TinvestAccount::TABLE, function (Blueprint $table) {
            $table->dropColumn(['last_sync_cursor', 'is_closed', 'closed_date']);
        });
    }
}
