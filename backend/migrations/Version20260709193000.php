<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seed a single user account (passwordless auth uses email + one-time code,
 * so no password is stored).
 */
final class Version20260709193000 extends AbstractMigration
{
    private const SEED_EMAIL = 'user@example.com';

    public function getDescription(): string
    {
        return 'Seed one user account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'INSERT INTO "user" (id, email, roles) VALUES (nextval(\'user_id_seq\'), :email, :roles)',
            ['email' => self::SEED_EMAIL, 'roles' => '["ROLE_USER"]']
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM "user" WHERE email = :email', ['email' => self::SEED_EMAIL]);
    }
}
