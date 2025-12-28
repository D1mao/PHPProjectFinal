<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class RegisterRequest
{
    #[Assert\NotBlank()]
    #[Assert\Length(min: 5, max: 50)]
    public string $fullName;

    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;

    #[Assert\NotBlank]
    #[Assert\Length(min: 6, max: 30)]
    public string $password;

    public function __construct(string $email = '', string $password = '', string $fullName = '')
    {
        $this->email = $email;
        $this->password = $password;
        $this->fullName = $fullName;
    }
}
