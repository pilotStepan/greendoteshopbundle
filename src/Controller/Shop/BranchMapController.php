<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Greendot\EshopBundle\Attribute\CustomApiEndpoint;
use Greendot\EshopBundle\Service\BranchMapProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Attribute\Route;

class BranchMapController extends AbstractController
{
    public function __construct(private readonly BranchMapProvider $branchMapProvider) {}

    #[CustomApiEndpoint]
    #[Route('/api/branches/map', name: 'api_branches_map', methods: ['GET'])]
    public function getBranchMap(Request $request): Response
    {
        $groupId = $request->query->getInt('group');
        $country = trim((string)$request->query->get('country', ''));

        if ($groupId < 1 || !preg_match('/^[a-zA-Z]{2}$/', $country)) {
            return new JsonResponse(['statusText' => 'Invalid group or country'], 400);
        }

        $data = $this->branchMapProvider->get($groupId, $country);

        $response = new JsonResponse();
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        $response->setCache(['etag' => $data['etag'], 'private' => true, 'max_age' => 600]);

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response->setJson($data['json']);
    }
}
