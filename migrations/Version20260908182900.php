<?php

declare(strict_types=1);

namespace App\Casing\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260908182900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Flatten Objecting physical columns and qualify the Casing domain status column.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Casing migration supports PostgreSQL only.',
        );

        $this->renameColumnIfPresent('case_record', 'status', 'case_status');

        foreach (['case_draft', 'case_record'] as $table) {
            foreach ([
                'object_uuid' => 'uuid',
                'object_slug' => 'slug',
                'object_created_at' => 'created_at',
                'object_modified_at' => 'modified_at',
                'object_created_by' => 'created_by',
                'object_modified_by' => 'modified_by',
            ] as $from => $to) {
                $this->renameColumnIfPresent($table, $from, $to);
            }
        }

        foreach ([
            'object_active' => 'active',
            'object_enabled' => 'enabled',
            'object_status' => 'status',
        ] as $from => $to) {
            $this->renameColumnIfPresent('case_record', $from, $to);
        }

        $this->renameIndexIfPresent('uniq_case_draft_object_uuid', 'uniq_case_draft_uuid');
        $this->renameIndexIfPresent('uniq_case_object_uuid', 'uniq_case_uuid');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'The flat Objecting physical-column canon is a forward-only schema transition.',
        );
    }

    private function renameColumnIfPresent(string $table, string $from, string $to): void
    {
        $fromExists = 1 === (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_name = ?",
            [$table, $from],
        );
        $toExists = 1 === (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_name = ?",
            [$table, $to],
        );

        if ($fromExists && !$toExists) {
            $this->addSql(sprintf('ALTER TABLE %s RENAME COLUMN %s TO %s', $table, $from, $to));
        }
    }

    private function renameIndexIfPresent(string $from, string $to): void
    {
        $fromExists = 1 === (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM pg_indexes WHERE schemaname = 'public' AND indexname = ?",
            [$from],
        );

        if ($fromExists) {
            $this->addSql(sprintf('ALTER INDEX %s RENAME TO %s', $from, $to));
        }
    }
}
