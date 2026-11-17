<?php

namespace App\Controller;

use App\Api\Input;
use App\Api\Output;
use App\Entity\Employee;
use App\Entity\NamedEntity;
use App\Entity\Position;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/{kind<teams|positions>}', defaults: ['_format' => 'json'])]
final class OrganizationController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', methods: ['GET'])]
    public function list(string $kind): JsonResponse
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            $items = $this->em->getRepository($this->entityClass($kind))->findBy([], ['id' => 'ASC']);
        } else {
            $employees = $this->teamEmployees();
            $items = [];
            foreach ($employees as $employee) {
                $item = $kind === 'teams' ? $employee->getTeam() : $employee->getPosition();
                $items[$item->getId()] = $item;
            }
            $items = array_values($items);
        }

        return $this->json(array_map(static fn (NamedEntity $item) => Output::named($item), $items));
    }

    #[Route('/{id<\d+>}', methods: ['GET'])]
    public function show(string $kind, int $id): JsonResponse
    {
        $item = $this->find($kind, $id);
        if (!$this->isGranted('ROLE_ADMIN')) {
            $visible = false;
            foreach ($this->teamEmployees() as $employee) {
                $related = $kind === 'teams' ? $employee->getTeam() : $employee->getPosition();
                if ($related->getId() === $id) {
                    $visible = true;
                    break;
                }
            }
            if (!$visible) {
                throw new NotFoundHttpException('Resource not found.');
            }
        }

        return $this->json(Output::named($item));
    }

    #[Route('', methods: ['POST'])]
    public function create(string $kind, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        [$name, $description] = $this->input($request);
        $class = $this->entityClass($kind);
        $item = new $class($name, $description);
        $this->em->persist($item);
        $this->em->flush();

        return $this->json(Output::named($item), 201);
    }

    #[Route('/{id<\d+>}', methods: ['PUT'])]
    public function update(string $kind, int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $item = $this->find($kind, $id);
        [$name, $description] = $this->input($request);
        $item->update($name, $description);
        $this->em->flush();

        return $this->json(Output::named($item));
    }

    #[Route('/{id<\d+>}', methods: ['DELETE'])]
    public function delete(string $kind, int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $item = $this->find($kind, $id);
        $field = $kind === 'teams' ? 'team' : 'position';
        if ($this->em->getRepository(Employee::class)->count([$field => $item]) > 0) {
            throw new ConflictHttpException('Resource is assigned to employees.');
        }
        $this->em->remove($item);
        try {
            $this->em->flush();
        } catch (ForeignKeyConstraintViolationException $exception) {
            throw new ConflictHttpException('Resource is assigned to employees.', $exception);
        }

        return new JsonResponse(null, 204);
    }

    private function entityClass(string $kind): string
    {
        return $kind === 'teams' ? Team::class : Position::class;
    }

    private function find(string $kind, int $id): NamedEntity
    {
        return $this->em->find($this->entityClass($kind), $id) ?? throw new NotFoundHttpException('Resource not found.');
    }

    private function teamEmployees(): array
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->getEmployee()) {
            throw $this->createAccessDeniedException();
        }

        return $this->em->getRepository(Employee::class)->findBy(['team' => $user->getEmployee()->getTeam()], ['id' => 'ASC']);
    }

    private function input(Request $request): array
    {
        $data = Input::body($request, ['name', 'description']);

        return [Input::requiredString($data, 'name', 150), Input::optionalString($data, 'description', 65535)];
    }
}
