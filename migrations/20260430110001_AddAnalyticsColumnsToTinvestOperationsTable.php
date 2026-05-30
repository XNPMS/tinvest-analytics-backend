<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Phpmig\Migration\Migration;
use Tinvest\Entity\TinvestOperation;

final class AddAnalyticsColumnsToTinvestOperationsTable extends Migration
{
    public function up(): void
    {
        Capsule::schema()->table(TinvestOperation::TABLE, function (Blueprint $table) {
            $table->decimal('payment', 18, 6)->nullable()->after('payment_nano');
            $table->decimal('commission', 18, 6)->default(0)->after('payment');
            $table->decimal('payment_rub', 18, 6)->nullable()->after('commission');
            $table->decimal('commission_rub', 18, 6)->default(0)->after('payment_rub');
            $table->decimal('fx_rate', 18, 6)->default(1)->after('commission_rub');
        });
    }

    public function down(): void
    {
        Capsule::schema()->table(TinvestOperation::TABLE, function (Blueprint $table) {
            $table->dropColumn(['payment', 'commission', 'payment_rub', 'commission_rub', 'fx_rate']);
        });
    }
}
