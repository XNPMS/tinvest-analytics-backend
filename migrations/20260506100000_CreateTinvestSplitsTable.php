<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Phpmig\Migration\Migration;
use Tinvest\Entity\TinvestSplit;

final class CreateTinvestSplitsTable extends Migration
{
    public function up(): void
    {
        Capsule::schema()->create(TinvestSplit::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string('ticker', 32);
            $table->date('split_date');
            $table->unsignedInteger('ratio');
            $table->timestamps();
            $table->unique(['ticker', 'split_date']);
        });
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists(TinvestSplit::TABLE);
    }
}
