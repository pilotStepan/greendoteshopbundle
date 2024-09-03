<?php

namespace Greendot\EshopBundle\Form;

use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\ClientAddress;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Jméno',
            ])
            ->add('surname', TextType::class, [
                'label' => 'Příjmení',
            ])
            ->add('mail', EmailType::class, [
                'label' => 'E-mail',
            ])
            ->add('phone', TextType::class, [
                'label' => 'Telefon',
            ])
            ->add('address', ClientAddressType::class, [
                'label' => false,
                'mapped' => false,
            ])
            ->add('agreeNewsletter', CheckboxType::class, [
                'label' => 'Souhlasím s odběrem newsletteru',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
            'allow_extra_fields' => true,
        ]);
    }
}

class ClientAddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('company', TextType::class, [
                'label' => 'Společnost',
                'required' => false,
            ])
            ->add('ic', TextType::class, [
                'label' => 'IČ',
                'required' => false,
            ])
            ->add('dic', TextType::class, [
                'label' => 'DIČ',
                'required' => false,
            ])
            ->add('street', TextType::class, [
                'label' => 'Ulice a číslo orientační',
            ])
            ->add('city', TextType::class, [
                'label' => 'Město',
            ])
            ->add('zip', TextType::class, [
                'label' => 'PSČ',
            ])
            ->add('country', TextType::class, [
                'label' => 'Stát',
            ])
            ->add('ship_name', TextType::class, [
                'label' => 'Doručovací jméno',
                'required' => false,
            ])
            ->add('ship_surname', TextType::class, [
                'label' => 'Doručovací příjmení',
                'required' => false,
            ])
            ->add('ship_company', TextType::class, [
                'label' => 'Doručovací společnost',
                'required' => false,
            ])
            ->add('ship_street', TextType::class, [
                'label' => 'Doručovací ulice a číslo orientační',
                'required' => false,
            ])
            ->add('ship_city', TextType::class, [
                'label' => 'Doručovací město',
                'required' => false,
            ])
            ->add('ship_zip', TextType::class, [
                'label' => 'Doručovací PSČ',
                'required' => false,
            ])
            ->add('ship_country', TextType::class, [
                'label' => 'Doručovací stát',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ClientAddress::class,
        ]);
    }
}