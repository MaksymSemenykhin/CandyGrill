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
            '20260331120101_create_users.php' => ['CREATE TABLE users', "ENUM('active', 'inactive')"],
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

        $tzPath = $dir . '/20260401120001_tz_registration_schema.php';
        $this->assertFileExists($tzPath);
        $tz = (string) file_get_contents($tzPath);
        $this->assertStringContainsString('ALTER TABLE characters', $tz);
        $this->assertStringContainsString('skill_1', $tz);

        $pubPath = $dir . '/20260404180000_add_users_public_id.php';
        $this->assertFileExists($pubPath);
        $pub = (string) file_get_contents($pubPath);
        $this->assertStringContainsString('public_id', $pub);
        $this->assertStringContainsString('uq_users_public_id', $pub);

        $combatsPath = $dir . '/20260415120000_alter_combats_public_id_and_claim.php';
        $this->assertFileExists($combatsPath);
        $combatsPhp = (string) file_get_contents($combatsPath);
        $this->assertStringContainsString('results_applied_at', $combatsPhp);
        $this->assertStringContainsString('uq_combats_public_id', $combatsPhp);
    }
}
