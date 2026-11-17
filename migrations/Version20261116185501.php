<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116185501 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Give each account a stable, non-reusable token identity.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD tokenIdentifier VARCHAR(64) DEFAULT NULL');
        $this->addSql('UPDATE users SET tokenIdentifier = LOWER(HEX(RANDOM_BYTES(32)))');
        $this->addSql('ALTER TABLE users MODIFY tokenIdentifier VARCHAR(64) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E912AACE0 ON users (tokenIdentifier)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_1483A5E912AACE0 ON users');
        $this->addSql('ALTER TABLE users DROP tokenIdentifier');
    }
}
