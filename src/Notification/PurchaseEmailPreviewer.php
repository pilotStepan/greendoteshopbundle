<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Notification;

use InvalidArgumentException;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Workflow\Action\ActionKind;
use Greendot\EshopBundle\Workflow\Action\ActionEntry;
use Greendot\EshopBundle\Workflow\Action\PurchaseTransitionActionResolver;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * Renders the e-mail a transition's notification would send, from the purchase as it is now.
 * The real e-mail is rendered after the transition, so whatever the transition itself changes
 * (invoice number, state, paid flag) can differ.
 */
final readonly class PurchaseEmailPreviewer
{
    public function __construct(
        #[Target('purchase_flow')]
        private WorkflowInterface                $purchaseWorkflow,
        private PurchaseTransitionActionResolver $resolver,
        private PurchaseNotificationRuntime      $runtime,
        private BodyRendererInterface            $bodyRenderer,
    ) {}

    /**
     * @throws InvalidArgumentException when the transition has no previewable e-mail of that alias for this purchase
     */
    public function preview(Purchase $purchase, string $transitionName, string $alias): EmailPreview
    {
        $transition = $this->resolver->findTransition($purchase, $this->purchaseWorkflow, $transitionName);

        $configured = array_filter(
            $this->resolver->entries($this->purchaseWorkflow, $transition),
            static fn (ActionEntry $e) => $e->key === $alias && $e->kind === ActionKind::NOTIFICATION,
        );
        if (!$configured) {
            throw new InvalidArgumentException(sprintf('Transition "%s" has no notification "%s".', $transitionName, $alias));
        }

        $handler = $this->resolver->notificationHandler($alias);
        if (!$handler instanceof PreviewableEmailNotificationInterface) {
            throw new InvalidArgumentException(sprintf('Notification "%s" has no e-mail preview.', $alias));
        }

        $notificationKey = $this->resolver->notificationKey($this->purchaseWorkflow, $transition);

        return $this->runtime->run($purchase, function () use ($handler, $purchase, $notificationKey, $alias): EmailPreview {
            $email = $handler->buildEmail($purchase, $notificationKey);
            if ($email === null) {
                throw new InvalidArgumentException(sprintf('Notification "%s" sends nothing for this purchase.', $alias));
            }

            $this->bodyRenderer->render($email);
            $html = $email->getHtmlBody();

            return new EmailPreview(
                subject: (string) $email->getSubject(),
                to: array_map(static fn (Address $a) => $a->toString(), $email->getTo()),
                cc: array_map(static fn (Address $a) => $a->toString(), $email->getCc()),
                html: is_resource($html) ? (string) stream_get_contents($html) : (string) $html,
            );
        });
    }
}
