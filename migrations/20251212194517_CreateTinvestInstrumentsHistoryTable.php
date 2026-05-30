<?php

declare(strict_types=1);

use Phpmig\Migration\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;
use Tinvest\Entity\TinvestInstrumentsHistory;

final class CreateTinvestInstrumentsHistoryTable extends Migration
{
    /**
     * Do the migration
     */
    public function up(): void
    {
        Capsule::schema()->create(TinvestInstrumentsHistory::TABLE, function (Blueprint $table) {
            $table->increments('id');
            $table->string('instrument_uid', 128)->nullable();
            $table->string('figi', 64)->nullable();
            $table->string('ticker', 64)->nullable();
            $table->string('instrument_type', 32)->nullable();
            $table->date('date');
            $table->string('currency', 16)->nullable();
            $table->bigInteger('close_units')->nullable();
            $table->bigInteger('close_nano')->nullable();
            $table->timestamps();
        });

        $connection = Capsule::schema()->getConnection();

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX idx_instr_hist_unique ON %s(instrument_uid, date)',
            TinvestInstrumentsHistory::TABLE
        ));

        $connection->statement(sprintf(
            'CREATE INDEX idx_instr_hist_figi ON %s(figi)',
            TinvestInstrumentsHistory::TABLE
        ));

        $connection->statement(sprintf(
            'CREATE INDEX idx_instr_hist_ticker ON %s(ticker)',
            TinvestInstrumentsHistory::TABLE
        ));
    }

    /**
     * Undo the migration
     */
    public function down(): void
    {
        $connection = Capsule::schema()->getConnection();

        $connection->statement(sprintf('DROP INDEX idx_instr_hist_unique ON %s', TinvestInstrumentsHistory::TABLE));
        $connection->statement(sprintf('DROP INDEX idx_instr_hist_figi ON %s', TinvestInstrumentsHistory::TABLE));
        $connection->statement(sprintf('DROP INDEX idx_instr_hist_ticker ON %s', TinvestInstrumentsHistory::TABLE));

        Capsule::schema()->drop('tinvest_instruments_history');
    }
}
