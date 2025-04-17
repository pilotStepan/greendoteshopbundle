<?php

namespace Greendot\EshopBundle\Service;


use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class AresService
{
    public function __construct(
        private HttpClientInterface $httpClient,
    )
    {
    }

    public function fetchCompanyByIc(string $ic): array
    {
        if (!strlen($ic) == 8 && preg_match('/^\d+$/', $ic)) {
            return ['error' => 'Špatný formát IČO'];
        }

        $url = "https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/$ic";

        try {
            $response = $this->httpClient->request('GET', $url);
            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                return ['error' => 'Požadovaná data nenalezena'];
            }

            $data = json_decode($response->getContent(), true);
        } catch (ExceptionInterface $e) {
            return ['error' => 'Požadovaná data nenalezena'];
        }

        if (empty($data) || !isset($data['sidlo'], $data['obchodniJmeno'])) {
            return ['error' => 'Nebylo možné načíst data'];
        }

        $sidlo = $data['sidlo'];
        $street = $sidlo['nazevUlice'] . ' ' . $sidlo['cisloDomovni'];
        if (isset($sidlo['cisloOrientacni'])) {
            $street .= '/' . $sidlo['cisloOrientacni'];
        }

        return [
            'data' => [
                'city' => $sidlo['nazevObce'],
                'street' => $street,
                'zip' => $sidlo['psc'],
                'company' => $data['obchodniJmeno'],
                'dic' => $data['dic'] ?? null
            ]
        ];
    }

    /* @throws ExceptionInterface */
    public function searchAddresses(
        string  $street,
        ?string $city = null,
        ?string $zip = null,
        int     $start = 0,
        int     $itemCount = 8
    ): array
    {
        $url = "https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/standardizovane-adresy/vyhledat";

        if (!$street) return [];

        $address = $street;

        if ($zip and $city) {
            $address .= ", " . $zip . " " . $city;
        } elseif ($zip or $city) {
            $address .= ", ";
            $address .= $city ?: $zip;
        }

        $data = [
            "start" => $start,
            "pocet" => $itemCount,
            "razeni" => [""],
            "textovaAdresa" => $address,
            "typStandardizaceAdresy" => "VYHOVUJICI_ADRESY"
        ];
        $returnedData = $this->getRequestPost($url, json_encode($data));

        if ($returnedData instanceof \stdClass && property_exists($returnedData, 'pocetCelkem') && property_exists($returnedData, 'standardizovaneAdresy')) {
            return ["count" => $returnedData->pocetCelkem, "data" => $this->formatAddresses($returnedData->standardizovaneAdresy)];
        } elseif ($returnedData instanceof \stdClass && property_exists($returnedData, 'subKod')) {
            if ($returnedData->subKod == "VYSTUP_PRILIS_MNOHO_VYSLEDKU") {
                return ["error" => "Prosím upřesněte adresu"];
            } else {
                return ["count" => 0, "data" => []];
            }
        } else {
            return ["count" => 0, "data" => []];
        }
    }

    /* @throws ExceptionInterface */
    private function getRequestPost(string $url, ?string $postData = null): mixed
    {
        $options['headers'] = [
            'Accept' => "application/json",
            'Content-Type' => "application/json"
        ];

        if ($postData) {
            $options["body"] = $postData;
        }

        $return = $this->httpClient->request('POST', $url, $options);

        try {
            $return = $return->getContent();
            return json_decode($return);
        } catch (ClientException $exception) {
            $exception = $exception->getResponse();
        } catch (\Exception $exception) {
            return [];
        }

        try {
            $return = $exception->getContent(false);
            return json_decode($return);
        } catch (\Exception $exception) {
            dump($exception);
        }

        return [];
    }

    private function formatAddresses(array $addresses): array
    {
        $formatted = [];
        foreach ($addresses as $address) {
            $addr = (array)$address;
            $entry = ['text' => $addr['textovaAdresa'], 'raw' => []];

            if (isset($addr['nazevObce'])) {
                $entry['raw']['city'] = $addr['nazevObce'];
            }
            if (isset($addr['psc'])) {
                $entry['raw']['zip'] = $addr['psc'];
            }

            if (isset($addr['nazevObce']) && $addr['nazevObce'] === 'Praha') {
                $districtKey = isset($addr['nazevMestskeCastiObvodu'])
                    ? 'nazevMestskeCastiObvodu'
                    : 'nazevMestskehoObvoduP';
                $entry['raw']['district'] = $addr[$districtKey] ?? '';
            }

            $street = '';
            if (isset($addr['nazevUlice'])) {
                $street = $addr['nazevUlice'];
            } elseif (isset($addr['nazevCastiObce'])) {
                $street = $addr['nazevCastiObce'];
            }

            if (isset($addr['cisloDomovni'])) {
                $street .= ' ' . $addr['cisloDomovni'];
            }
            if (isset($addr['cisloOrientacni'])) {
                $street .= '/' . $addr['cisloOrientacni'];
            }

            if ($street) {
                $entry['raw']['street'] = $street;
            }

            $formatted[] = $entry;
        }

        return $formatted;
    }
}