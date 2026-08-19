<?php

declare(strict_types=1);

namespace App\Casing\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create or safely adopt the initial Casing draft and durable case tables.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Casing migration supports PostgreSQL only.',
        );

        $this->addSql("CREATE TABLE IF NOT EXISTS case_draft (
            id SERIAL NOT NULL,
            draft_reference VARCHAR(26) NOT NULL,
            actor_id VARCHAR(190) NOT NULL,
            business_context VARCHAR(64) NOT NULL,
            catalog_category_id INT DEFAULT NULL,
            description TEXT DEFAULT NULL,
            subject_references JSONB NOT NULL DEFAULT '[]'::jsonb,
            supplied_facts JSONB NOT NULL DEFAULT '{}'::jsonb,
            contribution_data JSONB NOT NULL DEFAULT '{}'::jsonb,
            attachment_references JSONB NOT NULL DEFAULT '[]'::jsonb,
            current_step VARCHAR(64) NOT NULL DEFAULT 'context',
            object_uuid BYTEA NOT NULL,
            object_slug VARCHAR(190) NOT NULL,
            object_created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            object_modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            object_created_by VARCHAR(190) DEFAULT NULL,
            object_modified_by VARCHAR(190) DEFAULT NULL,
            PRIMARY KEY(id)
        )");

        $this->addSql("CREATE TABLE IF NOT EXISTS case_record (
            id SERIAL NOT NULL,
            case_reference VARCHAR(26) NOT NULL,
            source_draft_reference VARCHAR(26) DEFAULT NULL,
            actor_id VARCHAR(190) NOT NULL,
            business_context VARCHAR(64) NOT NULL,
            catalog_category_id INT NOT NULL,
            description TEXT DEFAULT NULL,
            subject_references JSONB NOT NULL DEFAULT '[]'::jsonb,
            supplied_facts JSONB NOT NULL DEFAULT '{}'::jsonb,
            contribution_data JSONB NOT NULL DEFAULT '{}'::jsonb,
            attachment_references JSONB NOT NULL DEFAULT '[]'::jsonb,
            status VARCHAR(32) NOT NULL,
            object_uuid BYTEA NOT NULL,
            object_slug VARCHAR(190) NOT NULL,
            object_created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            object_modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            object_created_by VARCHAR(190) DEFAULT NULL,
            object_modified_by VARCHAR(190) DEFAULT NULL,
            object_active BOOLEAN NOT NULL DEFAULT true,
            object_enabled BOOLEAN NOT NULL DEFAULT true,
            object_status VARCHAR(64) NOT NULL,
            PRIMARY KEY(id)
        )");

        $required = [
            'case_draft' => ['draft_reference', 'actor_id', 'business_context', 'catalog_category_id', 'current_step'],
            'case_record' => ['case_reference', 'actor_id', 'business_context', 'catalog_category_id', 'status'],
        ];

        foreach ($required as $table => $columns) {
            foreach ($columns as $column) {
                $this->abortIf(
                    !$this->columnExists($table, $column),
                    sprintf('Casing adoption requires %s.%s.', $table, $column),
                );
            }
        }

        $this->addIndexIfMissing('uniq_case_draft_reference', 'CREATE UNIQUE INDEX uniq_case_draft_reference ON case_draft (draft_reference)');
        $this->addIndexIfMissing('uniq_case_reference', 'CREATE UNIQUE INDEX uniq_case_reference ON case_record (case_reference)');
        $this->addIndexIfMissing('uniq_case_draft_object_uuid', 'CREATE UNIQUE INDEX uniq_case_draft_object_uuid ON case_draft (object_uuid)');
        $this->addIndexIfMissing('uniq_case_object_uuid', 'CREATE UNIQUE INDEX uniq_case_object_uuid ON case_record (object_uuid)');
        $this->addIndexIfMissing('idx_case_draft_actor_context', 'CREATE INDEX idx_case_draft_actor_context ON case_draft (actor_id, business_context)');
        $this->addIndexIfMissing('idx_case_actor_status', 'CREATE INDEX idx_case_actor_status ON case_record (actor_id, status)');

        if ($this->tableExists('category')) {
            $this->addConstraintIfMissing(
                'case_draft',
                'fk_case_draft_category',
                'ALTER TABLE case_draft ADD CONSTRAINT fk_case_draft_category FOREIGN KEY (catalog_category_id) REFERENCES category (id) ON DELETE RESTRICT NOT DEFERRABLE',
            );
            $this->addConstraintIfMissing(
                'case_record',
                'fk_case_category',
                'ALTER TABLE case_record ADD CONSTRAINT fk_case_category FOREIGN KEY (catalog_category_id) REFERENCES category (id) ON DELETE RESTRICT NOT DEFERRABLE',
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Casing case records are durable business data and this migration is intentionally irreversible.',
        );
    }

    private function tableExists(string $table): bool
    {
        return 1 === (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?",
            [$table],
        );
    }

    private function columnExists(string $table, string $column): bool
    {
        return 1 === (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_name = ?",
            [$table, $column],
        );
    }

    private function addIndexIfMissing(string $name, string $sql): void
    {
        $exists = 1 === (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM pg_indexes WHERE schemaname = 'public' AND indexname = ?",
            [$name],
        );

        if (!$exists) {
            $this->addSql($sql);
        }
    }

    private function addConstraintIfMissing(string $table, string $name, string $sql): void
    {
        $exists = 1 === (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = 'public' AND table_name = ? AND constraint_name = ?",
            [$table, $name],
        );

        if (!$exists) {
            $this->addSql($sql);
        }
    }
}
