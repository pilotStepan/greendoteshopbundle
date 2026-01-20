<?php

namespace Greendot\EshopBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class InquiryToFileType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('company', null, [
                'label' => $this->translator->trans('Uveďte název organizace, ve které pracujete'),
                'required' => true,
            ])
            ->add('notify_email', EmailType::class, [
                'label' => $this->translator->trans('E-mail pro notifikace'),
                'required' => false,
                'help' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Explicabo officiis deserunt tempore consectetur qui.',
            ])
            ->add('submit', SubmitType::class, ['label' => 'STÁHNOUT'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
