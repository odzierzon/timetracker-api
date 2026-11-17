<?php

namespace App\Api;

use App\Entity\Employee;
use App\Entity\NamedEntity;

final class Output
{
    public static function named(NamedEntity $entity): array
    {
        return ['id' => $entity->getId(), 'name' => $entity->getName(), 'description' => $entity->getDescription()];
    }

    public static function employee(Employee $employee, bool $admin): array
    { 
        $data = [
            'id' => $employee->getId(),
            'firstName' => $employee->getFirstName(),
            'lastName' => $employee->getLastName(),
            'email' => $employee->getEmail(),
            'team' => self::named($employee->getTeam()),
            'position' => self::named($employee->getPosition()),
        ];
        if ($admin) {
            $data['birthday'] = $employee->getBirthday()?->format('Y-m-d');
            $data['phone'] = $employee->getPhone();
        }

        return $data;
    }
}
