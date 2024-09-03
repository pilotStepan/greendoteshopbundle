<?php

namespace Greendot\EshopBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ClientChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Současné heslo',
                'mapped' => false,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Prosím zadejte současné heslo',
                    ]),
                ],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Nové heslo'],
                'second_options' => ['label' => 'Zopakovat nové heslo'],
                'mapped' => false,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Prosím zadejte nové heslo',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Vaše heslo by mělo být alespoň {{ limit }} znaků dlouhé',
                        'max' => 4096,
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => false,
        ]);
    }
}