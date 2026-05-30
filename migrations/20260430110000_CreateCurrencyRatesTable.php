<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Phpmig\Migration\Migration;
use Tinvest\Entity\CurrencyRate;

final class CreateCurrencyRatesTable extends Migration
{
    public function up(): void
    {
        Capsule::schema()->create(CurrencyRate::TABLE, function (Blueprint $table) {
            $table->increments('id');
            $table->date('rate_date');
            $table->string('currency', 3);
            $table->decimal('rate_to_rub', 18, 6);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['rate_date', 'currency'], 'uq_currency_rates_date_currency');
            $table->index('currency', 'idx_currency_rates_currency');
        });
    }

    public function down(): void
    {
        Capsule::schema()->drop(CurrencyRate::TABLE);
    }
}
