<?php

namespace Greendot\EshopBundle\Serializer;

use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Enum\UploadGroupTypeEnum;
use Greendot\EshopBundle\Repository\Project\UploadRepository;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Service\Price\CalculatedPricesService;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;

class ProductNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;
    use SerializerAttributesTrait;

    public function __construct(
        private readonly ProductRepository       $productRepository,
        private readonly CalculatedPricesService $calculatedPricesService,
        private readonly CurrencyManager         $currencyManager,
        private readonly UploadRepository        $uploadRepository,
    ) {}

    /**
     * @param Product $object
     */
    public function normalize($object, $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::ALREADY_CALLED] = true;

        $data = $this->normalizer->normalize($object, $format, $context);

        $attributes = $context['attributes'] ?? $context['fields'][Product::class] ?? null;

        if ($this->isFieldRequested('currencySymbol', $attributes)) {
            $data['currencySymbol'] = $this->currencyManager->get()->getSymbol();
        }

        if ($this->isFieldRequested('availability', $attributes)) {
            $availability = $this->productRepository->findAvailabilityByProduct($object);
            if ($availability) {
                $data['availability'] = $this->normalizer->normalize($availability, $format, $context);
            }
        }

        if ($this->isFieldRequested('parameters', $attributes)) {
            $data['parameters'] = $this->productRepository->calculateParameters($object);
        }

        if ($this->isFieldRequested('calculatedPrices', $attributes) || $this->isFieldRequested('priceFrom', $attributes)) {

            $this->calculatedPricesService->makeCalculatedPricesForProduct($object);

            if ($this->isFieldRequested('calculatedPrices', $attributes)) {
                $data['calculatedPrices'] = $object->getCalculatedPrices();
            }

            if ($this->isFieldRequested('priceFrom', $attributes)) {
                $prices = $object->getCalculatedPrices();
                $data['priceFrom'] = isset($prices['priceNoVat']) ? $prices['priceNoVat'] : 0;
            }
        }

        if ($this->isFieldRequested('upload', $attributes) && empty($data['upload'])) {

            $dynamicUpload = $this->resolveDynamicUpload($object);

            if ($dynamicUpload) {
                $dynamicUpload->setIsDynamicallySet(true);
                $data['upload'] = $this->normalizer->normalize($dynamicUpload, $format, $context);
            }
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return $data instanceof Product && !isset($context[self::ALREADY_CALLED]);
    }

    private function resolveDynamicUpload(Product $product)
    {
        if ($product->getUpload()) {
            return $product->getUpload();
        }

        $upload = $this->uploadRepository->findFirstImageUploadForProduct($product, UploadGroupTypeEnum::IMAGE);
        if ($upload) {
            return $upload;
        }

        return $this->uploadRepository->findFirstImageUploadForProductVariants($product, UploadGroupTypeEnum::IMAGE);
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->normalizer->getSupportedTypes($format);
    }
}