<?php

namespace App\Service;

use App\Entity\Order;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

class OrderMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private string $adminEmail,
        private string $systemEmail,
        private LoggerInterface $logger
    ) {}
    /*
    // 发送给管理员的新订单通知
    public function sendNewOrderNotification(Order $order): void
    {
        try {
            $this->logger->info('Attempting to send new order notification to admin', [
                'order_id' => $order->getId(),
                'admin_email' => $this->adminEmail
            ]);

            $adminEmail = (new TemplatedEmail())
                ->from(new Address($this->systemEmail, '商品订购系统'))
                ->to(new Address($this->adminEmail, 'Admin'))
                ->subject('新订单通知 #' . $order->getId())
                ->htmlTemplate('emails/admin_new_order.html.twig')
                ->context([
                    'order' => $order
                ]);

            $this->mailer->send($adminEmail);
            $this->logger->info('Admin notification sent successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to send admin notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }*/

    // 发送给客户的订单确认邮件
    public function sendOrderConfirmation(Order $order): void
    {
        try {
            $this->logger->debug('Starting email send process', [
                'php_version' => PHP_VERSION,
                'symfony_env' => $_ENV['APP_ENV'],
                'mailer_dsn' => $_ENV['MAILER_DSN'],
                'openssl_version' => OPENSSL_VERSION_TEXT
            ]);

            if (!$order->getEmail()) {
                throw new \RuntimeException('Order email is missing');
            }

            $this->logger->info('Attempting to send order confirmation email', [
                'order_id' => $order->getId(),
                'customer_email' => $order->getEmail(),
                'customer_name' => $order->getCustomerName(),
                'has_order_items' => $order->getOrderItems()->count() > 0,
                'total_amount' => $order->getTotalAmount(),
                'system_email' => $this->systemEmail,
                'mailer_dsn' => $_ENV['MAILER_DSN'] ?? 'not set'
            ]);

            $email = (new TemplatedEmail())
                ->from(new Address($this->systemEmail, '商品订购系统'))
                ->to(new Address($order->getEmail(), $order->getCustomerName()))
                ->subject('订单确认通知 #' . $order->getId())
                ->htmlTemplate('emails/customer_order_confirmation.html.twig')
                ->context([
                    'order' => $order,
                    'adminEmail' => $this->adminEmail
                ]);

            // 检查邮件模板渲染
            try {
                $html = $email->getHtmlBody();
                $this->logger->debug('Email template rendered successfully', [
                    'length' => strlen($html),
                    'template_path' => 'emails/customer_order_confirmation.html.twig'
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to render email template', [
                    'error' => $e->getMessage(),
                    'template_path' => 'emails/customer_order_confirmation.html.twig'
                ]);
                throw $e;
            }

            $this->mailer->send($email);
            $this->logger->info('Order confirmation email sent successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to send order confirmation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'error_class' => get_class($e),
                'system_email' => $this->systemEmail,
                'customer_email' => $order->getEmail()
            ]);
            throw $e;
        }
    }
}
