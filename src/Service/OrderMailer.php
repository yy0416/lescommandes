<?php

namespace App\Service;


use App\Entity\Order;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Symfony\Component\HttpFoundation\RequestStack;

class OrderMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private string $adminEmail,
        private string $systemEmail,
        private LoggerInterface $logger,
        private Environment $twig,
        private RequestStack $requestStack
    ) {}

    // 发送给客户的订单确认邮件
    public function sendOrderConfirmation(Order $order): void
    {
        try {
            $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr';
            $template = sprintf('emails/order_confirmation.%s.html.twig', $locale);

            // 确保模板存在，否则使用默认模板
            if (!$this->twig->getLoader()->exists($template)) {
                $template = 'emails/order_confirmation.fr.html.twig';
            }

            $this->logger->info('Preparing to send order confirmation email', [
                'order_id' => $order->getId(),
                'customer_email' => $order->getEmail(),
                'locale' => $locale,
                'template' => $template,
                'total_amount' => $order->getTotalAmount(),
                'system_email' => $this->systemEmail
            ]);

            $email = (new TemplatedEmail())
                ->from(new Address($this->systemEmail, 'Votre Boutique'))
                ->to(new Address($order->getEmail(), $order->getCustomerName()))
                ->subject('Order Confirmation #' . $order->getId())
                ->htmlTemplate($template)
                ->context([
                    'order' => $order,
                    'orderNumber' => $order->getId(),
                    'customerName' => $order->getCustomerName(),
                    'totalAmount' => $order->getTotalAmount(),
                    'pickupTime' => $order->getPickupTime()?->format('Y-m-d H:i') ?? 'N/A',
                    'pickupLocation' => $order->getPickupLocation()?->getName() ?? 'N/A'
                ]);

            // 验证模板渲染
            $renderedEmail = $this->twig->render($template, [
                'order' => $order,
                'orderNumber' => $order->getId(),
                'customerName' => $order->getCustomerName(),
                'totalAmount' => $order->getTotalAmount(),
                'pickupTime' => $order->getPickupTime()?->format('Y-m-d H:i') ?? 'N/A',
                'pickupLocation' => $order->getPickupLocation()?->getName() ?? 'N/A'
            ]);

            $this->logger->debug('Email content preview', ['content' => $renderedEmail]);

            $this->mailer->send($email);

            $this->logger->info('Order confirmation email sent successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to send order confirmation email', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
