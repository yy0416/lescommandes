<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\Order;
use App\Entity\PickupLocation;
use App\Entity\PickupTimeSlot;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

class DashboardController extends AbstractDashboardController
{
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('商品订购系统管理后台');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('管理后台', 'fa fa-home');
        yield MenuItem::linkToCrud('产品管理', 'fas fa-box', Product::class);
        yield MenuItem::linkToCrud('订单管理', 'fas fa-shopping-cart', Order::class);
        yield MenuItem::linkToCrud('自提点管理', 'fas fa-map-marker', PickupLocation::class);
        yield MenuItem::linkToCrud('取货时间管理', 'fas fa-clock', PickupTimeSlot::class);
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->setPaginatorPageSize(100000)
            ->setPaginatorRangeSize(0);
    }
}
