<?php

namespace App\Support\Rating;

class RatingDistributionCatalog
{
    /**
     * IDs mirror the current base seed data.
     *
     * @return array<int, array{name: string, subtypes: array<int, string>}>
     */
    public static function types(): array
    {
        return [
            1 => [
                'name' => 'Personenversicherungen',
                'subtypes' => [
                    1 => 'Krankenversicherung',
                    2 => 'Lebensversicherung',
                    112 => 'Unfallversicherung',
                    4 => 'Berufsunfaehigkeitsversicherung',
                    5 => 'Pflegeversicherung',
                    6 => 'Rentenversicherung',
                    7 => 'Reiseversicherung',
                    8 => 'Sterbegeldversicherung',
                    9 => 'Krankentagegeldversicherung',
                    10 => 'Zahnzusatzversicherung',
                    99 => 'Auslandskrankenversicherung',
                    12 => 'Dread-Disease-Versicherung',
                    13 => 'Praemien-Rueckerstattungsversicherung',
                    14 => 'Private Pflegezusatzversicherung',
                    15 => 'Zusatzversicherungen Krankenhaeuser',
                    16 => 'Krankenhauszusatzversicherung',
                    17 => 'Kapitalbildende Lebensversicherung',
                    18 => 'Risiko-Lebensversicherung',
                    19 => 'Fondsgebundene Lebensversicherung',
                    20 => 'Rueckdeckungsversicherung',
                    21 => 'Risiko-Lebensversicherung fuer Kredite',
                ],
            ],
            2 => [
                'name' => 'Sachversicherungen',
                'subtypes' => [
                    22 => 'Hausratversicherung',
                    23 => 'Wohngebaeudeversicherung',
                    24 => 'Kfz-Versicherung',
                    25 => 'Haftpflichtversicherung',
                    26 => 'Teilkasko',
                    27 => 'Vollkasko',
                    28 => 'Glasversicherung',
                    29 => 'Warentransportversicherung',
                    30 => 'Luftfrachtversicherung',
                    31 => 'Privatrechtsschutz',
                    32 => 'Berufsrechtsschutz',
                    33 => 'Verkehrsrechtsschutz',
                    34 => 'Mietrechtsschutz',
                    49 => 'Privathaftpflichtversicherung',
                    51 => 'Bauherrenhaftpflichtversicherung',
                    52 => 'Tierhalterhaftpflichtversicherung',
                    38 => 'Vermieterhaftpflichtversicherung',
                    39 => 'Jagdhaftpflichtversicherung',
                    53 => 'Umwelthaftpflichtversicherung',
                    54 => 'Produkthaftpflichtversicherung',
                    42 => 'Schmuckversicherung',
                    68 => 'Warenlager-Versicherung',
                    44 => 'Kunstversicherung',
                    45 => 'Bargeldversicherung',
                    46 => 'Sammlungsversicherung',
                    47 => 'Photovoltaikanlagenversicherung',
                    48 => 'Ernteversicherung',
                ],
            ],
            3 => [
                'name' => 'Haftpflichtversicherungen',
                'subtypes' => [
                    49 => 'Privathaftpflichtversicherung',
                    50 => 'Berufshaftpflichtversicherung',
                    51 => 'Bauherrenhaftpflichtversicherung',
                    52 => 'Tierhalterhaftpflichtversicherung',
                    53 => 'Umwelthaftpflichtversicherung',
                    54 => 'Produkthaftpflichtversicherung',
                    55 => 'Vermieter-Haftpflichtversicherung',
                    56 => 'Jagd-Haftpflichtversicherung',
                    57 => 'Feuerwehr- und Katastrophenschutz-Haftpflichtversicherung',
                ],
            ],
            4 => [
                'name' => 'Vermoegensversicherungen',
                'subtypes' => [
                    58 => 'Rechtsschutzversicherung',
                    59 => 'Kreditversicherung',
                    60 => 'Forderungsausfallversicherung',
                    61 => 'Forderungsausfallversicherung fuer Unternehmen',
                    62 => 'Wertgegenstandsversicherung',
                    114 => 'Baufinanzierungsversicherung',
                    95 => 'Veranstaltungsversicherung',
                    65 => 'Eventversicherung',
                    66 => 'Festivalversicherung',
                    67 => 'Messeversicherung',
                    68 => 'Warenlager-Versicherung',
                ],
            ],
            5 => [
                'name' => 'Gewerbe- und Unternehmensversicherungen',
                'subtypes' => [
                    69 => 'Betriebshaftpflichtversicherung',
                    70 => 'Produktversicherung',
                    71 => 'Geschaeftsinhaltsversicherung',
                    72 => 'Cyber-Versicherung',
                    73 => 'Hacker-Angriff-Versicherung',
                    74 => 'Datenschutz-Versicherung',
                    75 => 'Maschinenversicherung',
                    76 => 'Rechtsschutzversicherung fuer Unternehmen',
                    77 => 'Bauleistungsversicherung',
                    78 => 'Baugewaehrleistungsversicherung',
                    79 => 'Gruppen-Lebensversicherung fuer Unternehmen',
                    80 => 'Gruppen-Unfallversicherung',
                    81 => 'Fahrzeugversicherung fuer Unternehmen',
                    82 => 'Ertragsausfallversicherung',
                ],
            ],
            6 => [
                'name' => 'Spezialversicherungen',
                'subtypes' => [
                    83 => 'Flughafenversicherung',
                    84 => 'Flugzeugversicherung',
                    85 => 'Haustier-Versicherung',
                    86 => 'Hundehalterversicherung',
                    87 => 'Pferdeversicherung',
                    88 => 'Pferdehaftpflichtversicherung',
                    89 => 'Versicherung fuer exotische Tiere',
                    90 => 'Bootsversicherung',
                    91 => 'Yachtenversicherung',
                    92 => 'Vereinsversicherung',
                    93 => 'Profisportversicherung',
                    94 => 'Sportgeraeteversicherung',
                    95 => 'Veranstaltungsversicherung',
                    96 => 'Jagdversicherung',
                ],
            ],
            7 => [
                'name' => 'Zusatzversicherungen',
                'subtypes' => [
                    97 => 'Zusatzversicherung fuer Zahnbehandlungen',
                    98 => 'Reiseabbruchversicherung',
                    99 => 'Auslandskrankenversicherung',
                    100 => 'Kosmetikversicherung',
                    101 => 'Pflegezusatzversicherung',
                    102 => 'Reha-Versicherung',
                    103 => 'Reisegepaeckversicherung',
                    104 => 'Kosmetik- und Schoenheitsoperationen-Versicherung',
                ],
            ],
            8 => [
                'name' => 'Weitere Versicherungen',
                'subtypes' => [
                    105 => 'Kryptowaehrungsversicherung',
                    106 => 'Photovoltaikversicherung',
                    107 => 'Windparkversicherung',
                    108 => 'Bausparversicherungen',
                    109 => 'Pensionsversicherung',
                    110 => 'Arbeitslosenversicherung',
                    111 => 'Altersvorsorgeversicherung',
                    112 => 'Unfallversicherung',
                    113 => 'Nahrungsmittelversicherung',
                    114 => 'Baufinanzierungsversicherung',
                    115 => 'Immobilienbewertungsversicherung',
                ],
            ],
            9 => [
                'name' => 'Ergaenzungen und spezialisierte Versicherungen',
                'subtypes' => [],
            ],
        ];
    }

    /**
     * @return array<int, float>
     */
    public static function defaultTypeWeights(): array
    {
        $weights = [];

        foreach (self::types() as $typeId => $type) {
            $weights[$typeId] = count($type['subtypes']) > 0 ? 1.0 : 0.0;
        }

        return $weights;
    }

    /**
     * @return array<int, array<int, float>>
     */
    public static function defaultSubtypeWeights(): array
    {
        $weights = [];

        foreach (self::types() as $typeId => $type) {
            foreach ($type['subtypes'] as $subtypeId => $name) {
                $weights[$typeId][$subtypeId] = 1.0;
            }
        }

        return $weights;
    }
}
