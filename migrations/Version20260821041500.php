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

        if (!$this->tableExists('case_information_request')) {
            $this->addSql('CREATE TABLE case_information_request (
            id SERIAL NOT NULL,
            case_id INT NOT NULL,
            question TEXT NOT NULL,
            answer TEXT DEFAULT NULL,
            requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            answered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
            $this->addSql('CREATE INDEX idx_case_information_request_case_answered ON case_information_request (case_id, answered_at)');
            $this->addSql('CREATE UNIQUE INDEX uniq_case_information_request_open_case ON case_information_request (case_id) WHERE answered_at IS NULL');
            $this->addSql('ALTER TABLE case_information_request ADD CONSTRAINT fk_case_information_request_case FOREIGN KEY (case_id) REFERENCES case_record (id) ON DELETE RESTRICT NOT DEFERRABLE');

            return;
        }

        foreach (['id', 'case_id', 'question', 'requested_at'] as $column) {
            $this->abortIf(
                !$this->columnExists('case_information_request', $column),
                sprintf('Casing adoption requires case_information_request.%s.', $column),
            );
        }

        $this->abortIf(!$this->columnTypeIs('case_information_request', 'id', ['integer']), 'Casing adoption requires case_information_request.id to be integer.');
        $this->abortIf(!$this->columnTypeIs('case_information_request', 'case_id', ['integer']), 'Casing adoption requires case_information_request.case_id to be integer.');
        $this->abortIf(!$this->columnTypeIs('case_information_request', 'question', ['text']), 'Casing adoption requires case_information_request.question to be text.');
        $this->abortIf(!$this->columnTypeIs('case_information_request', 'requested_at', ['timestamp without time zone']), 'Casing adoption requires case_information_request.requested_at to be timestamp without time zone.');

        if ($this->columnExists('case_information_request', 'answer')) {
            $this->abortIf(!$this->columnTypeIs('case_information_request', 'answer', ['text']), 'Casing adoption requires case_information_request.answer to be text.');
        } else {
            $this->addSql('ALTER TABLE case_information_request ADD COLUMN answer TEXT DEFAULT NULL');
        }

        if ($this->columnExists('case_information_request', 'answered_at')) {
            $this->abortIf(!$this->columnTypeIs('case_information_request', 'answered_at', ['timestamp without time zone']), 'Casing adoption requires case_information_request.answered_at to be timestamp without time zone.');
        } else {
            $this->addSql('ALTER TABLE case_information_request ADD COLUMN answered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        }

        $this->addIndexIfMissing(
            'idx_case_information_request_case_answered',
            'CREATE INDEX idx_case_information_request_case_answered ON case_information_request (case_id, answered_at)',
        );
        $this->abortIf(
            0 < (int) $this->connection->fetchOne('SELECT COUNT(*) FROM (SELECT case_id FROM case_information_request WHERE answered_at IS NULL GROUP BY case_id HAVING COUNT(*) > 1) duplicate_open_request'),
            'Casing adoption requires at most one open information request per case.',
        );
        $this->addIndexIfMissing(
            'uniq_case_information_request_open_case',
            'CREATE UNIQUE INDEX uniq_case_information_request_open_case ON case_information_request (case_id) WHERE answered_at IS NULL',
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

    /** @param list<string> $allowedTypes */
    private function columnTypeIs(string $table, string $column, array $allowedTypes): bool
    {
        $type = $this->connection->fetchOne(
            "SELECT data_type FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_name = ?",
            [$table, $column],
        );

        return is_string($type) && in_array($type, $allowedTypes, true);
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
