<?php

namespace App\Controller;

use App\Service\RoomService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/rooms')]
class RoomController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(RoomService $roomService, SerializerInterface $serializer): JsonResponse
    {
        $rooms = $roomService->getAllRooms();

        $data = $serializer->serialize($rooms, 'json', ['groups' => 'return']);

        return $this->json(json_decode($data));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id, RoomService $roomService, SerializerInterface $serializer): JsonResponse
    {
        $room = $roomService->getRoomById($id);

        if (!$room) {
            return $this->json(['error' => 'Переговорная не найдена'], 404);
        }

        $data = $serializer->serialize($room, 'json', ['groups' => ['return+', 'return_for_room']]);

        return $this->json(json_decode($data));
    }
}
