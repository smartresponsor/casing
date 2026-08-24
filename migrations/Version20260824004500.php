<?php

declare(strict_types=1);

namespace App\Casing\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260824004500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create or safely adopt durable Casing lifecycle outbox messages.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Casing outbox migration supports PostgreSQL only.',
        );

        if (!$this->tableExists('case_outbox_message')) {
            $this->addSql('CREATE TABLE case_outbox_message (
                id SERIAL NOT NULL,
                slug UUID NOT NULL,
                aggregate_id VARCHAR(64) NOT NULL,
                event_type VARCHAR(128) NOT NULL,
                payload JSONB NOT NULL,
                occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                dispatched BOOLEAN DEFAULT FALSE NOT NULL,
                dispatched_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                attempts INT DEFAULT 0 NOT NULL,
                available_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )');
            $this->addSql('CREATE UNIQUE INDEX uniq_case_outbox_slug ON case_outbox_message (slug)');
            $this->addSql('CREATE INDEX idx_case_outbox_pending ON case_outbox_message (dispatched, available_at, id)');

            return;
        }

        foreach (['id', 'slug', 'aggregate_id', 'event_type', 'payload', 'occurred_at', 'dispatched', 'attempts'] as $column) {
            $this->abortIf(
                !$this->columnExists('case_outbox_message', $column),
                sprintf('Casing outbox adoption requires case_outbox_message.%s.', $column),
            );
        }

        if (!$this->indexExists('uniq_case_outbox_slug')) {
            $this->addSql('CREATE UNIQUE INDEX uniq_case_outbox_slug ON case_outbox_message (slug)');
        }
        if (!$this->indexExists('idx_case_outbox_pending')) {
            $this->addSql('CREATE INDEX idx_case_outbox_pending ON case_outbox_message (dispatched, available_at, id)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Casing outbox messages are durable lifecycle records.');
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

    private function indexExists(string $name): bool
    {
        return 1 === (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM pg_indexes WHERE schemaname = 'public' AND indexname = ?",
            [$name],
        );
    }
}
