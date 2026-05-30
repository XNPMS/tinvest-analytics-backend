<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Phpmig\Migration\Migration;
use Tinvest\Entity\Instrument;

final class AddNominalToInstruments extends Migration
{
    public function up(): void
    {
        Capsule::schema()->table(Instrument::TABLE, function (Blueprint $table) {
            $table->decimal('nominal', 14, 4)->nullable()->after('lot_size');
        });
    }

    public function down(): void
    {
        Capsule::schema()->table(Instrument::TABLE, function (Blueprint $table) {
            $table->dropColumn('nominal');
        });
    }
}
