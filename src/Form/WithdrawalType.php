<?php

namespace Greendot\EshopBundle\Form;

use Symfony\Component\Form\AbstractType;
use Greendot\EshopBundle\Dto\WithdrawalData;
use Karser\Recaptcha3Bundle\Form\Recaptcha3Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Karser\Recaptcha3Bundle\Validator\Constraints\Recaptcha3;


class WithdrawalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Vaše jméno a příjmení'])
            ->add('email', EmailType::class, ['label' => 'Váš e-mail (pro potvrzení)'])
            ->add('orderNumber', IntegerType::class, ['label' => 'Číslo objednávky'])
            ->add('goods', TextareaType::class, ['label' => 'Vypište zboží, které z objednávky chcete vrátit'])
            ->add('bankAccount', TextType::class, ['label' => 'Bankovní účet, kam požaduji převést částku za zboží'])
            ->add('captcha', Recaptcha3Type::class, [
                'constraints' => new Recaptcha3(),
                'locale' => 'cs',
                'action_name' => 'withdrawal',
                'attr' => [
                    'class' => 'custom-recaptcha',
                    'id' => 'withdrawal-captcha',
                ],
            ])
            ->add('submit', SubmitType::class, ['label' => 'Potvrdit odstoupení od smlouvy'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WithdrawalData::class,
            'csrf_protection' => false,
        ]);
    }
}
