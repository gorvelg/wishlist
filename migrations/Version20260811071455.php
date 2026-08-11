<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260811071455 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product_user DROP INDEX UNIQ_PRODUCT_USER, ADD INDEX IDX_7BF4E84584665A (product_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PRODUCT_USER ON product_user (product_id, user_id)');
        $this->addSql('ALTER TABLE wishlist ADD baby_name VARCHAR(255) DEFAULT NULL, ADD due_date DATE NOT NULL, ADD parents_names VARCHAR(255) NOT NULL, ADD message LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product_user DROP INDEX IDX_7BF4E84584665A, ADD UNIQUE INDEX UNIQ_PRODUCT_USER (product_id)');
        $this->addSql('DROP INDEX UNIQ_PRODUCT_USER ON product_user');
        $this->addSql('ALTER TABLE wishlist DROP baby_name, DROP due_date, DROP parents_names, DROP message');
    }
}
