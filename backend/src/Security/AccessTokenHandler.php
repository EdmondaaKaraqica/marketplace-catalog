<?php

namespace App\Security;

use App\Repository\ApiTokenRepository;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class AccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(private readonly ApiTokenRepository $tokens)
    {
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        $token = $this->tokens->findOneBy(['token' => $accessToken]);

        if (null === $token || $token->isExpired()) {
            throw new BadCredentialsException('Invalid or expired token.');
        }

        return new UserBadge($token->getUser()->getUserIdentifier());
    }
}