<?php

namespace App\Service;

use App\Entity\Order;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Psr\Log\LoggerInterface;

class OrderMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private string $adminEmail,
        private string $systemEmail,
        private LoggerInterface $logger
    ) {}

    // 发送给管理员
    public function sendNewOrderNotification(Order $order): void
    {

        $adminEmail = (new TemplatedEmail())
            ->from($this->systemEmail)
            ->to($this->adminEmail)
            ->subject('新订单通知 #' . $order->getId())
            ->htmlTemplate('emails/admin_new_order.html.twig')
            ->context([
                'order' => $order
            ]);

        $this->mailer->send($adminEmail);
    }

    public function sendOrderConfirmation(Order $order): void
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->systemEmail, '商品订购系统'))
                ->to(new Address($order->getEmail(), $order->getCustomerName()))
                ->subject('订单确认通知 #' . $order->getId())
                ->htmlTemplate('emails/customer_order_confirmation.html.twig')
                ->context([
                    'order' => $order,
                    'adminEmail' => $this->adminEmail
                ]);

            $this->mailer->send($email);
            $this->logger->info('Order confirmation email sent', ['order_id' => $order->getId()]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send order confirmation email', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }
}
