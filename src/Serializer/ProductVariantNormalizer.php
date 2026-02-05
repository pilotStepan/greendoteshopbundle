<?php

namespace Greendot\EshopBundle\Serializer;

use Greendot\EshopBundle\Enum\UploadGroupTypeEnum;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Repository\Project\UploadRepository;
use Greendot\EshopBundle\Service\Price\CalculatedPricesService;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;

class ProductVariantNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;
    use SerializerAttributesTrait;

    public function __construct(
        private readonly CalculatedPricesService $calculatedPricesService,
        private readonly UploadRepository        $uploadRepository,
    ) {}

    /**
     * @param ProductVariant $object
     */
    public function normalize($object, $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::ALREADY_CALLED] = true;

        $data = $this->normalizer->normalize($object, $format, $context);

        $attributes = $context['attributes'] ?? $context['fields'][ProductVariant::class] ?? null;

        if ($this->isFieldRequested('calculatedPrices', $attributes)) {
            $this->calculatedPricesService->makeCalculatedPricesForProductVariant($object);
            $data['calculatedPrices'] = $object->getCalculatedPrices();
        }

        if ($this->isFieldRequested('upload', $attributes) && !isset($data['upload'])) {
            $foundUpload = $this->findDynamicUpload($object);

            if ($foundUpload) {
                $foundUpload->setIsDynamicallySet(true);
                $data['upload'] = $this->normalizer->normalize($foundUpload, $format, $context);
            }
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return $data instanceof ProductVariant && !isset($context[self::ALREADY_CALLED]);
    }

    private function findDynamicUpload(ProductVariant $productVariant)
    {
        if ($productVariant->getUpload()) {
            return null;
        }

        $parentProduct = $productVariant->getProduct();
        $parentUpload = $parentProduct?->getUpload();

        if ($parentUpload !== null && !$parentUpload->isDynamicallySet()) {
            return $parentUpload;
        }

        return $this->uploadRepository->findFirstImageUploadForVariant($productVariant, UploadGroupTypeEnum::IMAGE);

    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->normalizer->getSupportedTypes($format);
    }
}