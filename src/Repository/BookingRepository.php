<?php

namespace App\Repository;

use App\Entity\Booking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;
use App\Enum\BookingStatusEnum;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    public function save(Booking $booking): void
    {
        $this->getEntityManager()->persist($booking);
        $this->getEntityManager()->flush();
    }

    public function findById(int $id): ?Booking
    {
        return $this->find($id);
    }

    public function findAllActive(): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.status != :archived')
            ->setParameter('archived', BookingStatusEnum::ARCHIVED->value)
            ->getQuery()
            ->getResult();
    }

    public function findAllIncludingArchived(): array
    {
        return $this->findAll();
    }

    public function findUserActiveBookings(User $user): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.participants', 'u')
            ->where('(b.createdBy = :user OR u.id = :user)')
            ->andWhere('b.status != :archived')
            ->setParameter('user', $user)
            ->setParameter('archived', BookingStatusEnum::ARCHIVED->value)
            ->getQuery()
            ->getResult();
    }

    public function delete(Booking $booking): void
    {
        $this->getEntityManager()->remove($booking);
        $this->getEntityManager()->flush();
    }

    public function findBookingsToArchive(\DateTime $thresholdDate): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.endAt < :threshold')
            ->andWhere('b.status = :active')
            ->setParameter('threshold', $thresholdDate)
            ->setParameter('active', BookingStatusEnum::ACTIVE->value)
            ->getQuery()
            ->getResult();
    }

    public function archiveOldBookings(\DateTime $thresholdDate): int
    {
        $bookingsToArchive = $this->findBookingsToArchive($thresholdDate);
        $archivedCount = 0;

        foreach ($bookingsToArchive as $booking) {
            $booking->setStatus(BookingStatusEnum::ARCHIVED->value);
            $archivedCount++;
        }

        if ($archivedCount > 0) {
            $this->getEntityManager()->flush();
        }

        return $archivedCount;
    }
}
