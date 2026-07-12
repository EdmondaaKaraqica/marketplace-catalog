<?php

namespace App\Controller;

use App\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    #[Route('/api/login/request', name: 'api_login_request', methods: ['POST'])]
    public function request(Request $request, AuthService $auth): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $email = trim((string) ($data['email'] ?? ''));
        if ('' === $email) {
            return new JsonResponse(['error' => 'Email is required'], 400);
        }

        $auth->requestCode($email);

        // Always the same response, so we don't leak which emails exist.
        return new JsonResponse(['message' => 'If that email exists, a code has been sent.']);
    }

    #[Route('/api/login/verify', name: 'api_login_verify', methods: ['POST'])]
    public function verify(Request $request, AuthService $auth): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $email = trim((string) ($data['email'] ?? ''));
        $code = trim((string) ($data['code'] ?? ''));

        $token = $auth->verifyCode($email, $code);
        if (null === $token) {
            return new JsonResponse(['error' => 'Invalid email or code'], 401);
        }

        return new JsonResponse(['token' => $token->getToken()]);
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        return new JsonResponse(['email' => $this->getUser()->getUserIdentifier()]);
    }
}