<?php

declare(strict_types=1);

use Auth\Entity\RefreshToken;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Phpmig\Migration\Migration;

final class AddIpUserAgentToRefreshTokensTable extends Migration
{
    public function up(): void
    {
        Capsule::schema()->table(RefreshToken::TABLE, function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('refresh_token_hash');
            $table->text('user_agent')->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Capsule::schema()->table(RefreshToken::TABLE, function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent']);
        });
    }
}
