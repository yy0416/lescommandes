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

class ShopController extends AbstractController
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/', name: 'app_shop')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $products = $entityManager->getRepository(Product::class)->findAll();

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

        foreach ($cart as $id => $quantity) {
            $product = $entityManager->getRepository(Product::class)->find($id);
            if ($product) {
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
        $this->addFlash('success', '商品已添加到购物车');

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
    public function checkout(
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $entityManager,
        OrderMailer $orderMailer
    ): Response {
        // 1. 检查购物车是否为空
        $cart = $session->get('cart', []);
        if (empty($cart)) {
            return $this->redirectToRoute('app_cart');
        }

        // 2. 准备订单和购物车数据
        $order = new Order();
        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        $total = 0;
        $cartItems = [];
        foreach ($cart as $id => $quantity) {
            $product = $entityManager->getRepository(Product::class)->find($id);
            if ($product) {
                $cartItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'subtotal' => $product->getPrice() * $quantity
                ];
                $total += $product->getPrice() * $quantity;
            }
        }

        // 3. 处理表单提交
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // 设置订单基本信息
                $order = $form->getData();
                $order->setCreatedAt(new \DateTimeImmutable());
                $order->setTotalAmount($total);

                // 添加订单项
                foreach ($cartItems as $item) {
                    $orderItem = new OrderItem();
                    $orderItem->setOrder($order);
                    $orderItem->setProduct($item['product']);
                    $orderItem->setQuantity($item['quantity']);
                    $orderItem->setPrice($item['product']->getPrice());
                    $order->addOrderItem($orderItem);
                }

                // 保存订单
                $entityManager->persist($order);
                $entityManager->flush();

                // 尝试发送邮件
                try {
                    $this->logger->info('Preparing to send order confirmation email', [
                        'order_id' => $order->getId(),
                        'customer_email' => $order->getEmail(),
                        'items_count' => $order->getOrderItems()->count(),
                        'pickup_time' => $order->getPickupTime()?->format('Y-m-d H:i'),
                        'pickup_location' => $order->getPickupLocation()?->getName(),
                        'total_amount' => $order->getTotalAmount(),
                        'mailer_dsn' => $_ENV['MAILER_DSN'] ?? 'not set',
                        'system_email' => $_ENV['SYSTEM_EMAIL'] ?? 'not set'
                    ]);

                    $orderMailer->sendOrderConfirmation($order);
                    $this->logger->info('Email sent successfully');
                } catch (\Exception $e) {
                    $this->logger->error('Failed to send order confirmation email', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'order_id' => $order->getId(),
                        'error_class' => get_class($e),
                        'mailer_dsn' => $_ENV['MAILER_DSN'] ?? 'not set',
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]);
                    throw $e;
                }

                // 清空购物车
                $session->remove('cart');

                // 显示成功页面
                return $this->render('shop/success.html.twig', [
                    'order' => $order
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Order process failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                $this->addFlash('error', '订单处理失败：' . $e->getMessage());
                return $this->redirectToRoute('app_checkout');
            }
        } elseif ($form->isSubmitted()) {
            // 如果表单提交但验证失败，获取具体的错误信息
            $errors = [];

            if ($form->get('customerName')->getErrors()->count() > 0) {
                $errors[] = '姓名' . $form->get('customerName')->getErrors()->current()->getMessage();
            }

            if ($form->get('phone')->getErrors()->count() > 0) {
                $errors[] = '电话' . $form->get('phone')->getErrors()->current()->getMessage();
            }

            if ($form->get('email')->getErrors()->count() > 0) {
                $errors[] = '邮箱' . $form->get('email')->getErrors()->current()->getMessage();
            }

            if ($form->get('pickupLocation')->getErrors()->count() > 0) {
                $errors[] = '取货地点' . $form->get('pickupLocation')->getErrors()->current()->getMessage();
            }

            if ($form->get('pickupTime')->getErrors()->count() > 0) {
                $errors[] = '取货时间' . $form->get('pickupTime')->getErrors()->current()->getMessage();
            }

            if (empty($errors)) {
                $this->addFlash('error', '请填写正确的上述信息');
            } else {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            }
        }

        // 4. 显示结算页面
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
            // 发送订单确认邮件给客户
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
