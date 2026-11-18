<?php

namespace Greendot\EshopBundle\Tests\Entity;

use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Comment;
use PHPUnit\Framework\TestCase;

class CommentTest extends TestCase
{
    public function testNotificationsAppendOldestFirst(): void
    {
        $question = new Comment();
        self::assertSame([], $question->getNotifications());

        $question->addNotification('old@example.com', new \DateTimeImmutable('2026-01-01 10:00:00+01:00'));
        $question->addNotification('new@example.com', new \DateTimeImmutable('2026-02-01 10:00:00+01:00'));

        self::assertSame([
            ['sentAt' => '2026-01-01T10:00:00+01:00', 'email' => 'old@example.com'],
            ['sentAt' => '2026-02-01T10:00:00+01:00', 'email' => 'new@example.com'],
        ], $question->getNotifications());
    }

    public function testNotificationsKeepOtherExtraKeys(): void
    {
        $question = new Comment();
        (fn() => $this->extra = ['other' => 1])->call($question);

        $question->addNotification('guest@example.com', new \DateTimeImmutable());

        self::assertSame(1, (fn() => $this->extra['other'])->call($question));
        self::assertCount(1, $question->getNotifications());
    }

    public function testAuthorEmailPrefersClientMail(): void
    {
        $question = (new Comment())->setEmail('typed@example.com');
        self::assertSame('typed@example.com', $question->getAuthorEmail());

        $question->setClient((new Client())->setMail('client@example.com'));
        self::assertSame('client@example.com', $question->getAuthorEmail());
    }

    public function testAuthorEmailIsNullWithoutAnyEmail(): void
    {
        self::assertNull((new Comment())->getAuthorEmail());
        self::assertNull((new Comment())->setEmail('')->getAuthorEmail());
    }

    public function testPublishedAdminReplyIgnoresDraftsAndCustomerReplies(): void
    {
        $question = new Comment();
        $question->addUnderComment((new Comment())->setIsAdmin(true)->setIsActive(false));
        $question->addUnderComment((new Comment())->setIsAdmin(false)->setIsActive(true));
        self::assertNull($question->getPublishedAdminReply());

        $published = (new Comment())->setIsAdmin(true)->setIsActive(true);
        $question->addUnderComment($published);
        self::assertSame($published, $question->getPublishedAdminReply());
    }
}
