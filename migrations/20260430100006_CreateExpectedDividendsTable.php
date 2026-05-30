<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Phpmig\Migration\Migration;
use Tinvest\Entity\ExpectedDividend;

final class CreateExpectedDividendsTable extends Migration
{
    public function up(): void
    {
        Capsule::schema()->create(ExpectedDividend::TABLE, function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('instrument_id');
            $table->date('record_date');
            $table->date('payment_date')->nullable();
            $table->decimal('amount_per_share', 18, 6);
            $table->string('currency', 3);
            $table->decimal('amount_rub', 18, 6)->nullable();
            $table->tinyInteger('is_confirmed')->unsigned()->default(0);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['instrument_id', 'record_date'], 'uq_expected_dividends_instrument_record');
            $table->index('payment_date', 'idx_expected_dividends_payment_date');

            $table->foreign('instrument_id', 'fk_expected_dividends_instrument_id')
                ->references('id')
                ->on('instruments')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Capsule::schema()->table(ExpectedDividend::TABLE, function (Blueprint $table) {
            $table->dropForeign('fk_expected_dividends_instrument_id');
        });

        Capsule::schema()->drop(ExpectedDividend::TABLE);
    }
}
