<?php
/**
 * Configuration et données de l'agence de voyage
 * Projet : Gestion d'une agence de voyage en PHP
 * 
 * Ce fichier contient toutes les données de configuration :
 * - Destinations et villes
 * - Tarifs selon l'âge et la destination
 * - Prix des boissons
 * - Paramètres généraux
 */

// Nom de l'agence
define('NOM_AGENCE', 'Voyages Express');

// Aéroport de départ
define('AEROPORT_DEPART', 'Bruxelles Charleroi Sud');

// Période de validité
define('DATE_DEBUT', '2025-12-01');
define('DATE_FIN', '2026-06-30');

// Paramètres de bagage
define('POIDS_BAGAGE_INCLUS', 25); // kg
define('PRIX_KG_SUPPLEMENTAIRE', 20); // euros

// Frais de paiement
define('FRAIS_CARTE_BANCAIRE', 30);
define('FRAIS_VIREMENT', 20);

// TVA
define('TAUX_TVA', 0.20); // 20%

// Poids bagage maximum par voyageur (kg)
define('POIDS_MAX_BAGAGE', 100);

// Réduction si réservation 3 mois avant
define('MOIS_REDUCTION', 3);
define('POURCENTAGE_REDUCTION', 0.10); // 10%

/**
 * Destinations disponibles par continent
 */
$destinations = [
    'Afrique' => [
        'Maroc' => ['Marrakech', 'Agadir'],
        'Sénégal' => ['Dakar', 'Touba'],
        'Kenya' => ['Nairobi', 'Mombasa']
    ],
    'Europe' => [
        'Espagne' => ['Madrid', 'Barcelone'],
        'Italie' => ['Rome', 'Venise'],
        'Portugal' => ['Porto', 'Lisbonne']
    ],
    'Amérique' => [
        'Etats Unis' => ['Los Angeles', 'New York'],
        'Brésil' => ['Rio de Janeiro', 'Sao Paulo'],
        'Argentine' => ['Buenos Aires', 'Mendoza']
    ],
    'Asie' => [
        'Japon' => ['Tokyo', 'Nagoya'],
        'Chine' => ['Pékin', 'Shanghai'],
        'Turquie' => ['Istanbul', 'Izmir']
    ]
];

/**
 * Tarifs aller-retour en euros depuis Bruxelles Charleroi Sud
 * Structure : [Pays][Ville][Age][Aéroport] = Prix
 * 
 * Note: Les tarifs sont valables si réservation < 3 mois avant le départ
 * Si réservation >= 3 mois avant : réduction de 10%
 * 
 * Ages:
 * - 0: < 2 ans
 * - 1: 2 <= age <= 11
 * - 2: >= 12 ans
 */
$tarifs = [
    'Japon' => [
        'Tokyo' => [
            '0' => [200, 210],      // <2 ans [Aéroport1, Aéroport2]
            '1' => [250, 260],      // 2-11 ans
            '2' => [400, 450]       // >=12 ans
        ],
        'Nagoya' => [
            '0' => [200, 210],
            '1' => [250, 260],
            '2' => [400, 450]
        ]
    ],
    'Chine' => [
        'Pékin' => [
            '0' => [180, 190],
            '1' => [240, 250],
            '2' => [390, 420]
        ],
        'Shanghai' => [
            '0' => [180, 190],
            '1' => [240, 250],
            '2' => [390, 420]
        ]
    ],
    'Turquie' => [
        'Istanbul' => [
            '0' => [100, 120],
            '1' => [150, 160],
            '2' => [250, 260]
        ],
        'Izmir' => [
            '0' => [100, 120],
            '1' => [150, 160],
            '2' => [250, 260]
        ]
    ],
    'Etats Unis' => [
        'Los Angeles' => [
            '0' => [160, 170],
            '1' => [180, 200],
            '2' => [280, 300]
        ],
        'New York' => [
            '0' => [160, 170],
            '1' => [180, 200],
            '2' => [280, 300]
        ]
    ],
    'Brésil' => [
        'Rio de Janeiro' => [
            '0' => [180, 190],
            '1' => [250, 260],
            '2' => [380, 400]
        ],
        'Sao Paulo' => [
            '0' => [180, 190],
            '1' => [250, 260],
            '2' => [380, 400]
        ]
    ],
    'Argentine' => [
        'Buenos Aires' => [
            '0' => [200, 220],
            '1' => [260, 290],
            '2' => [390, 420]
        ],
        'Mendoza' => [
            '0' => [200, 220],
            '1' => [260, 290],
            '2' => [390, 420]
        ]
    ],
    'Espagne' => [
        'Madrid' => [
            '0' => [150, 160],
            '1' => [200, 220],
            '2' => [390, 400]
        ],
        'Barcelone' => [
            '0' => [150, 160],
            '1' => [200, 220],
            '2' => [390, 400]
        ]
    ],
    'Italie' => [
        'Rome' => [
            '0' => [160, 190],
            '1' => [220, 250],
            '2' => [400, 450]
        ],
        'Venise' => [
            '0' => [160, 190],
            '1' => [220, 250],
            '2' => [400, 450]
        ]
    ],
    'Portugal' => [
        'Porto' => [
            '0' => [150, 160],
            '1' => [200, 230],
            '2' => [350, 380]
        ],
        'Lisbonne' => [
            '0' => [150, 160],
            '1' => [200, 230],
            '2' => [350, 380]
        ]
    ],
    'Maroc' => [
        'Marrakech' => [
            '0' => [140, 150],
            '1' => [180, 190],
            '2' => [250, 300]
        ],
        'Agadir' => [
            '0' => [140, 150],
            '1' => [180, 190],
            '2' => [250, 300]
        ]
    ],
    'Sénégal' => [
        'Dakar' => [
            '0' => [200, 220],
            '1' => [250, 290],
            '2' => [380, 400]
        ],
        'Touba' => [
            '0' => [200, 220],
            '1' => [250, 290],
            '2' => [380, 400]
        ]
    ],
    'Kenya' => [
        'Nairobi' => [
            '0' => [220, 250],
            '1' => [290, 320],
            '2' => [400, 420]
        ],
        'Mombasa' => [
            '0' => [220, 250],
            '1' => [290, 320],
            '2' => [400, 420]
        ]
    ]
];

/**
 * Prix des boissons en euros
 */
$boissons = [
    'Eau (1/2)' => 1.50,
    'Cannette bière' => 1.20,
    'Vin (1/4 l)' => 1.70,
    'Cannette coca' => 1.80,
    'Jus d\'orange' => 2.00
];

/**
 * Options de menu
 */
$menu_entrees = [
    'Salade verte',
    'Soupe du jour',
    'Carpaccio',
    'Plateau de charcuterie'
];

$menu_plats = [
    'Poulet rôti',
    'Saumon grillé',
    'Pâtes à la carbonara',
    'Steak frites',
    'Plat végétarien'
];

$menu_desserts = [
    'Tarte aux pommes',
    'Mousse au chocolat',
    'Salade de fruits',
    'Glace vanille'
];

?>

