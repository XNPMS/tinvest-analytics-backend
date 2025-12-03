<?php

declare(strict_types=1);

use Auth\Entity\RefreshToken;
use Phpmig\Migration\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

final class CreateRefreshTokensTable extends Migration
{
    /**
     * Do the migration
     */
    public function up(): void
    {
        Capsule::schema()->create(RefreshToken::TABLE, function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->boolean('revoked')->default(false);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign('user_id', 'fk_refresh_tokens_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });

        Capsule::schema()->getConnection()->statement(sprintf(
            'CREATE INDEX idx_refresh_tokens_active ON %s(user_id, revoked, expires_at)',
            RefreshToken::TABLE
        ));

        Capsule::schema()->getConnection()->statement(sprintf(
            'ALTER TABLE %s ADD refresh_token_hash BINARY(32) UNIQUE AFTER user_id',
            RefreshToken::TABLE
        ));
    }

    /**
     * Undo the migration
     */
    public function down(): void
    {
        Capsule::schema()->table(RefreshToken::TABLE, function (Blueprint $table) {
            $table->dropForeign('fk_refresh_tokens_user_id');
        });

        Capsule::schema()->getConnection()->statement(
            sprintf('DROP INDEX idx_refresh_tokens_active ON %s', RefreshToken::TABLE)
        );

        Capsule::schema()->drop(RefreshToken::TABLE);
    }
}
