<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        self::assertContains('ROLE_USER', (new User())->getRoles());
    }

    public function testGetRolesAreUnique(): void
    {
        $user = (new User())->setRoles(['ROLE_ADMIN', 'ROLE_USER']);

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], array_values($user->getRoles()));
    }

    public function testUserIdentifierIsTheEmail(): void
    {
        $user = (new User())->setEmail('user@example.com');

        self::assertSame('user@example.com', $user->getUserIdentifier());
    }
}
