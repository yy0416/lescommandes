<?php

namespace App\Service;

use App\Entity\Order;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;
use Psr\Log\LoggerInterface;

class OrderService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private Environment $twig,
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
        private string $adminEmail
    ) {}

    private function sendConfirmationEmail(Order $order): void
    {
        try {
            $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr';
            $template = sprintf('emails/order_confirmation.%s.html.twig', $locale);

            // 确保模板存在，否则使用默认模板
            if (!$this->twig->getLoader()->exists($template)) {
                $template = 'emails/order_confirmation.fr.html.twig';
            }

            $this->logger->info('Starting email send process', [
                'order_id' => $order->getId(),
                'customer_email' => $order->getEmail(),
                'locale' => $locale,
                'template' => $template,
                'mailer_dsn' => $_ENV['MAILER_DSN'] ?? 'not set',
                'admin_email' => $this->adminEmail
            ]);

            $email = (new TemplatedEmail())
                ->from($this->adminEmail)
                ->to($order->getEmail())
                ->subject($this->translator->trans('email.order.subject', ['%id%' => $order->getId()], domain: 'messages', locale: $locale))
                ->htmlTemplate($template)
                ->context([
                    'order' => $order,
                ]);

            $this->mailer->send($email);

            $this->logger->info('Email sent successfully', [
                'order_id' => $order->getId(),
                'customer_email' => $order->getEmail()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send email', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'order_id' => $order->getId(),
                'customer_email' => $order->getEmail()
            ]);
            throw $e;
        }
    }
}
