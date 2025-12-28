<?php

namespace App\Repository;

use App\Entity\Room;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Room>
 */
class RoomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Room::class);
    }

    public function save(Room $room): void
    {
        $this->getEntityManager()->persist($room);
        $this->getEntityManager()->flush();
    }

    public function findById(int $id): ?Room
    {
        return $this->find($id);
    }

    public function findAllRooms(): array
    {
        return $this->findAll();
    }

    public function delete(Room $room): void
    {
        $this->getEntityManager()->remove($room);
        $this->getEntityManager()->flush();
    }
}
