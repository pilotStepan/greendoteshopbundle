<?php

namespace Greendot\EshopBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ClientController extends AbstractController
{
    #[Route("/api/current-user", name: "api_current_user")]
    public function getCurrentUser()
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
        }

        $addresses = $user->getClientAddresses();

        if ($addresses->isEmpty()) {
            return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
        }

        $lastAddress = $addresses->toArray();
        usort($lastAddress, fn($a, $b) => $b->getId() <=> $a->getId());
        $lastAddress = reset($lastAddress);

        return $this->json([
            'id'              => $user->getId(),
            'name'            => $user->getName(),
            'surname'         => $user->getSurname(),
            'mail'            => $user->getMail(),
            'phone'           => $user->getPhone(),
            'street'          => $lastAddress->getStreet(),
            'city'            => $lastAddress->getCity(),
            'zip'             => $lastAddress->getZip(),
            'country'         => $lastAddress->getCountry(),
            'company'         => $lastAddress->getCompany(),
            'ic'              => $lastAddress->getIc(),
            'dic'             => $lastAddress->getDic(),
            'shipName'        => $lastAddress->getShipName(),
            'shipSurname'     => $lastAddress->getShipSurname(),
            'shipStreet'      => $lastAddress->getShipStreet(),
            'shipCity'        => $lastAddress->getShipCity(),
            'shipZip'         => $lastAddress->getShipZip(),
            'shipCountry'     => $lastAddress->getShipCountry(),
            'agreeNewsletter' => $user->isAgreeNewsletter(),
        ]);
    }
}