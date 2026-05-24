<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Phpmig\Migration\Migration;
use Tinvest\Entity\Instrument;

final class CreateInstrumentsTable extends Migration
{
    public function up(): void
    {
        Capsule::schema()->create(Instrument::TABLE, function (Blueprint $table) {
            $table->increments('id');
            $table->string('figi', 32);
            $table->string('ticker', 32)->nullable();
            $table->string('isin', 32)->nullable();
            $table->string('name', 255);
            $table->enum('asset_type', ['stock', 'bond', 'etf', 'currency', 'future', 'option', 'other']);
            $table->string('sector', 64)->nullable();
            $table->string('currency', 3);
            $table->string('exchange', 10)->nullable();
            $table->decimal('lot_size', 10, 0)->default(1);
            $table->tinyInteger('is_tradable')->unsigned()->default(1);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('figi', 'uq_instruments_figi');
            $table->index('ticker', 'idx_instruments_ticker');
            $table->index('asset_type', 'idx_instruments_asset_type');
            $table->index('sector', 'idx_instruments_sector');
        });
    }

    public function down(): void
    {
        Capsule::schema()->drop(Instrument::TABLE);
    }
}
