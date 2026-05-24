<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Phpmig\Migration\Migration;
use Tinvest\Entity\PortfolioSnapshot;

final class CreatePortfolioSnapshotsTable extends Migration
{
    public function up(): void
    {
        Capsule::schema()->create(PortfolioSnapshot::TABLE, function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('account_id');
            $table->date('snapshot_date');
            $table->decimal('total_value_rub', 18, 2);
            $table->decimal('cash_flow_rub', 18, 2)->default(0);
            $table->decimal('twr_factor', 14, 8)->default(1);
            $table->decimal('cumulative_twr', 14, 8)->default(1);
            $table->json('positions_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['account_id', 'snapshot_date'], 'uq_portfolio_snapshots_account_date');
            $table->index(['account_id', 'snapshot_date'], 'idx_portfolio_snapshots_account_date');

            $table->foreign('account_id', 'fk_portfolio_snapshots_account_id')
                ->references('id')
                ->on('tinvest_accounts')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Capsule::schema()->table(PortfolioSnapshot::TABLE, function (Blueprint $table) {
            $table->dropForeign('fk_portfolio_snapshots_account_id');
        });

        Capsule::schema()->drop(PortfolioSnapshot::TABLE);
    }
}
