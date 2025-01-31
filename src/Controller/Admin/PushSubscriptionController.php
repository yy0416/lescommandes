<?php

namespace App\Controller\Admin;

use App\Entity\PushSubscription;
use App\Service\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PushSubscriptionController extends AbstractController
{
    #[Route('/admin/push/key', name: 'admin_push_key', methods: ['GET'])]
    public function getPublicKey(PushNotificationService $pushService): JsonResponse
    {
        return $this->json(['publicKey' => $pushService->getPublicKey()]);
    }

    #[Route('/admin/push/subscribe', name: 'admin_push_subscribe', methods: ['POST'])]
    public function subscribe(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $subscription = new PushSubscription();
        $subscription->setEndpoint($data['endpoint']);
        $subscription->setKeys([
            'p256dh' => $data['keys']['p256dh'],
            'auth' => $data['keys']['auth']
        ]);

        $em->persist($subscription);
        $em->flush();

        return $this->json(['status' => 'subscribed']);
    }
}
