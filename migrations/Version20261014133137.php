<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261014133137 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE agent (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(120) NOT NULL, email VARCHAR(180) NOT NULL, active BOOLEAN NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_268B9C9DE7927C74 ON agent (email)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__ticket AS SELECT id, title, description, priority, status, created_at FROM ticket');
        $this->addSql('DROP TABLE ticket');
        $this->addSql('CREATE TABLE ticket (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(120) NOT NULL, description CLOB NOT NULL, priority VARCHAR(16) NOT NULL, status VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, due_at DATETIME DEFAULT NULL, assignee_id INTEGER DEFAULT NULL, CONSTRAINT FK_97A0ADA359EC7D60 FOREIGN KEY (assignee_id) REFERENCES agent (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO ticket (id, title, description, priority, status, created_at) SELECT id, title, description, priority, status, created_at FROM __temp__ticket');
        $this->addSql('DROP TABLE __temp__ticket');
        $this->addSql('CREATE INDEX IDX_97A0ADA359EC7D60 ON ticket (assignee_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE agent');
        $this->addSql('CREATE TEMPORARY TABLE __temp__ticket AS SELECT id, title, description, priority, status, created_at FROM ticket');
        $this->addSql('DROP TABLE ticket');
        $this->addSql('CREATE TABLE ticket (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(120) NOT NULL, description CLOB NOT NULL, priority VARCHAR(16) NOT NULL, status VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO ticket (id, title, description, priority, status, created_at) SELECT id, title, description, priority, status, created_at FROM __temp__ticket');
        $this->addSql('DROP TABLE __temp__ticket');
    }
}
