<?php

declare(strict_types=1);

namespace App\Casing\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260821041500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create or safely adopt durable Casing information requests.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Casing migration supports PostgreSQL only.',
        );

        $this->addSql('CREATE TABLE IF NOT EXISTS case_information_request (
            id SERIAL NOT NULL,
            case_id INT NOT NULL,
            question TEXT NOT NULL,
            answer TEXT DEFAULT NULL,
            requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            answered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');

        foreach (['case_id', 'question', 'requested_at'] as $column) {
            $this->abortIf(
                !$this->columnExists('case_information_request', $column),
                sprintf('Casing adoption requires case_information_request.%s.', $column),
            );
        }

        $this->addIndexIfMissing(
            'idx_case_information_request_case_answered',
            'CREATE INDEX idx_case_information_request_case_answered ON case_information_request (case_id, answered_at)',
        );
        $this->addConstraintIfMissing(
            'case_information_request',
            'fk_case_information_request_case',
            'ALTER TABLE case_information_request ADD CONSTRAINT fk_case_information_request_case FOREIGN KEY (case_id) REFERENCES case_record (id) ON DELETE RESTRICT NOT DEFERRABLE',
        );
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Case information requests are durable business records.');
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
        if (0 === (int) $this->connection->fetchOne("SELECT COUNT(*) FROM pg_indexes WHERE schemaname = 'public' AND indexname = ?", [$name])) {
            $this->addSql($sql);
        }
    }

    private function addConstraintIfMissing(string $table, string $name, string $sql): void
    {
        if (0 === (int) $this->connection->fetchOne("SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = 'public' AND table_name = ? AND constraint_name = ?", [$table, $name])) {
            $this->addSql($sql);
        }
    }
}
