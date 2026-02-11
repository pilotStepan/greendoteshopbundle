<?php

namespace Greendot\EshopBundle\Service\Sitemaps;

use Doctrine\ORM\QueryBuilder;
use Greendot\EshopBundle\Enum\CategoryTypeEnum;
use Greendot\EshopBundle\Enum\ReservedCategoryIds;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CategorySitemap implements SitemapProviderInterface
{
    use SitemapTrait;

    private readonly bool $blogHasLanding;

    public function __construct(
        private readonly CategoryRepository    $categoryRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        ParameterBagInterface                  $parameterBag

    )
    {
        $this->blogHasLanding = $parameterBag->get('greendot_eshop.blog.has_landing');
    }

    public function name(): string
    {
        return 'category';
    }

    public function addToSitemapIndex(\SimpleXMLElement $xmlUrlSet): void
    {
        $this->addToIndex($xmlUrlSet, $this->urlGenerator->generate('sitemap_default', ['type' => $this->name()], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function generateSiteMap(array $options = []): Response
    {
        $xml = $this->blankUrlSet();
        $categoryQB = $this->sitemapCategoryQB();
        $categories = $categoryQB->select('category.id as id, category.slug as slug, IDENTITY(category.categoryType) as categoryType')
            ->getQuery()->getArrayResult();

        foreach ($categories as ['id' => $id, 'slug' => $slug, 'categoryType' => $categoryType]) {
            $categoryUrl = $this->resolveCategoryUrl($id, $categoryType, $slug);
            if (!$categoryUrl) continue;

            $this->addToUrlSet($xml, $categoryUrl);
        }

        return $this->generateXmlResponse($xml);
    }

    private function resolveCategoryUrl(int $id, ?int $categoryType, string $slug): ?string
    {
        $parameters = ['slug' => $slug];
        $controllerName = null;
        if (ReservedCategoryIds::tryFrom($id)) {
            if ($id == ReservedCategoryIds::HOMEPAGE->value) {
                $parameters = [];
                $controllerName = 'web_homepage';
            }
            if ($id == ReservedCategoryIds::BLOG->value) {
                if (!$this->blogHasLanding) return null;
                $parameters = [];
                $controllerName = 'web_blog_landing';
            }
        }

        if (!$controllerName) {
            $controllerName = match ($categoryType) {
                CategoryTypeEnum::BLOG->value => 'web_blog_detail',
                default => 'app_master',
            };
        }


        return $this->urlGenerator->generate($controllerName, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }


    private function sitemapCategoryQB(): QueryBuilder
    {
        return $this->categoryRepository->createQueryBuilder('category')
            ->andWhere('category.isActive = 1')
            ->andWhere('category.isIndexable = 1')
            ->andWhere('category.published_at < :now OR category.published_at IS NULL')->setParameter('now', new \DateTime());
    }
}