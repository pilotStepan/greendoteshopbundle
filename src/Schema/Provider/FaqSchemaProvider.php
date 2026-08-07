<?php

namespace Greendot\EshopBundle\Schema\Provider;

use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\FAQPage;
use Greendot\EshopBundle\Entity\Project\Comment;
use Greendot\EshopBundle\Schema\SchemaProviderInterface;
use Greendot\EshopBundle\Repository\Project\CommentRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Entity\Project\Category as CategoryEntity;
use Greendot\EshopBundle\Schema\UnsupportedSchemaSubjectException;

class FaqSchemaProvider implements SchemaProviderInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CommentRepository     $commentRepository,
    ) {}

    public function supports(mixed $object): bool
    {
        /* Override supports() in an app subclass and alias it over this service in config/services.yaml */
        return false;
    }

    public function provide(mixed $object): FAQPage
    {
        if (!$this->supports($object)) {
            throw new UnsupportedSchemaSubjectException();
        }

        /** @var CategoryEntity $object */
        $url = $this->urlGenerator->generate(
            $object->getControllerName(),
            ['slug' => $object->getSlug()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $questions = $this->commentRepository->findAnsweredQuestions(20);

        $mainEntity = [];
        foreach ($questions as $question) {
            /** @var Comment $question */
            $answer = null;
            foreach ($question->getUnderComment() as $reply) {
                if ($reply->isIsAdmin() && $reply->isActive()) {
                    $answer = $reply;
                    break;
                }
            }

            if ($answer === null) {
                continue;
            }

            $mainEntity[] = Schema::question()
                ->name($question->getTitle())
                ->text($question->getContent())
                ->dateCreated($question->getSubmitted())
                ->datePublished($question->getSubmitted())
                ->acceptedAnswer(Schema::answer()
                    ->name($answer->getTitle())
                    ->text(strip_tags($answer->getContent() ?? ''))
                    ->dateCreated($answer->getSubmitted())
                    ->datePublished($answer->getSubmitted()),
                )
            ;
        }

        return Schema::fAQPage()
            ->identifier(sprintf('%s#faq', $url))
            ->name($object->getTitle())
            ->description($object->getDescription())
            ->mainEntity($mainEntity)
        ;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
