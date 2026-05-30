<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Phpmig\Migration\Migration;
use Tinvest\Entity\Instrument;

final class ExpandExchangeColumnInInstruments extends Migration
{
    public function up(): void
    {
        Capsule::schema()->table(Instrument::TABLE, function (Blueprint $table) {
            $table->string('exchange', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Capsule::schema()->table(Instrument::TABLE, function (Blueprint $table) {
            $table->string('exchange', 10)->nullable()->change();
        });
    }
}