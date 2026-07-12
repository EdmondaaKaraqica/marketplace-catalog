<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ApiToken;
use PHPUnit\Framework\TestCase;

final class ApiTokenTest extends TestCase
{
    public function testTokenInThePastIsExpired(): void
    {
        $token = (new ApiToken())->setExpiresAt(new \DateTimeImmutable('-1 minute'));

        self::assertTrue($token->isExpired());
    }

    public function testTokenInTheFutureIsNotExpired(): void
    {
        $token = (new ApiToken())->setExpiresAt(new \DateTimeImmutable('+1 hour'));

        self::assertFalse($token->isExpired());
    }
}
