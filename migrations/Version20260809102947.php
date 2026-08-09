<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260809102947 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE wishlist_owner (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, wishlist_id INT DEFAULT NULL, INDEX IDX_9BC58E84A76ED395 (user_id), INDEX IDX_9BC58E84FB8E54CD (wishlist_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE wishlist_owner ADD CONSTRAINT FK_9BC58E84A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE wishlist_owner ADD CONSTRAINT FK_9BC58E84FB8E54CD FOREIGN KEY (wishlist_id) REFERENCES wishlist (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE wishlist_owner DROP FOREIGN KEY FK_9BC58E84A76ED395');
        $this->addSql('ALTER TABLE wishlist_owner DROP FOREIGN KEY FK_9BC58E84FB8E54CD');
        $this->addSql('DROP TABLE wishlist_owner');
    }
}
