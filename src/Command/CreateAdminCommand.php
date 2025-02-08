<?php

namespace App\Command;

use App\Entity\Admin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Creates a new admin user'
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Admin email')
            ->addArgument('password', InputArgument::REQUIRED, 'Admin password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $adminCreationKey = $_ENV['ADMIN_CREATION_KEY'] ?? null;
        if (!$adminCreationKey || $adminCreationKey !== '0808') {
            $output->writeln('<error>未授权的管理员创建操作！</error>');
            return Command::FAILURE;
        }

        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        $output->writeln('正在创建管理员账户：');
        $output->writeln("邮箱: $email");
        $output->writeln('请记住您的登录密码！');

        $admin = new Admin();
        $admin->setEmail($email);

        $hashedPassword = $this->passwordHasher->hashPassword(
            $admin,
            $password
        );
        $admin->setPassword($hashedPassword);

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $output->writeln('管理员账户创建成功！');
        $output->writeln('请使用以下信息登录：');
        $output->writeln("- 邮箱: $email");
        $output->writeln('- 密码: (您刚才设置的密码)');

        return Command::SUCCESS;
    }
}
