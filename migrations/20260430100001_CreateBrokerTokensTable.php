<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Phpmig\Migration\Migration;
use Tinvest\Entity\BrokerToken;

final class CreateBrokerTokensTable extends Migration
{
    public function up(): void
    {
        Capsule::schema()->create(BrokerToken::TABLE, function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->text('token_encrypted');
            $table->string('token_iv', 64);
            $table->string('token_tag', 32);
            $table->enum('status', ['active', 'invalid', 'revoked'])->default('active');
            $table->string('last_error', 500)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique('user_id', 'uq_broker_tokens_user');
            $table->index('status', 'idx_broker_tokens_status');

            $table->foreign('user_id', 'fk_broker_tokens_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Capsule::schema()->table(BrokerToken::TABLE, function (Blueprint $table) {
            $table->dropForeign('fk_broker_tokens_user_id');
        });

        Capsule::schema()->drop(BrokerToken::TABLE);
    }
}
