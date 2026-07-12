<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CodeMailer
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    public function sendCode(string $email, string $code): void
    {
        // Mock "email": append to var/login_codes.log so the code can be read in dev
        $line = sprintf("[%s] login code for %s: %s\n", date('c'), $email, $code);
        file_put_contents($this->projectDir.'/var/login_codes.log', $line, FILE_APPEND);
    }
}