<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class RoomRequest
{
    #[Assert\NotBlank()]
    public string $name;

    #[Assert\NotBlank()]
    #[Assert\Type('integer')]
    public int $capacity;

    #[Assert\NotBlank()]
    public string $location;

    public string $description;

    public function __construct(string $name = '', int $capacity = 1, string $location = '', string $description = '')
    {
        $this->name = $name;
        $this->capacity = $capacity;
        $this->location = $location;
        $this->description = $description;
    }
}