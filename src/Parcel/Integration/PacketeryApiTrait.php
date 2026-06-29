<?php

namespace Greendot\EshopBundle\Parcel\Integration;

use Throwable;
use RuntimeException;
use SimpleXMLElement;
use DateTimeImmutable;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Parcel\ParcelStatusInfoDto;
use Greendot\EshopBundle\Enum\PaymentTypeActionGroup;
use Greendot\EshopBundle\Enum\VoucherCalculationType;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Parcel\ParcelDeliveryStateEnum;
use Greendot\EshopBundle\Parcel\Exception\PermanentParcelException;
use Greendot\EshopBundle\Parcel\Exception\TransientParcelException;


trait PacketeryApiTrait
{
    private const API_URL = 'https://www.zasilkovna.cz/api/rest';

    public function getParcelStatus(Purchase $purchase): ParcelStatusInfoDto
    {
        $transportation = $purchase->getTransportation();
        $apiPassword = $transportation?->getSecretKey() ?? '';
        $packetId = $purchase->getTransportNumber();

        $xml = $this->callPacketeryApi('packetStatus', [
            'apiPassword' => $apiPassword,
            'packetId' => $packetId,
        ], 'packetStatus', $purchase);

        $statusCode = (int)$xml->result->statusCode;
        $codeText = (string)$xml->result->codeText;
        $dateTime = isset($xml->result->dateTime)
            ? new DateTimeImmutable((string)$xml->result->dateTime)
            : null;

        return new ParcelStatusInfoDto(
            state: $this->mapStatusCode($statusCode),
            details: ['statusCode' => $statusCode, 'codeText' => $codeText],
            occurredAt: $dateTime,
        );
    }

    private function callPacketeryApi(string $rootElement, array $data, string $logContext, Purchase $purchase): SimpleXMLElement
    {
        $xmlBody = $this->buildXml($rootElement, $data);

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => ['Content-Type' => 'application/xml'],
                'body' => $xmlBody,
            ]);

            $xml = simplexml_load_string($response->getContent(false));

            if ((string)$xml->status !== 'ok') {
                $rawResponse = $response->getContent(false);
                $fault = isset($xml->fault) ? (string)$xml->fault : '';
                $this->logger->error("Packeta API error on $logContext", [
                    'purchaseId' => $purchase->getId(),
                    'response' => $rawResponse,
                ]);
                if (in_array($fault, ['PacketAttributesFault', 'InvalidCourierNumber', 'InvalidApiPassword', 'SenderNotExists'], true)) {
                    throw new PermanentParcelException("Packeta $logContext failed (permanent/$fault): $rawResponse");
                }
                throw new TransientParcelException("Packeta $logContext failed: $rawResponse");
            }

            return $xml;
        } catch (PermanentParcelException | TransientParcelException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->logger->error("Packeta HTTP exception on $logContext", [
                'purchaseId' => $purchase->getId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function buildXml(string $rootElement, array $data): string
    {
        $xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><$rootElement/>");
        $this->arrayToXml($data, $xml);
        return $xml->asXML();
    }

    private function arrayToXml(array $data, SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $child = $xml->addChild($key);
                $this->arrayToXml($value, $child);
            } else {
                $xml->addChild($key, htmlspecialchars((string)$value, ENT_XML1));
            }
        }
    }

    private function mapStatusCode(int $code): ParcelDeliveryStateEnum
    {
        return match ($code) {
            1       => ParcelDeliveryStateEnum::RECEIVED_DATA,
            2, 3, 4 => ParcelDeliveryStateEnum::IN_TRANSIT,
            5       => ParcelDeliveryStateEnum::READY_FOR_PICKUP,
            7       => ParcelDeliveryStateEnum::DELIVERED,
            8       => ParcelDeliveryStateEnum::NOT_PICKED_UP,
            default => ParcelDeliveryStateEnum::CANCELLED,
        };
    }

    private function resolvePriceAndCod(Purchase $purchase, string $currency): array
    {
        $currencyEntity = $currency === 'EUR'
            ? $this->currencyRepository->findOneBy(['isDefault' => false])
            : $this->currencyRepository->findOneBy(['isDefault' => true]);

        $priceCalculator = $this->purchasePriceFactory->create($purchase, $currencyEntity);

        $value = (clone $priceCalculator)
            ->setVatCalculationType(VatCalculationType::WithVAT)
            ->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount)
            ->setVoucherCalculationType(VoucherCalculationType::WithoutVoucher)
            ->getPrice()
        ;

        $isCod = $purchase->getPaymentType()->getActionGroup() === PaymentTypeActionGroup::ON_DELIVERY;
        $cod = $isCod
            ? (clone $priceCalculator)
                ->setVatCalculationType(VatCalculationType::WithVAT)
                ->setDiscountCalculationType(DiscountCalculationType::WithDiscount)
                ->setVoucherCalculationType(VoucherCalculationType::WithVoucher)
                ->getPrice(true)
            : null;

        return ['value' => $value, 'cod' => $cod];
    }
}
