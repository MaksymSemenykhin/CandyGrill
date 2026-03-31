<?php

declare(strict_types=1);

namespace Game\Tests;

use PHPUnit\Framework\TestCase;

final class DatabaseMigrationFileTest extends TestCase
{
    /**
     * One Phinx migration file per table; DDL lives in the same file (nowdoc) as $this->execute().
     */
    public function testMigrationsContainExpectedDdl(): void
    {
        $dir = dirname(__DIR__) . '/database/migrations';
        $expect = [
            '20260331120101_create_users.php' => ['CREATE TABLE users', 'uq_users_email'],
            '20260331120102_create_characters.php' => ['CREATE TABLE characters', 'fk_characters_user'],
            '20260331120103_create_combats.php' => ['CREATE TABLE combats', 'idx_combats_participant_a_status'],
            '20260331120104_create_combat_moves.php' => ['CREATE TABLE combat_moves', 'uq_combat_moves_turn'],
        ];

        foreach ($expect as $file => $needles) {
            $path = $dir . '/' . $file;
            $this->assertFileExists($path);
            $php = (string) file_get_contents($path);
            $this->assertStringContainsString('Phinx\\Migration\\AbstractMigration', $php);
            $this->assertStringContainsString("<<<'SQL'", $php);
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $php, $file);
            }
        }
    }
}
