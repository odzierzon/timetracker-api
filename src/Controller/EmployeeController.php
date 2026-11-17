<?php

namespace App\Controller;

use App\Api\Input;
use App\Api\Output;
use App\Entity\Employee;
use App\Entity\Position;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/api/employees', defaults: ['_format' => 'json'])]
final class EmployeeController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $admin = $this->isGranted('ROLE_ADMIN');
        $criteria = $admin ? [] : ['team' => $this->employeeUser()->getTeam()];
        $employees = $this->em->getRepository(Employee::class)->findBy($criteria, ['id' => 'ASC']);

        return $this->json(array_map(static fn (Employee $employee) => Output::employee($employee, $admin), $employees));
    }

    #[Route('/{id<\d+>}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $employee = $this->find($id);
        $admin = $this->isGranted('ROLE_ADMIN');
        if (!$admin && $employee->getTeam()->getId() !== $this->employeeUser()->getTeam()->getId()) {
            throw new NotFoundHttpException('Employee not found.');
        }

        return $this->json(Output::employee($employee, $admin));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $employee = new Employee();
        $this->apply($employee, $request);
        $this->em->persist($employee);
        $this->flushUnique();

        return $this->json(Output::employee($employee, true), 201);
    }

    #[Route('/{id<\d+>}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $employee = $this->find($id);
        $this->apply($employee, $request);
        $user = $this->em->getRepository(User::class)->findOneBy(['employee' => $employee]);
        if ($user !== null) {
            $user->setEmail($employee->getEmail());
        }
        $this->flushUnique();

        return $this->json(Output::employee($employee, true));
    }

    #[Route('/{id<\d+>}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->em->remove($this->find($id));
        $this->em->flush();

        return new JsonResponse(null, 204);
    }

    #[Route('/{id<\d+>}/account', methods: ['POST'])]
    public function createAccount(int $id, Request $request, UserPasswordHasherInterface $hasher): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $employee = $this->find($id);
        $data = Input::body($request, ['password']);
        $password = Input::password($data);
        if ($this->em->getRepository(User::class)->findOneBy(['employee' => $employee])) {
            throw new ConflictHttpException('Employee already has an account.');
        }
        $user = new User($employee->getEmail(), false, $employee);
        $user->setPassword($hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->flushUnique();

        return $this->json(['employeeId' => $employee->getId(), 'email' => $user->getEmail()], 201);
    }

    private function find(int $id): Employee
    {
        return $this->em->find(Employee::class, $id) ?? throw new NotFoundHttpException('Employee not found.');
    }

    private function employeeUser(): Employee
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->getEmployee()) {
            throw $this->createAccessDeniedException();
        }

        return $user->getEmployee();
    }

    private function apply(Employee $employee, Request $request): void
    {
        $data = Input::body($request, ['firstName', 'lastName', 'email', 'teamId', 'positionId', 'birthday', 'phone']);
        $firstName = Input::requiredString($data, 'firstName', 100);
        $lastName = Input::requiredString($data, 'lastName', 100);
        $email = Input::email($data);
        $team = $this->em->find(Team::class, Input::id($data, 'teamId'));
        $position = $this->em->find(Position::class, Input::id($data, 'positionId'));
        if (!$team || !$position) {
            throw new UnprocessableEntityHttpException('Team or position does not exist.');
        }
        $employee->update($firstName, $lastName, $email, $team, $position, Input::birthday($data), Input::optionalString($data, 'phone', 50));
    }

    private function flushUnique(): void
    {
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw new ConflictHttpException('Email or account already exists.', $exception);
        } catch (ForeignKeyConstraintViolationException $exception) {
            throw new ConflictHttpException('A referenced resource no longer exists.', $exception);
        }
    }
}
