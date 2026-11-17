<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116185050 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create organization and user tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE employees (id INT AUTO_INCREMENT NOT NULL, firstName VARCHAR(100) NOT NULL, lastName VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, birthday DATE DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, team_id INT NOT NULL, position_id INT NOT NULL, UNIQUE INDEX UNIQ_BA82C300E7927C74 (email), INDEX IDX_BA82C300296CD8AE (team_id), INDEX IDX_BA82C300DD842E46 (position_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE positions (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, description LONGTEXT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE teams (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, description LONGTEXT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, `admin` TINYINT NOT NULL, employee_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), UNIQUE INDEX UNIQ_1483A5E98C03F15C (employee_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE employees ADD CONSTRAINT FK_BA82C300296CD8AE FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE employees ADD CONSTRAINT FK_BA82C300DD842E46 FOREIGN KEY (position_id) REFERENCES positions (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E98C03F15C FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE employees DROP FOREIGN KEY FK_BA82C300296CD8AE');
        $this->addSql('ALTER TABLE employees DROP FOREIGN KEY FK_BA82C300DD842E46');
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E98C03F15C');
        $this->addSql('DROP TABLE employees');
        $this->addSql('DROP TABLE positions');
        $this->addSql('DROP TABLE teams');
        $this->addSql('DROP TABLE users');
    }
}
