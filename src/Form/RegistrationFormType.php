<?php

namespace Greendot\EshopBundle\Form;

use Greendot\EshopBundle\Entity\Project\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Jméno'])
            ->add('surname', TextType::class, ['label' => 'Příjmení'])
            ->add('mail', EmailType::class, ['label' => 'Přihlašovací e-mail'])
            ->add('phone', TelType::class, ['label' => 'Telefon'])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'label' => 'Heslo',
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a password',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Your password should be at least {{ limit }} characters',
                        'max' => 4096,
                    ]),
                ],
            ])
            ->add('clientAddresses', ClientAddressFormType::class, [
                'label' => 'Address Information',
                'mapped' => false,
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => 'Souhlasím se zpracováním osobních údajů',
                'constraints' => [
                    new IsTrue([
                        'message' => 'Musíte souhlasit se zpracováním údajů.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
        ]);
    }
}