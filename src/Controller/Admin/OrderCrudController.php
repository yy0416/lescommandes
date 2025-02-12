<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Dompdf\Dompdf;
use Dompdf\Options;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;

class OrderCrudController extends AbstractCrudController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $exportPdf = Action::new('exportPdf', '导出PDF')
            ->linkToCrudAction('exportPdf')
            ->addCssClass('btn btn-primary')
            ->setIcon('fa fa-file-pdf');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $exportPdf)
            ->add(Crud::PAGE_DETAIL, $exportPdf)
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setLabel('新建订单');
            });
    }

    public function configureCrud(Crud $crud): Crud
    {
        $orders = $this->entityManager->getRepository(Order::class)->findAll();
        $help = count($orders) === 0 ? '您现在还没有订单' : '';

        return $crud
            ->setEntityLabelInSingular('订单')
            ->setEntityLabelInPlural('订单管理')
            ->setPageTitle('index', '订单列表')
            ->setPageTitle('new', '新建订单')
            ->setPageTitle('edit', fn(Order $order) => sprintf('编辑订单 #%s', $order->getId()))
            ->setPageTitle('detail', fn(Order $order) => sprintf('订单 #%s', $order->getId()))
            ->setHelp('index', $help)
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->showEntityActionsInlined()
            ->setPaginatorPageSize(100000)
            ->setPaginatorRangeSize(0);
    }

    public function configureFields(string $pageName): iterable
    {
        $fields = [
            IdField::new('id')->hideOnForm(),
            TextField::new('customerName', '客户姓名'),
            TextField::new('phone', '联系电话'),
            TextField::new('email', '电子邮箱'),
            AssociationField::new('pickupLocation', '自提地点'),
            DateTimeField::new('pickupTime', '自提时间'),
            MoneyField::new('totalAmount', '总金额')
                ->setCurrency('EUR')
                ->setStoredAsCents(false)
                ->setNumDecimals(2),
            DateTimeField::new('createdAt', '创建时间')->hideOnForm(),
        ];

        // 在详情页显示订单项
        if ($pageName === Crud::PAGE_DETAIL) {
            $fields[] = CollectionField::new('orderItems', '订单商品')
                ->setTemplatePath('admin/order_items.html.twig');
        }

        return $fields;
    }

    public function exportPdf(AdminContext $context): Response
    {
        $order = $context->getEntity()->getInstance();

        // 配置PDF选项
        $options = new Options();
        $options->set('defaultFont', 'SimSun'); // 使用支持中文的字体

        $dompdf = new Dompdf($options);

        // 生成PDF内容
        $html = $this->renderView('admin/order_pdf.html.twig', [
            'order' => $order
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        // 生成PDF文件
        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="order-' . $order->getId() . '.pdf"'
            ]
        );
    }
}
