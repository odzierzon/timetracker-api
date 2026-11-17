<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenIdentifier;

    #[ORM\Column]
    private string $password;

    #[ORM\Column]
    private bool $admin;

    #[ORM\OneToOne(targetEntity: Employee::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Employee $employee;

    public function __construct(string $email, bool $admin, ?Employee $employee = null)
    {
        if ($admin === ($employee !== null)) {
            throw new \InvalidArgumentException('A user must be either an admin or linked to an employee.');
        }

        $this->email = $email;
        $this->tokenIdentifier = bin2hex(random_bytes(32));
        $this->admin = $admin;
        $this->employee = $employee;
    }

    public function getUserIdentifier(): string { return $this->tokenIdentifier; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): void { $this->email = $email; }
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): void { $this->password = $password; }
    public function getRoles(): array { return [$this->admin ? 'ROLE_ADMIN' : 'ROLE_EMPLOYEE']; }
    public function getEmployee(): ?Employee { return $this->employee; }
    public function eraseCredentials(): void {}
}
