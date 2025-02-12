<?php

namespace App\Controller\Admin;

use App\Entity\PickupLocation;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use Doctrine\ORM\QueryBuilder;

class PickupLocationCrudController extends AbstractCrudController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getEntityFqcn(): string
    {
        return PickupLocation::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::DELETE)
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->update(Crud::PAGE_NEW, Action::SAVE_AND_RETURN, function (Action $action) {
                return $action->setLabel('保存');
            });
    }

    public function configureCrud(Crud $crud): Crud
    {
        $locations = $this->entityManager->getRepository(PickupLocation::class)->findAll();
        $help = count($locations) === 0 ? '您还没有设置自提点' : '';

        return $crud
            ->setEntityLabelInSingular('自提点')
            ->setEntityLabelInPlural('自提点管理')
            ->setPageTitle('index', '自提点列表')
            ->setPageTitle('new', '新增自提点')
            ->setPageTitle('edit', fn(PickupLocation $location) => sprintf('编辑自提点 - %s', $location->getName()))
            ->setPageTitle('detail', fn(PickupLocation $location) => sprintf('自提点 - %s', $location->getName()))
            ->setHelp('index', $help)
            ->setPaginatorPageSize(100000)
            ->setPaginatorRangeSize(0);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name', '名称'),
            TextField::new('address', '地址'),
            BooleanField::new('isActive', '是否启用')
                ->renderAsSwitch(true)
                ->setFormTypeOption('data', true)
                ->setHelp('停用自提点而不是删除它可以保留历史订单数据'),
        ];
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (count($entityInstance->getOrders()) > 0) {
            // 如果有关联订单，则只标记为删除
            $entityInstance->setIsDeleted(true);
            $entityManager->persist($entityInstance);
        } else {
            // 如果没有关联订单，则真实删除
            $entityManager->remove($entityInstance);
        }
        $entityManager->flush();
    }

    public function createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $qb->andWhere($qb->getRootAliases()[0] . '.isDeleted = :isDeleted')
            ->setParameter('isDeleted', false);
        return $qb;
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::updateEntity($entityManager, $entityInstance);
        $entityManager->refresh($entityInstance);
    }
}
