<?php

namespace App\Controller;

use App\Dto\RegisterRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\UserService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class AuthController extends AbstractController
{
    #[Route('/api/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        ValidatorInterface $validator,
        UserService $userService,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        
        $dto = new RegisterRequest(
            $data['email'] ?? '',
            $data['password'] ?? '',
            $data['fullName'] ?? '',
        );

        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
           return $this->json(['errors' => (string)$errors], 400);
        }

        $user = $userService->createUser($dto);

        $token = $jwtManager->create($user);

        return $this->json([
            'status' => 'ok',
            'message' => 'Пользователь успешно зарегестрирован',
            'token' => $token,
        ]);
    }
}