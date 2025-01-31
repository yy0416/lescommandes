<?php

namespace App\Service;

use App\Entity\Order;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private string $adminEmail
    ) {}

    public function sendOrderConfirmation(Order $order): void
    {
        $email = (new TemplatedEmail())
            ->from('your-system@example.com')
            ->to($order->getEmail())
            ->subject('订单确认 - 订单号: ' . $order->getId())
            ->htmlTemplate('emails/order_confirmation.html.twig')
            ->context([
                'order' => $order
            ]);

        $this->mailer->send($email);

        // 发送管理员通知
        $adminEmail = (new TemplatedEmail())
            ->from('your-system@example.com')
            ->to($this->adminEmail)
            ->subject('新订单通知 - 订单号: ' . $order->getId())
            ->htmlTemplate('emails/admin_notification.html.twig')
            ->context([
                'order' => $order
            ]);

        $this->mailer->send($adminEmail);
    }
}
