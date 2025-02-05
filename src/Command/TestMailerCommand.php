<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

class TestMailerCommand extends Command
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('app:test-mailer')
            ->setDescription('Test email configuration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $email = (new Email())
                ->from($_ENV['SYSTEM_EMAIL'])
                ->to('yueyuan0416@gmail.com')
                ->subject('Test email')
                ->text('This is a test email.');

            $this->logger->info('Attempting to send test email', [
                'from' => $_ENV['SYSTEM_EMAIL'],
                'to' => 'yueyuan0416@gmail.com',
                'mailer_dsn' => $_ENV['MAILER_DSN']
            ]);

            $this->mailer->send($email);
            $output->writeln('Test email sent successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send test email', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $output->writeln('<error>Failed to send email: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
