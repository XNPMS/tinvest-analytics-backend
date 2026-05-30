<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Phpmig\Migration\Migration;
use User\Entity\User;

final class AddOnboardingStepIsActiveToUsersTable extends Migration
{
    public function up(): void
    {
        Capsule::schema()->table(User::TABLE, function (Blueprint $table) {
            $table->enum('onboarding_step', ['token_missing', 'accounts_pending', 'syncing', 'ready'])
                ->default('token_missing')
                ->after('password_hash');
            $table->tinyInteger('is_active')->unsigned()->default(1)->after('onboarding_step');
        });

        Capsule::schema()->getConnection()->statement(sprintf(
            'ALTER TABLE %s DROP COLUMN t_token',
            User::TABLE
        ));
    }

    public function down(): void
    {
        Capsule::schema()->table(User::TABLE, function (Blueprint $table) {
            $table->dropColumn(['onboarding_step', 'is_active']);
        });

        Capsule::schema()->table(User::TABLE, function (Blueprint $table) {
            $table->string('t_token', 255)->nullable()->after('password_hash');
        });
    }
}
