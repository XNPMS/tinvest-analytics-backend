<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Phpmig\Migration\Migration;

final class ConvertDatabaseToUtf8mb4 extends Migration
{
    private const COLLATION = 'utf8mb4_unicode_ci';

    public function up(): void
    {
        $conn = Capsule::schema()->getConnection();
        $conn->statement('SET FOREIGN_KEY_CHECKS = 0');

        $conn->statement('ALTER DATABASE CHARACTER SET utf8mb4 COLLATE ' . self::COLLATION);

        $tables = $conn->select(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ?',
            ['BASE TABLE'],
        );

        foreach ($tables as $row) {
            $table = $row->TABLE_NAME;
            $conn->statement(
                "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE " . self::COLLATION
            );
        }

        $conn->statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down(): void
    {
        $conn = Capsule::schema()->getConnection();
        $conn->statement('SET FOREIGN_KEY_CHECKS = 0');

        $conn->statement('ALTER DATABASE CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci');

        $tables = $conn->select(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ?',
            ['BASE TABLE'],
        );

        foreach ($tables as $row) {
            $table = $row->TABLE_NAME;
            $conn->statement(
                "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci"
            );
        }

        $conn->statement('SET FOREIGN_KEY_CHECKS = 1');
    }
}
