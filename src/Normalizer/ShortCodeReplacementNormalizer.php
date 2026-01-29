<?php

namespace Greendot\EshopBundle\Normalizer;

use Greendot\EshopBundle\Service\ShortCodes\ShortCodeBase;
use Greendot\EshopBundle\Service\ShortCodes\ShortCodeProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Replaces content of shortcodes in all fields of object (only works if X-ShortCode-replace header is set to true)
 */

class ShortCodeReplacementNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    private  NormalizerInterface $normalizer;
    public function __construct(
        private readonly ShortCodeProvider $shortCodeProvider,
        private readonly RequestStack $requestStack
    ){}

    private const ALREADY_CALLED = 'SHORT_CODE_NORMALIZER';

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::ALREADY_CALLED] = true;

        $providers = $this->shortCodeProvider->getSupported(get_class($data));
        foreach ($providers as $provider){
            assert(is_subclass_of($provider, ShortCodeBase::class));
            $provider->replaceAll($data);
        }

        $data = $this->normalizer->normalize($data, $format, $context);
        return $data;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED]) || !is_object($data)){
            return false;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) return false;

        //Only trigger when header is present
        return $request->headers->get('X-ShortCode-replace') === 'true';
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->normalizer->getSupportedTypes($format);
    }

    public function setNormalizer(NormalizerInterface $normalizer): void
    {
        $this->normalizer = $normalizer;
    }
}