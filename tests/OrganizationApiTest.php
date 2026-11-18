<?php

namespace App\Tests;

use App\Entity\User;
use App\Command\CreateAdminCommand;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class OrganizationApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $adminToken;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        $params = $connection->getParams();
        if ($connection->getDatabase() !== 'timetracker_test' || ($params['host'] ?? null) !== 'database_test') {
            throw new \RuntimeException('Tests require the isolated database_test service.');
        }
        foreach (['users', 'employees', 'teams', 'positions'] as $table) {
            $connection->executeStatement('DELETE FROM '.$table);
        }
        $admin = new User('admin@example.test', true);
        $admin->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($admin, 'admin-password-123'));
        $em->persist($admin);
        $em->flush();
        $this->adminToken = $this->login('admin@example.test', 'admin-password-123');
    }

    public function testCrudValidationAndReferentialIntegrity(): void
    {
        $this->request('GET', '/api/employees');
        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error'], array_keys($this->json()));
        $this->request('POST', '/api/teams', ['name' => ''], $this->adminToken);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', '/api/teams', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->adminToken,
        ], '{');
        self::assertResponseStatusCodeSame(400);
        $team = $this->createNamed('teams', 'Engineering');
        $position = $this->createNamed('positions', 'Developer');

        $data = $this->employeeData('Alice', 'alice@example.test', $team['name'], $position['name']);
        $invalidData = $data;
        $invalidData['firstName'] = '';
        $invalidData['lastName'] = [];
        $invalidData['email'] = 'invalid';
        $invalidData['team'] = 'Unknown team';
        $invalidData['position'] = 'Unknown position';
        $invalidData['birthday'] = '2023-02-29';
        $invalidData['phone'] = [];
        $invalidData['unexpected'] = true;
        $this->request('POST', '/api/employees', $invalidData, $this->adminToken);
        self::assertResponseStatusCodeSame(422);
        $error = $this->json()['error'];
        foreach (['firstName', 'lastName', 'email', 'team', 'position', 'birthday', 'phone', 'unexpected', 'Engineering', 'Developer'] as $field) {
            self::assertStringContainsString($field, $error);
        }

        $data['birthday'] = '2023-02-29';
        $this->request('POST', '/api/employees', $data, $this->adminToken);
        self::assertResponseStatusCodeSame(422);
        $data['birthday'] = '2000-02-29';
        $data['phone'] = '12345';
        $this->request('POST', '/api/employees', $data, $this->adminToken);
        self::assertResponseStatusCodeSame(201);
        $employee = $this->json();
        self::assertSame('2000-02-29', $employee['birthday']);
        self::assertSame('12345', $employee['phone']);

        $duplicateTeam = $this->createNamed('teams', 'Engineering');
        $duplicatePosition = $this->createNamed('positions', 'Developer');
        $ambiguousData = $data;
        $ambiguousData['email'] = 'ambiguous@example.test';
        $this->request('POST', '/api/employees', $ambiguousData, $this->adminToken);
        self::assertResponseStatusCodeSame(422);
        $ambiguousError = $this->json()['error'];
        self::assertStringContainsString('team', $ambiguousError);
        self::assertStringContainsString('position', $ambiguousError);
        self::assertStringContainsString('ambiguous', $ambiguousError);
        $this->request('DELETE', '/api/teams/'.$duplicateTeam['id'], null, $this->adminToken);
        self::assertResponseStatusCodeSame(204);
        $this->request('DELETE', '/api/positions/'.$duplicatePosition['id'], null, $this->adminToken);
        self::assertResponseStatusCodeSame(204);

        $this->request('POST', '/api/employees', $data, $this->adminToken);
        self::assertResponseStatusCodeSame(409);
        $data['email'] = 'other@example.test';
        $data['team'] = 'Unknown team';
        $this->request('POST', '/api/employees', $data, $this->adminToken);
        self::assertResponseStatusCodeSame(422);
        $this->request('DELETE', '/api/teams/'.$team['id'], null, $this->adminToken);
        self::assertResponseStatusCodeSame(409);
        $this->request('DELETE', '/api/positions/'.$position['id'], null, $this->adminToken);
        self::assertResponseStatusCodeSame(409);

        $this->request('PUT', '/api/teams/'.$team['id'], ['name' => 'Platform', 'description' => 'Updated'], $this->adminToken);
        self::assertResponseIsSuccessful();
        self::assertSame('Platform', $this->json()['name']);
        $this->request('DELETE', '/api/employees/'.$employee['id'], null, $this->adminToken);
        self::assertResponseStatusCodeSame(204);
        $this->request('DELETE', '/api/teams/'.$team['id'], null, $this->adminToken);
        self::assertResponseStatusCodeSame(204);
        $this->request('DELETE', '/api/positions/'.$position['id'], null, $this->adminToken);
        self::assertResponseStatusCodeSame(204);
    }

    public function testTeamVisibilityCurrentMembershipAndDeletedAccounts(): void
    {
        $teamA = $this->createNamed('teams', 'A');
        $teamB = $this->createNamed('teams', 'B');
        $positionA = $this->createNamed('positions', 'A position');
        $positionB = $this->createNamed('positions', 'B position');
        $aliceData = $this->employeeData('Alice', 'alice@example.test', $teamA['name'], $positionA['name']);
        $aliceData['birthday'] = '2000-01-01';
        $aliceData['phone'] = 'private';
        $alice = $this->createEmployee($aliceData);
        $bob = $this->createEmployee($this->employeeData('Bob', 'bob@example.test', $teamA['name'], $positionA['name']));
        $carol = $this->createEmployee($this->employeeData('Carol', 'carol@example.test', $teamB['name'], $positionB['name']));
        $this->request('POST', '/api/employees/'.$alice['id'].'/account', ['password' => 'employee-password-123'], $this->adminToken);
        self::assertResponseStatusCodeSame(201);
        $this->request('POST', '/api/employees/'.$alice['id'].'/account', ['password' => 'employee-password-123'], $this->adminToken);
        self::assertResponseStatusCodeSame(409);
        $token = $this->login('alice@example.test', 'employee-password-123');

        $this->request('GET', '/api/employees', null, $token);
        self::assertResponseIsSuccessful();
        $visible = $this->json();
        self::assertCount(2, $visible);
        self::assertSame([$alice['id'], $bob['id']], array_column($visible, 'id'));
        self::assertSame('alice@example.test', $visible[0]['email']);
        self::assertArrayNotHasKey('birthday', $visible[0]);
        self::assertArrayNotHasKey('phone', $visible[0]);
        $this->request('GET', '/api/employees/'.$carol['id'], null, $token);
        self::assertResponseStatusCodeSame(404);
        $this->request('PUT', '/api/employees/'.$bob['id'], $aliceData, $token);
        self::assertResponseStatusCodeSame(403);
        $this->request('GET', '/api/teams/'.$teamB['id'], null, $token);
        self::assertResponseStatusCodeSame(404);
        $this->request('GET', '/api/positions', null, $token);
        self::assertSame([$positionA['id']], array_column($this->json(), 'id'));
        $this->request('GET', '/api/positions/'.$positionB['id'], null, $token);
        self::assertResponseStatusCodeSame(404);

        $aliceData['team'] = $teamB['name'];
        $aliceData['email'] = 'alice2@example.test';
        $this->request('PUT', '/api/employees/'.$alice['id'], $aliceData, $this->adminToken);
        self::assertResponseIsSuccessful();
        $this->request('GET', '/api/employees', null, $token);
        self::assertSame([$alice['id'], $carol['id']], array_column($this->json(), 'id'));
        $this->request('GET', '/api/employees/'.$bob['id'], null, $token);
        self::assertResponseStatusCodeSame(404);
        $this->login('alice2@example.test', 'employee-password-123');
        $this->request('DELETE', '/api/employees/'.$alice['id'], null, $this->adminToken);
        self::assertResponseStatusCodeSame(204);
        $this->request('GET', '/api/employees', null, $token);
        self::assertResponseStatusCodeSame(401);
        $replacement = $this->createEmployee($this->employeeData('Replacement', 'alice2@example.test', $teamB['name'], $positionB['name']));
        $this->request('POST', '/api/employees/'.$replacement['id'].'/account', ['password' => 'replacement-password-123'], $this->adminToken);
        self::assertResponseStatusCodeSame(201);
        $this->request('GET', '/api/employees', null, $token);
        self::assertResponseStatusCodeSame(401);
    }

    public function testExpiredAndInvalidCredentialsAreRejected(): void
    {
        $this->request('POST', '/api/login', ['email' => 'admin@example.test', 'password' => 'wrong']);
        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error'], array_keys($this->json()));
        $container = static::getContainer();
        $admin = $container->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['email' => 'admin@example.test']);
        $expired = $container->get(JWTTokenManagerInterface::class)->createFromPayload($admin, ['exp' => time() - 10]);
        $this->request('GET', '/api/employees', null, $expired);
        self::assertResponseStatusCodeSame(401);
    }

    public function testAdminBootstrapCreatesHashedCredentials(): void
    {
        $container = static::getContainer();
        $tester = new CommandTester($container->get(CreateAdminCommand::class));
        $tester->setInputs(['newadmin@example.test', 'a-long-admin-password', 'a-long-admin-password']);
        self::assertSame(0, $tester->execute([]));
        self::assertStringNotContainsString('a-long-admin-password', $tester->getDisplay());
        $user = $container->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['email' => 'newadmin@example.test']);
        self::assertNotNull($user);
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertTrue($container->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'a-long-admin-password'));
    }

    private function createNamed(string $kind, string $name): array
    {
        $this->request('POST', '/api/'.$kind, ['name' => $name], $this->adminToken);
        self::assertResponseStatusCodeSame(201);

        return $this->json();
    }

    private function createEmployee(array $data): array
    {
        $this->request('POST', '/api/employees', $data, $this->adminToken);
        self::assertResponseStatusCodeSame(201);

        return $this->json();
    }

    private function employeeData(string $firstName, string $email, string $team, string $position): array
    {
        return ['firstName' => $firstName, 'lastName' => 'Example', 'email' => $email, 'team' => $team, 'position' => $position];
    }

    private function login(string $email, string $password): string
    {
        $this->request('POST', '/api/login', ['email' => $email, 'password' => $password]);
        self::assertResponseIsSuccessful();

        return $this->json()['token'];
    }

    private function request(string $method, string $path, ?array $data = null, ?string $token = null): void
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $this->client->request($method, $path, [], [], $server, $data === null ? null : json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function json(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
