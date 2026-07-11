<?php

namespace App\Service;

use App\Entity\ApiToken;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class AuthService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly CodeMailer $mailer,
    ) {
    }

    public function requestCode(string $email): void
    {
        $user = $this->users->findOneBy(['email' => $email]);
        if (null === $user) {
            return; // don't reveal whether the email exists
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->setLoginCode(hash('sha256', $code)); // column stores the hashed code
        $user->setLoginCodeExpiresAt(new \DateTimeImmutable('+10 minutes'));
        $this->em->flush();

        $this->mailer->sendCode($email, $code);
    }

    public function verifyCode(string $email, string $code): ?ApiToken
    {
        $user = $this->users->findOneBy(['email' => $email]);

        if (null === $user
            || null === $user->getLoginCode()
            || $user->getLoginCodeExpiresAt() < new \DateTimeImmutable()
            || !hash_equals($user->getLoginCode(), hash('sha256', $code))
        ) {
            return null;
        }

        // one-time: invalidate the code once used
        $user->setLoginCode(null);
        $user->setLoginCodeExpiresAt(null);

        $token = (new ApiToken())
            ->setUser($user)
            ->setToken(bin2hex(random_bytes(32)))
            ->setExpiresAt(new \DateTimeImmutable('+7 days'));

        $this->em->persist($token);
        $this->em->flush();

       // var_dump('token val:', $token->getToken());
        return $token;
    }
}