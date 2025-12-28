<?php

namespace App\Controller;

use App\Dto\BookingRequest;
use App\Entity\Booking;
use App\Entity\User;
use App\Service\BookingService;
use App\Service\RoomService;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

#[Route('/api/bookings')]
class BookingController extends AbstractController
{
    #[Route('/create', methods: ['POST'])]
    public function create(
        Request $request,
        ValidatorInterface $validator,
        RoomService $roomService,
        BookingService $bookingService,
        #[CurrentUser] User $user
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);

        $dto = new BookingRequest(
            $data['startAt'] ?? '',
            $data['endAt'] ?? '',
            $data['roomId'] ?? 0,
            $data['participants'] ?? []
        );

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string)$errors], 400);
        }

        $room = $roomService->getRoomById($dto->roomId);
        if (!$room) {
            return $this->json(['error' => 'Переговорная не найдена'], 404);
        }

        if ($dto->startAt >= $dto->endAt) {
            return $this->json(['error' => 'Неправильное время'], 400);
        }

        foreach ($room->getBookings() as $booking) {
            if (
                ($dto->startAt < $booking->getEndAt() && $dto->endAt > $booking->getStartAt())
            ) {
                return $this->json(['error' => 'Переговорная уже занята на это время'], 409);
            }
        }

        if (count($data['participants']) > $room->getCapacity()) {
            return $this->json(['error' => 'Переговорная не рассчитана на такое количество людей'], 400);
        }

        $booking = $bookingService->createBooking($user, $dto);

        return $this->json(['id' => $booking->getId()], 201);
    }

    #[Route('/my', methods: ['GET'])]
    public function myBookings(#[CurrentUser] User $user, SerializerInterface $serializer, BookingService $bookingService): JsonResponse
    {
        $data = $serializer->serialize($bookingService->getUserActiveBookings($user), 'json', ['groups' => 'return']);

        return $this->json(json_decode($data));
    }

    #[Route('/update/{id}', methods:['PATCH'])]
    public function update(int $id, Request $request, BookingService $bookingService, #[CurrentUser] User $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $booking = $bookingService->getBookingById($id);

        if (!$booking) {
            return $this->json(['error' => 'Бронь не найдена'], 404);
        }

        if ($booking->getCreatedBy()?->getId() !== $user->getId()) {
            return $this->json(['error' => 'Вы не можете изменить эту бронь'], 403);
        }

        try {
            $bookingService->updateBooking($booking->getId(), $data);
        } catch (Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json(['status' => 'updated']);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function cancel(int $id, BookingService $bookingService, #[CurrentUser] User $user): JsonResponse
    {
        $booking = $bookingService->getBookingById($id);

        if (!$booking) {
            return $this->json(['error' => 'Бронь не найдена'], 404);
        }

        if ($booking->getCreatedBy()?->getId() !== $user->getId()) {
            return $this->json(['error' => 'Вы не можете отменить эту бронь'], 403);
        }

        $bookingService->cancelBooking($booking->getId(), $user);

        return $this->json(['status' => 'deleted']);
    }
}
