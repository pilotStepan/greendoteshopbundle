<?php

namespace Greendot\EshopBundle\Service\Vies;

use Exception;
use DragonBe\Vies\Vies;
use Psr\Log\LoggerInterface;
use DragonBe\Vies\CheckVatResponse;
use Greendot\EshopBundle\Dto\VatInfo;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Utils\ViesAddressParser;

#[WithMonologChannel('api.vies')]
readonly class ManageVies
{
    public const DOMESTIC_COUNTRY_CODE = 'CZ';

    private Vies $client;

    public function __construct(private LoggerInterface $logger)
    {
        $this->client = new Vies();
    }

    /**
     * @throws Exception
     */
    public function getVatInfo(string $rawVat): VatInfo
    {
        $vat = preg_replace(
            '/[^A-Z0-9]/',
            '',
            strtoupper($rawVat),
        );

        ['country' => $country, 'id' => $vatId] = $this->client->splitVatId($vat);

        try {
            $vatResponse = $this->client->validateVat($country, $vatId);
        } catch (Exception $e) {
            $this->logger->error('VIES API error', [
                'vat' => $vat,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $this->buildVatInfo($vatResponse);
    }

    private function buildVatInfo(CheckVatResponse $vatResponse): VatInfo
    {
        $vatInfo = new VatInfo();
        $vatInfo->countryCode = $vatResponse->getCountryCode();
        $vatInfo->vatNumber = $vatResponse->getVatNumber();
        $vatInfo->requestDate = $vatResponse->getRequestDate()->format('d-m-Y H:i:s');

        $vatInfo->isValid = $vatResponse->isValid();
        $vatInfo->isForeign = $vatResponse->getCountryCode() !== self::DOMESTIC_COUNTRY_CODE;
        $vatInfo->isVatExempted = $vatInfo->isValid && $vatInfo->isForeign;

        $vatInfo->name = $vatResponse->getName() === '---' ? null : $vatResponse->getName();

        $address = $vatResponse->getAddress();
        $parsed = ViesAddressParser::parse(
            $vatResponse->getCountryCode(),
            $address === '---' ? null : $address,
        );
        $vatInfo->street = $parsed['street'];
        $vatInfo->city = $parsed['city'];
        $vatInfo->zip = $parsed['zip'];

        return $vatInfo;
    }
}