<?php

namespace Greendot\EshopBundle\Schema\Provider;

use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\Article;
use Greendot\EshopBundle\Enum\CategoryTypeEnum;
use Greendot\EshopBundle\Schema\SchemaProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Entity\Project\Category as CategoryEntity;
use Greendot\EshopBundle\Schema\UnsupportedSchemaSubjectException;

class ArticleSchemaProvider implements SchemaProviderInterface
{
    private const SUPPORTED_TYPES = [
        CategoryTypeEnum::BLOG->value,
    ];

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire(param: 'greendot_eshop.global.absolute_url')]
        private readonly string                $absoluteUrl,
    ) {}

    public function supports(mixed $object): bool
    {
        return $object instanceof CategoryEntity
            && in_array($object->getCategoryType()?->getId(), self::SUPPORTED_TYPES, true);
    }

    public function provide(mixed $object): Article
    {
        if (!$this->supports($object)) {
            throw new UnsupportedSchemaSubjectException();
        }

        /** @var CategoryEntity $object */
        $url = $this->urlGenerator->generate(
            'web_blog_detail',
            ['slug' => $object->getSlug()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $article = Schema::article()
            ->identifier(sprintf('%s#article', $url))
            ->url($url)
            ->mainEntityOfPage($url)
            ->headline($object->getTitle() ?? $object->getName())
            ->name($object->getName())
            ->description($this->stripToNull($object->getDescription()))
            ->articleBody($this->stripToNull($object->getHtml()))
        ;

        $upload = $object->getUpload();
        if ($upload?->getPath()) {
            $article->image($this->absoluteUrl . $upload->getPath());
        }

        $datePublished = $object->getPublishedAt() ?? $object->getCreatedAt();
        if ($datePublished !== null) {
            $article->datePublished($datePublished);
        }

        $keywords = array_values(array_filter(array_map(
            static fn($label) => $label->getName(),
            $object->getLabels()->toArray(),
        )));
        if (!empty($keywords)) {
            $article->keywords($keywords);
        }

        $person = $object->getPersons()->first() ?: null;
        $author = $person?->getPerson();
        if ($author !== null) {
            $article->author(Schema::person()->name($author->getFullName()));
        }

        return $article;
    }

    public function getPriority(): int
    {
        return 0;
    }

    private function stripToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stripped = trim(strip_tags($value));

        return $stripped === '' ? null : $stripped;
    }
}
