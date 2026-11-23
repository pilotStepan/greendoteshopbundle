<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Tests\Notification;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\FilterCollection;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Notification\PreviewableEmailNotificationInterface;
use Greendot\EshopBundle\Notification\PurchaseEmailPreviewer;
use Greendot\EshopBundle\Notification\PurchaseNotificationHandlerInterface;
use Greendot\EshopBundle\Notification\PurchaseNotificationRuntime;
use Greendot\EshopBundle\Service\PurchaseLocaleResolver;
use Greendot\EshopBundle\Tests\Workflow\Action\TransitionActionTestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class PurchaseEmailPreviewerTest extends TestCase
{
    use TransitionActionTestTrait;

    private function previewable(): PurchaseNotificationHandlerInterface
    {
        return new class implements PurchaseNotificationHandlerInterface, PreviewableEmailNotificationInterface {
            public function buildEmail(Purchase $purchase, string $transition): TemplatedEmail
            {
                return (new TemplatedEmail())
                    ->to('jana@example.com')
                    ->subject('Objednávka ' . $transition)
                    ->htmlTemplate('email/order.html.twig')
                    ->context(['transition' => $transition]);
            }

            public function handle(Purchase $purchase, string $transition): void {}
        };
    }

    private function previewer(array $shipMetadata): PurchaseEmailPreviewer
    {
        $workflow = $this->buildWorkflow($shipMetadata);
        $resolver = $this->buildResolver([], [
            'purchase_notification.customer_email' => $this->previewable(),
            'purchase_notification.plain' => $this->plainNotification(),
        ]);

        $filters = $this->createMock(FilterCollection::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getFilters')->willReturn($filters);

        $twig = new Environment(new ArrayLoader(['email/order.html.twig' => '<p>{{ transition }}</p>']));

        return new PurchaseEmailPreviewer(
            $workflow,
            $resolver,
            new PurchaseNotificationRuntime(new PurchaseLocaleResolver('cs'), new LocaleSwitcher('cs', []), $em),
            new BodyRenderer($twig),
        );
    }

    public function testRendersTheConfiguredEmailWithTheNotificationKey(): void
    {
        $previewer = $this->previewer(['notifications' => ['purchase_notification.customer_email'], 'notification_key' => 'log_send']);
        $preview = $previewer->preview($this->purchase(), 'ship', 'purchase_notification.customer_email');

        $this->assertSame('Objednávka log_send', $preview->subject);
        $this->assertSame(['jana@example.com'], $preview->to);
        $this->assertStringContainsString('<p>log_send</p>', $preview->html);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function notPreviewable(): iterable
    {
        yield 'not configured on the transition' => [['notifications' => ['purchase_notification.plain']], 'purchase_notification.customer_email'];
        yield 'handler without preview' => [['notifications' => ['purchase_notification.plain']], 'purchase_notification.plain'];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('notPreviewable')]
    public function testRejectsAliasesWithoutPreview(array $metadata, string $alias): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->previewer($metadata)->preview($this->purchase(), 'ship', $alias);
    }
}
