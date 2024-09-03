<?php
namespace Greendot\EshopBundle\Form;

use Greendot\EshopBundle\Entity\Project\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientMailQuestion extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                'name',
                TextType::class,
                [
                    'label' => 'Jméno',
                    'required' => true
                ])
            ->add(
                'company',
                TextType::class,
                [
                    'label' => 'Organizace',
                    'required' => true
                ])
            ->add(
                'mail',
                TextType::class,
                [
                    'label' => 'E-mail',
                    'required' => true
                ])
            ->add(
                'phone',
                TelType::class,
                [
                    'label' => 'Telefon',
                    'required' => true
                ])
            ->add(
                'question',
                TextareaType::class,
                [
                    'label' => 'Váš dotaz',
                    'required' => true,
                    'mapped' => false
                ])
            ->add(
                'agreeGdpr',
                CheckboxType::class,
                [
                    'label' => 'Souhlasím se zpracováním osobních údajů',
                    'required' => true,
                    'mapped' => false
                ])
            ->add(
                'submit',
                SubmitType::class,
                ['label' => 'Odeslat dotaz'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
            'csrf_protection' => false
        ]);
    }
}