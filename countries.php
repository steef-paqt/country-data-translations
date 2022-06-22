<?php

require __DIR__.'/vendor/autoload.php';

use LaLit\XML2Array;

/**
 * @see https://op.europa.eu/en/web/eu-vocabularies/dataset/-/resource?uri=http://publications.europa.eu/resource/dataset/country
 * Version:         20220316-0 LATEST
 * URI:             http://publications.europa.eu/resource/dataset/country
 * Type of dataset: Name authority list
 */
$xml = file_get_contents('./countries.xml');

$parsed = XML2Array::createArray($xml);
$countries = $parsed['countries']['record'];
$languages = [
    'de' => 'deu',
    'en' => 'eng',
    'es' => 'spa',
    'fr' => 'fra',
    'nl' => 'nld',
    'pt' => 'por',
];

foreach ($languages as $iso2 => $iso3) {
    $list = (new CountriesParser($iso3))->getList($countries);

    $json = json_encode($list, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    file_put_contents('./output/' . $iso2 . '.json', $json);
}

class CountriesParser
{
    private string $countryCode = '';

    public function __construct(string $countryCode)
    {
        $this->countryCode = $countryCode;
    }

    public function getList(array $countries): array
    {
        $list = [];
        $unknown = [];
        foreach ($countries as $country) {
            if (isset($country['@attributes']['deprecated'])
                && $country['@attributes']['deprecated'] === 'true'
            ) {
                echo 'deprecated: ' . $this->getName($country) . PHP_EOL;
                continue;
            }
            $isoCode = strtolower($this->getCode($country));
            $countryName = $this->getName($country);
            $countryDemonym = $this->getDemonym($country);
            $values = [
                'country'     => $countryName,
                'nationality' => $countryDemonym,
            ];

            if ($countryDemonym !== null) {
                $list[$isoCode] = $values;
            } else {
                $unknown[$isoCode] = $values;
            }
        }
        ksort($list);
        ksort($unknown);
        $list += $unknown;

        return $list;
    }

    private function getName(array $country)
    {
        return $this->getValue($country['label']['lg.version']);
    }

    private function getDemonym(array $country)
    {
        return $this->getValue($country['adjective']['lg.version']);
    }

    private function getValue(array $labels)
    {
        foreach ($labels as $label) {
            if (!isset($label['@attributes']['lg'])) {
                continue;
            }
            if ($label['@attributes']['lg'] === $this->countryCode) {
                return $label['@value'];
            }
        }

        return null;
    }

    private function getCode(array $country)
    {
        if (isset($country['code-3166-1-alpha-2'])) {
            return $country['code-3166-1-alpha-2'];
        }
        if (isset($country['code-IBAN-COU'])) {
            return $country['code-IBAN-COU'];
        }

        return '??';
    }
}
