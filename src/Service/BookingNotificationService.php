<?php
// src/Service/BookingNotificationService.php

namespace App\Service;

use App\Entity\Booking;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class BookingNotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $fromEmail
    ) {}

    public function sendBookingConfirmation(Booking $booking): void
    {
        try {
            $user = $booking->getCreatedBy();
            $participants = $booking->getParticipants();
            
            $recipients = array_merge([$user], $participants->toArray());
            foreach ($recipients as $recipient) {
                $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, 'Система бронирования'))
                ->to($recipient->getEmail())
                ->subject('Ваше бронирование подтверждено')
                ->htmlTemplate('email/booking_confirmation.html.twig')
                ->context([
                    'booking' => $booking,
                    'user' => $recipient,
                    'room' => $booking->getRoom(),
                    'participants' => $recipients
                ]);

                $this->mailer->send($email);
                
                $this->logger->info('Отправлено письмо подтверждения брони', [
                    'booking_id' => $booking->getId(),
                    'user_email' => $recipient->getEmail()
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Не удалось отправить письмо подтверждения брони', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendBookingUpdateNotification(Booking $booking): void
    {
        try {
            $user = $booking->getCreatedBy();
            $participants = $booking->getParticipants();
            
            $recipients = array_merge([$user], $participants->toArray());
            
            foreach ($recipients as $recipient) {
                $email = (new TemplatedEmail())
                    ->from(new Address($this->fromEmail, 'Система бронирования'))
                    ->to($recipient->getEmail())
                    ->subject('Изменение бронирования')
                    ->htmlTemplate('email/booking_updated.html.twig')
                    ->context([
                        'booking' => $booking,
                        'user' => $recipient,
                        'room' => $booking->getRoom(),
                        'participants' => $recipients,
                    ]);

                $this->mailer->send($email);
            }
            
            $this->logger->info('Отправлено письмо изменения брони', [
                'booking_id' => $booking->getId(),
                'recipient_count' => count($recipients)
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Не удалось отправить письмо изменения брони', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendBookingCancellationNotification(Booking $booking): void
    {
        try {
            $user = $booking->getCreatedBy();
            $participants = $booking->getParticipants();
            
            $recipients = array_merge([$user], $participants->toArray());
            
            foreach ($recipients as $recipient) {
                $email = (new TemplatedEmail())
                    ->from(new Address($this->fromEmail, 'Система бронирования'))
                    ->to($recipient->getEmail())
                    ->subject('Бронирование отменено')
                    ->htmlTemplate('email/booking_cancelled.html.twig')
                    ->context([
                        'booking' => $booking,
                        'user' => $recipient,
                        'room' => $booking->getRoom(),
                        'participants' => $recipients,
                    ]);

                $this->mailer->send($email);
            }
            
            $this->logger->info('Отправлено письмо отмены брони', [
                'booking_id' => $booking->getId(),
                'recipient_count' => count($recipients)
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Не удалось отправить письмо отмены брони', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }
}