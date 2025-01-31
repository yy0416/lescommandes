<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\PushSubscription;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Psr\Log\LoggerInterface;

class PushNotificationService
{
    private WebPush $webPush;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private string $vapidPublicKey,
        private string $vapidPrivateKey,
        private string $vapidSubject
    ) {
        $this->webPush = new WebPush([
            'VAPID' => [
                'subject' => $this->vapidSubject,
                'publicKey' => $this->vapidPublicKey,
                'privateKey' => $this->vapidPrivateKey,
            ],
        ]);
    }

    public function getPublicKey(): string
    {
        return $this->vapidPublicKey;
    }

    public function sendNewOrderNotification(Order $order): void
    {
        try {
            $subscriptions = $this->entityManager->getRepository(PushSubscription::class)->findAll();

            $payload = json_encode([
                'title' => '新订单通知',
                'body' => sprintf('收到新订单 #%d，客户：%s', $order->getId(), $order->getCustomerName()),
                'icon' => '/build/images/icon-192.png',
                'badge' => '/build/images/badge-72.png',
                'data' => [
                    'url' => '/admin?order=' . $order->getId()
                ]
            ]);

            /** @var PushSubscription $subscription */
            foreach ($subscriptions as $subscription) {
                $webPushSubscription = Subscription::create([
                    'endpoint' => $subscription->getEndpoint(),
                    'keys' => $subscription->getKeys()
                ]);

                $this->webPush->queueNotification(
                    $webPushSubscription,
                    $payload
                );
            }

            // 发送所有排队的通知
            $results = $this->webPush->flush();

            foreach ($results as $result) {
                if (!$result->isSuccess()) {
                    $this->logger->warning('Push notification failed', [
                        'reason' => $result->getReason(),
                        'endpoint' => $result->getEndpoint()
                    ]);
                }
            }

            $this->logger->info('Push notification sent for new order', ['order_id' => $order->getId()]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send push notification', [
                'error' => $e->getMessage(),
                'order_id' => $order->getId()
            ]);
        }
    }
}
