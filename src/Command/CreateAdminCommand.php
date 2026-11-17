<?php

namespace App\Command;

use App\Api\Input as ApiInput;
use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin', description: 'Create an administrator interactively.')]
final class CreateAdminCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $hasher)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$input->isInteractive()) {
            $io->error('This command requires an interactive terminal.');

            return Command::FAILURE;
        }

        try {
            $email = ApiInput::email(['email' => $io->ask('Admin email')]);
            $password = ApiInput::password(['password' => $io->askHidden('Admin password (12–72 bytes)')]);
            $confirmation = $io->askHidden('Confirm password');
            if (!is_string($confirmation) || !hash_equals($password, $confirmation)) {
                $io->error('Passwords do not match.');

                return Command::FAILURE;
            }
            $user = new User($email, true);
            $user->setPassword($this->hasher->hashPassword($user, $password));
            $this->em->persist($user);
            $this->em->flush();
        } catch (UnprocessableEntityHttpException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        } catch (UniqueConstraintViolationException) {
            $io->error('An account with this email already exists.');

            return Command::FAILURE;
        }

        $io->success('Admin created.');

        return Command::SUCCESS;
    }
}
