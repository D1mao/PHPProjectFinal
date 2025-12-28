<?php

namespace App\Service;

use App\Dto\RoomRequest;
use App\Entity\Room;
use App\Repository\RoomRepository;
use Psr\Log\LoggerInterface;

class RoomService
{
    public function __construct(
        private RoomRepository $roomRepository,
        private LoggerInterface $logger
    ) {}

    public function createRoom(RoomRequest $dto): Room
    {
        $room = new Room();
        $room->setName($dto->name);
        $room->setCapacity($dto->capacity);
        $room->setLocation($dto->location);
        $room->setDescription($dto->description);

        $this->roomRepository->save($room);
        
        $this->logger->info('Переговорная создана', ['roomId' => $room->getId(), 'name' => $room->getName()]);
        
        return $room;
    }

    public function getRoomById(int $id): ?Room
    {
        return $this->roomRepository->findById($id);
    }

    public function getAllRooms(): array
    {
        return $this->roomRepository->findAllRooms();
    }

    public function updateRoom(int $roomId, array $data): bool
    {
        $room = $this->roomRepository->findById($roomId);
        if (!$room) {
            return false;
        }

        if (isset($data['name']) && is_string($data['name'])) {
            $room->setName($data['name']);
        }
        if (isset($data['capacity']) && is_int($data['capacity'])) {
            $room->setCapacity($data['capacity']);
        }
        if (isset($data['location']) && is_string($data['location'])) {
            $room->setLocation($data['location']);
        }
        if (isset($data['description']) && is_string($data['description'])) {
            $room->setDescription($data['description']);
        }

        $this->roomRepository->save($room);
        $this->logger->info('Переговорная обновлена', ['roomId' => $roomId]);
        
        return true;
    }

    public function deleteRoom(int $roomId): bool
    {
        $room = $this->roomRepository->findById($roomId);
        if (!$room) {
            return false;
        }

        $this->roomRepository->delete($room);
        $this->logger->info('Переговорная удалена', ['roomId' => $roomId]);
        
        return true;
    }
}