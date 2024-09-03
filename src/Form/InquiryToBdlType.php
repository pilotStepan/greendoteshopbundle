<?php

namespace Greendot\EshopBundle\Form;

use Greendot\EshopBundle\Entity\Project\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\Translation\TranslatorInterface;

class InquiryToBdlType extends AbstractType
{

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullname', null, [
                'label' => $this->translator->trans("Jméno a příjmení*"),
                'required' => true,
                'attr' => [
                    'foreach_render' => true
                ]
            ])
            ->add('company', null, [
                'label' => $this->translator->trans('Název organizace'),
                'required' => false,
                'attr' => [
                    'foreach_render' => true
                ]
            ])
            ->add('mail', EmailType::class, [
                'label' => $this->translator->trans('Kontaktní E-mail*'),
                'required' => true,
                'attr' => [
                    'foreach_render' => true
                ]
            ])
            ->add('phone', TelType::class, [
                'label' => $this->translator->trans('Tel. Číslo*'),
                'required' => true,
                'attr' => [
                    'foreach_render' => true
                ],
                'constraints' => [
                    new NotBlank(),
                    new Regex('/^[\s+\d]+$/')
                ]
            ])
            ->add('note', TextareaType::class, [
                'label' => $this->translator->trans('Poznámka'),
                'required' => false,
                'attr' => [
                    'cols' => 30,
                    'rows' => 10,
                    'foreach_render' => true
                ]
            ])
            ->add('notify_email', EmailType::class, [
                'label' => $this->translator->trans('E-mail pro notifikace'),
                'required' => false,
                'help' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Explicabo officiis deserunt tempore consectetur qui.',
                'attr' => [
                    'foreach_render' => false
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => $this->translator->trans('Odeslat poptávku')
            ]);
    }

}
