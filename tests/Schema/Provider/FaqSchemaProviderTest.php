<?php

namespace Greendot\EshopBundle\Tests\Schema\Provider;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Doctrine\Common\Collections\ArrayCollection;
use Greendot\EshopBundle\Entity\Project\Comment;
use Greendot\EshopBundle\Schema\Provider\FaqSchemaProvider;
use Greendot\EshopBundle\Repository\Project\CommentRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Entity\Project\Category as CategoryEntity;

class FaqSchemaProviderTest extends TestCase
{
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private CommentRepository&MockObject $commentRepository;
    private FaqSchemaProvider $provider;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->commentRepository = $this->createMock(CommentRepository::class);
        $this->provider = new FaqSchemaProvider($this->urlGenerator, $this->commentRepository);
    }

    public function testSupportsReturnsFalseByDefault(): void
    {
        // The base provider matches nothing until an app subclass overrides supports() for its
        // own category_type id — there is no shared "FAQ category" concept across projects.
        $category = $this->createMock(CategoryEntity::class);
        $this->assertFalse($this->provider->supports($category));
    }

    public function testSupportsReturnsFalseForNonCategoryObject(): void
    {
        $this->assertFalse($this->provider->supports(new \stdClass()));
        $this->assertFalse($this->provider->supports(null));
    }

    public function testProvideBuildsFaqPageFromAnsweredQuestions(): void
    {
        $provider = new class($this->urlGenerator, $this->commentRepository) extends FaqSchemaProvider {
            public function supports(mixed $object): bool
            {
                return $object instanceof CategoryEntity;
            }
        };

        $category = $this->createMock(CategoryEntity::class);
        $category->method('getControllerName')->willReturn('app_master');
        $category->method('getSlug')->willReturn('poradna');
        $category->method('getTitle')->willReturn('Poradna');
        $category->method('getDescription')->willReturn('Odpovědi na vaše dotazy');

        $expectedUrl = 'https://example.com/poradna';
        $this->urlGenerator
            ->method('generate')
            ->with('app_master', ['slug' => 'poradna'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn($expectedUrl);

        $submitted = new \DateTimeImmutable('2026-03-17T13:56:58+00:00');
        $answered = new \DateTimeImmutable('2026-03-18T11:38:29+00:00');

        $answer = $this->createMock(Comment::class);
        $answer->method('isIsAdmin')->willReturn(true);
        $answer->method('isActive')->willReturn(true);
        $answer->method('getTitle')->willReturn('Re: otázka');
        $answer->method('getContent')->willReturn('<p>Odpověď</p>');
        $answer->method('getSubmitted')->willReturn($answered);

        $question = $this->createMock(Comment::class);
        $question->method('getTitle')->willReturn('Otázka');
        $question->method('getContent')->willReturn('Text otázky');
        $question->method('getSubmitted')->willReturn($submitted);
        $question->method('getUnderComment')->willReturn(new ArrayCollection([$answer]));

        $this->commentRepository
            ->expects($this->once())
            ->method('findAnsweredQuestions')
            ->with(20)
            ->willReturn([$question]);

        $schema = $provider->provide($category);
        $array = $schema->toArray();

        $this->assertSame('FAQPage', $array['@type']);
        $this->assertSame($expectedUrl . '#faq', $array['@id']);
        $this->assertSame('Poradna', $array['name']);
        $this->assertCount(1, $array['mainEntity']);
        $this->assertSame('Otázka', $array['mainEntity'][0]['name']);
        $this->assertSame('Re: otázka', $array['mainEntity'][0]['acceptedAnswer']['name']);
        $this->assertSame('Odpověď', $array['mainEntity'][0]['acceptedAnswer']['text']);
    }

    public function testProvideSkipsQuestionsWithoutAResolvedAnswer(): void
    {
        $provider = new class($this->urlGenerator, $this->commentRepository) extends FaqSchemaProvider {
            public function supports(mixed $object): bool
            {
                return $object instanceof CategoryEntity;
            }
        };

        $category = $this->createMock(CategoryEntity::class);
        $this->urlGenerator->method('generate')->willReturn('https://example.com/poradna');

        $inactiveReply = $this->createMock(Comment::class);
        $inactiveReply->method('isIsAdmin')->willReturn(true);
        $inactiveReply->method('isActive')->willReturn(false);

        $question = $this->createMock(Comment::class);
        $question->method('getUnderComment')->willReturn(new ArrayCollection([$inactiveReply]));

        $this->commentRepository->method('findAnsweredQuestions')->willReturn([$question]);

        $array = $provider->provide($category)->toArray();

        $this->assertSame([], $array['mainEntity']);
    }

    public function testGetPriorityReturnsZero(): void
    {
        $this->assertSame(0, $this->provider->getPriority());
    }
}
