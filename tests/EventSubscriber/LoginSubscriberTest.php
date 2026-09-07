<?php

namespace Greendot\EshopBundle\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\EventSubscriber\LoginSubscriber;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;

class LoginSubscriberTest extends TestCase
{
    private MockObject $purchaseRepository;
    private MockObject $entityManager;
    private RequestStack $requestStack;
    private LoginSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->purchaseRepository = $this->createMock(PurchaseRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = new RequestStack();

        $this->subscriber = new LoginSubscriber(
            $this->purchaseRepository,
            $this->requestStack,
            $this->entityManager,
        );
    }

    private function pushRequestWithLocale(string $locale): void
    {
        $request = new Request();
        $request->setLocale($locale);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStack->push($request);
    }

    private function buildLoginEvent(UserInterface $user): InteractiveLoginEvent
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return new InteractiveLoginEvent(new Request(), $token);
    }

    public function testUpdatesClientLocaleWhenRequestLocaleDiffers(): void
    {
        $this->pushRequestWithLocale('en');

        $client = new Client();
        $client->setLocale('cs');

        $this->purchaseRepository->method('findOneBySession')->willReturn(null);
        $this->purchaseRepository->method('findCartForClient')->willReturn(null);

        $this->entityManager->expects($this->once())->method('flush');

        $this->subscriber->onInteractiveLogin($this->buildLoginEvent($client));

        $this->assertSame('en', $client->getLocale());
    }

    public function testDoesNotFlushWhenRequestLocaleMatchesStoredLocale(): void
    {
        $this->pushRequestWithLocale('cs');

        $client = new Client();
        $client->setLocale('cs');

        $this->purchaseRepository->method('findOneBySession')->willReturn(null);
        $this->purchaseRepository->method('findCartForClient')->willReturn(null);

        $this->entityManager->expects($this->never())->method('flush');

        $this->subscriber->onInteractiveLogin($this->buildLoginEvent($client));

        $this->assertSame('cs', $client->getLocale());
    }

    public function testSetsLocaleWhenClientHasNoStoredLocaleYet(): void
    {
        $this->pushRequestWithLocale('en');

        $client = new Client();
        $client->setLocale(null);

        $this->purchaseRepository->method('findOneBySession')->willReturn(null);
        $this->purchaseRepository->method('findCartForClient')->willReturn(null);

        $this->entityManager->expects($this->once())->method('flush');

        $this->subscriber->onInteractiveLogin($this->buildLoginEvent($client));

        $this->assertSame('en', $client->getLocale());
    }

    public function testIgnoresNonClientUsers(): void
    {
        $this->pushRequestWithLocale('en');

        $user = $this->createMock(UserInterface::class);

        $this->purchaseRepository->expects($this->never())->method('findOneBySession');
        $this->entityManager->expects($this->never())->method('flush');

        $this->subscriber->onInteractiveLogin($this->buildLoginEvent($user));
    }
}
