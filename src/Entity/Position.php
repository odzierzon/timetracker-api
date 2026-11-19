<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\PositionRepository;

#[ORM\Entity(repositoryClass: PositionRepository::class)]
#[ORM\Table(name: 'positions')]
class Position extends NamedEntity
{
}
