<?php

namespace Greendot\EshopBundle\Service\ShortCodes;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Form\WithdrawalType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class WithdrawalForm extends ShortCodeBase implements ShortCodeInterface
{
    public function __construct(
        private readonly FormFactoryInterface    $formFactory,
        private readonly Environment             $twig,
        private readonly UrlGeneratorInterface   $urlGenerator,
        private readonly string                  $formTemplate = '@GreendotEshop/forms/default.html.twig',
    ) {}

    function regex(): string
    {
        return '/@@withdrawal-form@@/';
    }

    function supportedFields(): array
    {
        return [
            Category::class => ['html'],
        ];
    }

    function replaceableContent(object $object, ?array $data = null): string
    {
        if (!$object instanceof Category) {
            return '';
        }

        return $this->twig->render($this->formTemplate, [
            'form'   => $this->formFactory->create(WithdrawalType::class)->createView(),
            'action' => $this->urlGenerator->generate('form_withdrawal_submit'),
        ]);
    }
}
