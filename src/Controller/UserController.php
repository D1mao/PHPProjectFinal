<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Entity\User;
use App\Service\UserService;
use Symfony\Component\Serializer\SerializerInterface;

class UserController extends AbstractController
{
    #[Route('/api/me', name: 'my_data', methods: ['GET'])]
    public function me(#[CurrentUser()] ?User $user, SerializerInterface $serializer): JsonResponse
    {
        $data = $serializer->serialize($user, 'json', ['groups' => 'return']);

        return $this->json(json_decode($data));
    }

    #[Route('/api/users', methods: ['GET'])]
    public function listUsers(#[CurrentUser()] User $user, UserService $userService, SerializerInterface $serializer): JsonResponse
    {
        $users = $userService->getAllUsers($user);
        $data = $serializer->serialize($users, 'json', ['groups' => 'return_for_booking']);
        return $this->json(json_decode($data));
    }

}