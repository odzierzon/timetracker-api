<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'employees')]
class Employee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $firstName;

    #[ORM\Column(length: 100)]
    private string $lastName;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Team $team;

    #[ORM\ManyToOne(targetEntity: Position::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Position $position;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $birthday;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $phone;

    public function update(string $firstName, string $lastName, string $email, Team $team, Position $position, ?\DateTimeImmutable $birthday, ?string $phone): void
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->team = $team;
        $this->position = $position;
        $this->birthday = $birthday;
        $this->phone = $phone;
    }

    public function getId(): ?int { return $this->id; }
    public function getFirstName(): string { return $this->firstName; }
    public function getLastName(): string { return $this->lastName; }
    public function getEmail(): string { return $this->email; }
    public function getTeam(): Team { return $this->team; }
    public function getPosition(): Position { return $this->position; }
    public function getBirthday(): ?\DateTimeImmutable { return $this->birthday; }
    public function getPhone(): ?string { return $this->phone; }
}
