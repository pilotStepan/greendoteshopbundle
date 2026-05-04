<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Repository\Project\TransportationRepository;
use Greendot\EshopBundle\Repository\Project\UploadRepository;
use Symfony\Component\HttpFoundation\RequestStack;

use function PHPUnit\Framework\throwException;

readonly class UploadProductWithVariantsProvider implements ProviderInterface
{
    public function __construct(
        private UploadRepository            $uploadRepository,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|null|object
    {        
        $uploads = $this->uploadRepository->getProductWithVariantsUploads($uriVariables['id']);
        dd($uploads);
        
        return $uploads;
    }
}