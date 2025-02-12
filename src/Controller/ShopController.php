<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\OrderItem;
use App\Form\OrderType;
use App\Service\OrderMailer;
use App\Service\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;


class ShopController extends AbstractController
{
    private $logger;
    private $csrfTokenManager;
    private $translator;

    public function __construct(
        LoggerInterface $logger,
        CsrfTokenManagerInterface $csrfTokenManager,
        TranslatorInterface $translator
    ) {
        $this->logger = $logger;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->translator = $translator;
    }

    #[Route('/', name: 'app_shop')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $products = $entityManager->getRepository(Product::class)->findAllActive();

        return $this->render('shop/index.html.twig', [
            'products' => $products
        ]);
    }

    #[Route('/cart', name: 'app_cart')]
    public function cart(SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        $cart = $session->get('cart', []);
        $cartItems = [];
        $total = 0;

        if (empty($cart)) {
            return $this->render('shop/cart.html.twig', [
                'items' => [],
                'total' => 0
            ]);
        }

        foreach ($cart as $id => $quantity) {
            $product = $entityManager->getRepository(Product::class)->find($id);
            if ($product && !$product->isDeleted()) {
                $cartItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'subtotal' => $product->getPrice() * $quantity
                ];
                $total += $product->getPrice() * $quantity;
            }
        }

        return $this->render('shop/cart.html.twig', [
            'items' => $cartItems,
            'total' => $total
        ]);
    }

    #[Route('/cart/add/{id}', name: 'app_cart_add')]
    public function addToCart(Product $product, Request $request, SessionInterface $session): Response
    {
        $quantity = (int)$request->request->get('quantity', 1);
        $cart = $session->get('cart', []);
        $id = $product->getId();

        if (isset($cart[$id])) {
            $cart[$id] += $quantity;
        } else {
            $cart[$id] = $quantity;
        }

        $session->set('cart', $cart);
        $this->addFlash('success', 'Le produit a été ajouté au panier.');

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/cart/remove/{id}', name: 'app_cart_remove')]
    public function removeFromCart(int $id, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        if (isset($cart[$id])) {
            unset($cart[$id]);
        }

        $session->set('cart', $cart);
        return $this->redirectToRoute('app_cart');
    }

    #[Route('/cart/update/{id}', name: 'app_cart_update')]
    public function updateCart(Product $product, Request $request, SessionInterface $session): Response
    {
        $quantity = (int)$request->request->get('quantity');
        if ($quantity > 0) {
            $cart = $session->get('cart', []);
            $cart[$product->getId()] = $quantity;
            $session->set('cart', $cart);
        }

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/cart/items', name: 'app_cart_items')]
    public function cartItems(SessionInterface $session, EntityManagerInterface $entityManager): JsonResponse
    {
        $cart = $session->get('cart', []);
        $cartItems = [];
        $total = 0;

        foreach ($cart as $id => $quantity) {
            $product = $entityManager->getRepository(Product::class)->find($id);
            if ($product) {
                $subtotal = $product->getPrice() * $quantity;
                $cartItems[] = [
                    'product' => [
                        'id' => $product->getId(),
                        'name' => $product->getName(),
                        'price' => $product->getPrice()
                    ],
                    'quantity' => $quantity,
                    'subtotal' => $subtotal
                ];
                $total += $subtotal;
            }
        }

        return new JsonResponse([
            'items' => $cartItems,
            'total' => $total
        ]);
    }

    #[Route('/checkout', name: 'app_checkout')]
    public function checkout(Request $request, EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        $cartItems = [];
        $total = 0;

        if (empty($cart)) {
            $this->addFlash('error', 'Your cart is empty');
            return $this->redirectToRoute('app_cart');
        }

        foreach ($cart as $id => $quantity) {
            $product = $entityManager->getRepository(Product::class)->find($id);
            if ($product && !$product->isDeleted()) {
                $cartItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'subtotal' => $product->getPrice() * $quantity
                ];
                $total += $product->getPrice() * $quantity;
            }
        }

        $order = new Order();
        $form = $this->createForm(OrderType::class, $order, [
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id'   => 'checkout_form'
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $order->setCreatedAt(new \DateTimeImmutable());

                foreach ($cartItems as $item) {
                    $orderItem = new OrderItem();
                    $orderItem->setOrder($order);
                    $orderItem->setProduct($item['product']);
                    $orderItem->setQuantity($item['quantity']);
                    $orderItem->setPrice($item['product']->getPrice());
                    $order->addOrderItem($orderItem);
                }

                $order->calculateTotal();

                $entityManager->persist($order);
                $entityManager->flush();

                $session->remove('cart');

                $this->logger->info('Form submitted', [
                    'form_data' => $request->request->all(),
                    'is_valid' => $form->isValid(),
                    'errors' => $form->getErrors(true)->__toString()
                ]);

                return $this->redirectToRoute('app_checkout_success', [
                    'id' => $order->getId()
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Order creation failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $this->addFlash('error', 'An error occurred while processing your order');
                return $this->redirectToRoute('app_cart');
            }
        } elseif ($form->isSubmitted() && !$form->isValid()) {
            $this->logger->info('Form validation failed', [
                'errors' => $form->getErrors(true, true)->__toString(),
                'data' => $request->request->all()
            ]);
            $this->addFlash('error', $this->translator->trans('form.error.validation'));
            return $this->render('shop/checkout.html.twig', [
                'form' => $form->createView(),
                'cart' => $cartItems,
                'total' => $total
            ]);
        }

        return $this->render('shop/checkout.html.twig', [
            'form' => $form->createView(),
            'cart' => $cartItems,
            'total' => $total
        ]);
    }

    #[Route('/orders', name: 'app_orders')]
    public function orders(EntityManagerInterface $entityManager): Response
    {
        $orders = $entityManager->getRepository(Order::class)->findBy(
            [],
            ['createdAt' => 'DESC']
        );

        return $this->render('shop/orders.html.twig', [
            'orders' => $orders
        ]);
    }

    #[Route('/order/{id}', name: 'app_order_detail')]
    public function orderDetail(Order $order): Response
    {
        return $this->render('shop/order_detail.html.twig', [
            'order' => $order
        ]);
    }

    #[Route('/checkout/success/{id}', name: 'app_checkout_success')]
    public function checkoutSuccess(Order $order, OrderMailer $orderMailer): Response
    {
        $this->logger->info('Starting checkout success process', [
            'order_id' => $order->getId(),
            'customer_email' => $order->getEmail()
        ]);

        try {
            $orderMailer->sendOrderConfirmation($order);

            $this->addFlash('success', '订单确认邮件已发送到您的邮箱');
        } catch (\Exception $e) {
            $this->logger->error('Failed to send emails', [
                'error' => $e->getMessage(),
                'order_id' => $order->getId(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->addFlash('error', '订单已创建，但发送确认邮件时出现问题');
        }

        $this->logger->info('Checkout success process completed', [
            'order_id' => $order->getId()
        ]);

        return $this->render('shop/success.html.twig', [
            'order' => $order
        ]);
    }
}
