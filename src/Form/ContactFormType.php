<?php

namespace Greendot\EshopBundle\Form;

use Greendot\EshopBundle\Entity\Project\ContactMessage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, ['label'=>'Jméno'])
            ->add('email', EmailType::class, ['label'=>'E-mail'])
            ->add('content', null, ['label'=>'Vaše zpráva'])
            ->add('submit', SubmitType::class, ['label'=>'Odeslat'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactMessage::class,
        ]);
    }
}
