<?php

namespace App\Controller;

use App\Dto\RoomRequest;
use App\Repository\UserRepository;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Room;
use App\Service\RoomService;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin')]
class AdminController extends AbstractController
{
    #[Route('/users', methods: ['GET'])]
    public function listUsers(UserService $userService, SerializerInterface $serializer): JsonResponse
    {
        $users = $userService->getAllUsers();

        $data = $serializer->serialize($users, 'json', ['groups' => 'return']);

        return $this->json(json_decode($data));
    }

    #[Route('/users/update/{id}', methods: ['PATCH'])]
    public function updateUser(
        int $id,
        Request $request,
        UserService $userService,
    ): JsonResponse {
        $user = $userService->getUserById($id);

        if (!$user) {
            return $this->json(['error' => 'Пользователь не найден'], 404);
        }

        $data = json_decode($request->getContent(), true);

        $userService->updateUser($user->getId(), $data);

        return $this->json(['status' => 'updated']);
    }

    #[Route('/rooms/create', methods: ['POST'])]
    public function create(Request $request, ValidatorInterface $validator, RoomService $roomService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $dto = new RoomRequest(
            $data['name'] ?? '',
            $data['capacity'] ?? 0,
            $data['location'] ?? '',
            $data['description'] ?? ''
        );

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string)$errors], 400);
        }

        $room = $roomService->createRoom($dto);

        return $this->json(['id' => $room->getId()], 201);
    }

    #[Route('/rooms/update/{id}', methods: ['PATCH'])]
    public function update(int $id, Request $request, RoomService $roomService): JsonResponse
    {
        $room = $roomService->getRoomById($id);

        if (!$room) {
            return $this->json(['error' => 'Переговорная не найдена'], 404);
        }

        $data = json_decode($request->getContent(), true);

        $roomService->updateRoom($room->getId(), $data);

        return $this->json(['status' => 'updated']);
    }

    
    #[Route('/rooms/delete/{id}', methods: ['DELETE'])]
    public function delete(int $id, RoomService $roomService): JsonResponse
    {
        $room = $roomService->getRoomById($id);

        if (!$room) {
            return $this->json(['error' => 'Переговорная не найдена'], 404);
        }

        $roomService->deleteRoom($room->getId());

        return $this->json(['status' => 'deleted']);
    }
}
