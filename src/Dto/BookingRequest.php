<?php

namespace App\Dto;

use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

class BookingRequest
{
    #[Assert\NotBlank]
    #[Assert\Type('DateTimeImmutable')]
    public DateTimeImmutable $startAt;

    #[Assert\NotBlank]
    #[Assert\Type('DateTimeImmutable')]
    public DateTimeImmutable $endAt;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    public int $roomId;

    /** @var int[] */
    #[Assert\All([
        new Assert\Type('integer')
    ])]
    public array $participants = [];

    public function __construct(string $startAt = '', string $endAt = '', int $roomId = 0, array $participants = [])
    {
        $this->startAt = new DateTimeImmutable($startAt);
        $this->endAt = new DateTimeImmutable($endAt);
        $this->roomId = $roomId;
        $this->participants = $participants;
    }
}
