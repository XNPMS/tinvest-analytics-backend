<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Phpmig\Migration\Migration;
use Tinvest\Entity\PortfolioSnapshot;

final class AddExpectedYieldRubToPortfolioSnapshots extends Migration
{
    public function up(): void
    {
        Capsule::schema()->table(PortfolioSnapshot::TABLE, function (Blueprint $table) {
            $table->decimal('expected_yield_rub', 18, 2)->default(0)->after('cash_flow_rub');
        });
    }

    public function down(): void
    {
        Capsule::schema()->table(PortfolioSnapshot::TABLE, function (Blueprint $table) {
            $table->dropColumn('expected_yield_rub');
        });
    }
}
