<?php

namespace App\Controller\Admin;

use App\Entity\PickupTimeSlot;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class PickupTimeSlotCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PickupTimeSlot::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setLabel('添加新时间段');
            });
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('取货时间段')
            ->setEntityLabelInPlural('取货时间管理')
            ->setPageTitle('index', '取货时间段列表')
            ->setPageTitle('new', '新增时间段')
            ->setPageTitle('edit', fn(PickupTimeSlot $timeSlot) => sprintf('编辑时间段 - %s', $timeSlot));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            DateField::new('date', '日期'),
            TimeField::new('startTime', '开始时间'),
            TimeField::new('endTime', '结束时间'),
            IntegerField::new('maxOrders', '最大订单数')
                ->setHelp('此时间段可接受的最大订单数量'),
            BooleanField::new('isAvailable', '是否可用')
                ->renderAsSwitch(true),
            TextField::new('description', '说明')->hideOnIndex(),
        ];
    }
}
