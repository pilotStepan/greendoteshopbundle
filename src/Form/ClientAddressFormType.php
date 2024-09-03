<?php

namespace Greendot\EshopBundle\Form;

use Greendot\EshopBundle\Entity\Project\ClientAddress;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientAddressFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('street', TextType::class, ['label' => 'Ulice a číslo domu'])
            ->add('city', TextType::class, ['label' => 'Město'])
            ->add('zip', TextType::class, ['label' => 'PSČ'])
            ->add('country', TextType::class, ['label' => 'Stát'])
            ->add('company', TextType::class, ['label' => 'Název organizace'])
            ->add('ic', TextType::class, ['label' => 'IČ'])
            ->add('dic', TextType::class, ['label' => 'DIČ', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ClientAddress::class,
        ]);
    }
}