<?php

namespace App\Service;

use App\Dto\RegisterRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private LoggerInterface $logger
    ) {}

    public function createUser(RegisterRequest $dto): User
    {
        $user = new User();
        $user->setEmail($dto->email);
        $user->setFullName($dto->fullName);
        $user->setRoles(['ROLE_USER']);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->password);
        $user->setPassword($hashedPassword);

        $this->userRepository->save($user);
        $this->logger->info('Пользователь создан', ['email' => $user->getEmail(), 'id' => $user->getId()]);
        
        return $user;
    }

    public function updateUser(int $userId, array $data): bool
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            return false;
        }

        if (isset($data['fullName']) && is_string($data['fullName'])) {
            $user->setFullName($data['fullName']);
        }

        if (isset($data['roles']) && is_array($data['roles'])) {
            $user->setRoles($data['roles']);
        }

        $this->userRepository->save($user);
        $this->logger->info('Пользователь обновлён', ['userId' => $userId]);

        return true;
    }

    public function getUserById(int $id): ?User
    {
        return $this->userRepository->findById($id);
    }

    public function getUserByEmail(string $email): ?User
    {
        return $this->userRepository->findByEmail($email);
    }

    public function getAllUsers(): array
    {
        return $this->userRepository->findAllUsers();
    }

    public function deleteUser(int $userId): bool
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            return false;
        }

        $this->userRepository->delete($user);
        $this->logger->info('Пользователь удалён', ['userId' => $userId]);
        
        return true;
    }
}