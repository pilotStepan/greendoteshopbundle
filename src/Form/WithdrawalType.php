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
use Symfony\Contracts\Translation\TranslatorInterface;


class WithdrawalType extends AbstractType
{

    public function __construct(
        private TranslatorInterface $translator
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => $this->translator->trans('Vaše jméno a příjmení')])
            ->add('email', EmailType::class, ['label' => $this->translator->trans('Váš e-mail (pro zaslání potvrzení)')])
            ->add('orderNumber', IntegerType::class, ['label' => $this->translator->trans('Číslo objednávky nebo faktury')])
            ->add('goods', TextareaType::class, ['label' => $this->translator->trans('Vypište zboží, které z objednávky chcete vrátit včetně čísla produktu')])
            ->add('bankAccount', TextType::class, ['label' => $this->translator->trans('Bankovní účet, kam požaduji převést částku za zboží (pro sk platby uveďte IBAN)')])
            ->add('captcha', Recaptcha3Type::class, [
                'constraints' => new Recaptcha3(),
                'locale' => 'cs',
                'action_name' => 'withdrawal',
                'attr' => [
                    'class' => 'custom-recaptcha',
                    'id' => 'withdrawal-captcha',
                ],
            ])
            ->add('submit', SubmitType::class, ['label' => $this->translator->trans('Potvrdit odstoupení od smlouvy')])
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
