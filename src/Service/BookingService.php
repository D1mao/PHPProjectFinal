<?php

namespace App\Service;

use App\Dto\BookingRequest;
use App\Entity\Booking;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\RoomRepository;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use App\Enum\BookingStatusEnum;
use App\Repository\UserRepository;
use Exception;

class BookingService
{
    public function __construct(
        private BookingRepository $bookingRepository,
        private RoomRepository $roomRepository,
        private UserRepository $userRepository,
        private BookingNotificationService $notificationService,
        private LoggerInterface $logger
    ) {}

    public function createBooking(
        User $createdBy,
        BookingRequest $dto
    ): ?Booking {
        $room = $this->roomRepository->findById($dto->roomId);

        $booking = new Booking();
        $booking->setCreatedBy($createdBy);
        $booking->setRoom($room);
        $booking->setStartAt($dto->startAt);
        $booking->setEndAt($dto->endAt);
        $booking->setStatus(BookingStatusEnum::ACTIVE->value);

        foreach ($dto->participants as $participant) {
            $booking->addParticipant($this->userRepository->findById($participant));
        }

        $this->bookingRepository->save($booking);

        $this->notificationService->sendBookingConfirmation($booking);
        
        $this->logger->info('Бронь создана', [
            'bookingId' => $booking->getId(),
            'roomId' => $room->getId(),
            'userId' => $createdBy->getId()
        ]);
        
        return $booking;
    }

    public function updateBooking(int $bookingId, array $data): bool
    {
        $booking = $this->bookingRepository->findById($bookingId);
        if (!$booking) {
            return false;
        }

        $wasUpdated = false;

        if (isset($data['startAt']) && (new DateTimeImmutable($data['startAt'])) instanceof \DateTimeImmutable) {
            foreach ($booking->getRoom()->getBookings() as $booking) {
                if ($data['startAt'] < $booking->getEndAt()) {
                    throw new Exception('Переговорная уже занята на это время');
                }
            }
            
            $booking->setStartAt($data['startAt']);
            $wasUpdated = true;
        }

        if (isset($data['endAt']) && (new DateTimeImmutable($data['endAt'])) instanceof \DateTimeImmutable) {
            foreach ($booking->getRoom()->getBookings() as $booking) {
                if ($data['endAt'] > $booking->getStartAt()) {
                    throw new Exception('Переговорная уже занята на это время');
                }
            }

            $booking->setEndAt($data['endAt']);
            $wasUpdated = true;
        }

        if (isset($data['participants']) && is_array($data['participants'])) {
            foreach ($booking->getParticipants() as $participant) {
                $booking->removeParticipant($participant);
            }

            foreach ($data['participants'] as $participant) {
                $booking->addParticipant($this->userRepository->find($participant));
            }
            $wasUpdated = true;
        }

        if ($wasUpdated) {
            $this->bookingRepository->save($booking);
            
            $this->notificationService->sendBookingUpdateNotification($booking);
            
            $this->logger->info('Бронь обновлена', ['bookingId' => $bookingId]);
        }

        return $wasUpdated;
    }

    public function getUserActiveBookings(User $user): array
    {
        return $this->bookingRepository->findUserActiveBookings($user);
    }

    public function getAllActiveBookings(): array
    {
        return $this->bookingRepository->findAllActive();
    }

    public function getAllBookingsIncludingArchived(): array
    {
        return $this->bookingRepository->findAllIncludingArchived();
    }

    public function cancelBooking(int $bookingId, User $user): bool
    {
        $booking = $this->bookingRepository->findById($bookingId);
        if (!$booking || $booking->getCreatedBy()->getId() !== $user->getId()) {
            return false;
        }

        $booking->setStatus(BookingStatusEnum::CANCELLED->value);
        $this->bookingRepository->save($booking);

        $this->notificationService->sendBookingCancellationNotification($booking);
        
        $this->logger->info('Бронь отменена', ['bookingId' => $bookingId, 'userId' => $user->getId()]);
        
        return true;
    }

    public function getBookingById(int $id): ?Booking
    {
        return $this->bookingRepository->findOneBy(['id' => $id, 'status' => 'active']);
    }

    public function archiveOldBookings(): int
    {
        $thresholdDate = new \DateTime();
        $archivedCount = $this->bookingRepository->archiveOldBookings($thresholdDate);
        
        $this->logger->info('Старые брони заархивированы', ['count' => $archivedCount]);
        
        return $archivedCount;
    }

    public function archiveBookingsOlderThan(\DateTime $thresholdDate): int
    {
        $archivedCount = $this->bookingRepository->archiveOldBookings($thresholdDate);
        
        $this->logger->info('Брони, с датой после указанной заархивированы', [
            'count' => $archivedCount,
            'threshold' => $thresholdDate->format('Y-m-d H:i:s')
        ]);
        
        return $archivedCount;
    }
}