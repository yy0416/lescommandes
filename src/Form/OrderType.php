<?php

namespace App\Form;

use App\Entity\Order;
use App\Entity\PickupLocation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class OrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // 简单设置明天早上9点
        $tomorrow = new \DateTime('tomorrow');
        $tomorrow->setTime(9, 0);

        $builder
            ->add('customerName', TextType::class, [
                'label' => 'WeChat Name',
                'attr' => [
                    'placeholder' => '请输入您的微信名'
                ]
            ])
            ->add('phone', TelType::class, [
                'label' => 'Phone',
                'attr' => [
                    'placeholder' => '请输入您的手机号码 (例如: 0612345678)'
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'placeholder' => '请输入您的电子邮箱'
                ]
            ])
            ->add('pickupLocation', EntityType::class, [
                'class' => PickupLocation::class,
                'choice_label' => 'name',
                'label' => 'Pickup Location',
                'placeholder' => '请选择取货地点'
            ])
            ->add('pickupTime', DateTimeType::class, [
                'label' => 'Pickup Time',
                'widget' => 'single_text',
                'html5' => true,
                'data' => $tomorrow,
                'attr' => [
                    'min' => $tomorrow->format('Y-m-d\TH:i'),
                    'step' => 900 // 15分钟间隔
                ],
                'constraints' => [
                    new Assert\GreaterThan([
                        'value' => $tomorrow,
                        'message' => '取货时间必须至少提前24小时预约'
                    ])
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
        ]);
    }
}
