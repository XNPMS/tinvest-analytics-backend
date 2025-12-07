<?php

declare(strict_types=1);

use Phpmig\Migration\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;
use User\Entity\User;

final class CreateUsersTable extends Migration
{
    /**
     * Do the migration
     */
    public function up(): void
    {
        Capsule::schema()->create(User::TABLE, function (Blueprint $table) {
            $table->increments('id');
            $table->string('email', 191)->unique();
            $table->string('password_hash', 255);
            $table->string('t_token', 255)->nullable();
            $table->timestamps();
        });

        Capsule::schema()->getConnection()->statement(
            sprintf('CREATE UNIQUE INDEX idx_users_email ON %s(email)', User::TABLE)
        );
    }

    /**
     * Undo the migration
     */
    public function down(): void
    {
        Capsule::schema()->getConnection()->statement(
            sprintf('DROP INDEX idx_users_email ON %s', User::TABLE)
        );

        Capsule::schema()->drop(User::TABLE);
    }
}
