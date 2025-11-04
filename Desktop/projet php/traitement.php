<?php
/**
 * Page de traitement du formulaire de réservation
 * 
 * Cette page valide toutes les données du formulaire,
 * calcule les prix et redirige vers la page récapitulatif
 */

session_start();
require_once 'config.php';
require_once 'fonctions.php';

// Vérifier que le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Tableau pour stocker les erreurs
$erreurs = [];

// Récupération et validation des données personnelles
$nom = trim($_POST['nom'] ?? '');
$prenom = trim($_POST['prenom'] ?? '');
$email = trim($_POST['email'] ?? '');
$telephone = trim($_POST['telephone'] ?? '');
$adresse = trim($_POST['adresse'] ?? '');

// Validation des données personnelles
if (!validerNom($nom)) {
    $erreurs[] = "Le nom est invalide (alphanumérique, max 30 caractères)";
}

if (!validerNom($prenom)) {
    $erreurs[] = "Le prénom est invalide (alphanumérique, max 30 caractères)";
}

if (!validerEmail($email)) {
    $erreurs[] = "L'email est invalide (doit contenir @)";
}

if (!validerTelephone($telephone)) {
    $erreurs[] = "Le téléphone est invalide (uniquement des chiffres)";
}

if (!validerAdresse($adresse)) {
    $erreurs[] = "L'adresse est invalide (max 60 caractères)";
}

// Récupération des données de voyage
$continent = trim($_POST['continent'] ?? '');
$pays = trim($_POST['pays'] ?? '');
$ville = trim($_POST['ville'] ?? '');
$aeroport = intval($_POST['aeroport'] ?? -1);
$date_depart = trim($_POST['date_depart'] ?? '');
$date_retour = trim($_POST['date_retour'] ?? '');

// Validation des données de voyage
if (empty($continent) || !isset($destinations[$continent])) {
    $erreurs[] = "Le continent sélectionné est invalide";
}

if (empty($pays) || !isset($destinations[$continent][$pays])) {
    $erreurs[] = "Le pays sélectionné est invalide";
}

if (empty($ville) || !in_array($ville, $destinations[$continent][$pays])) {
    $erreurs[] = "La ville sélectionnée est invalide";
}

if ($aeroport < 0 || $aeroport > 1) {
    $erreurs[] = "L'aéroport sélectionné est invalide";
}

if (empty($date_depart) || empty($date_retour)) {
    $erreurs[] = "Les dates de voyage sont requises";
} else {
    $validationDates = validerDates($date_depart, $date_retour);
    if (!$validationDates['valide']) {
        $erreurs[] = $validationDates['message'];
    }
}

// Récupération des données des voyageurs
$nb_voyageurs = intval($_POST['nb_voyageurs'] ?? 0);
$ages = $_POST['ages'] ?? [];
$poids = $_POST['poids'] ?? [];

// Validation du nombre de voyageurs
if (!validerEntierPositif($nb_voyageurs)) {
    $erreurs[] = "Le nombre de voyageurs doit être un entier positif";
}

// Validation des données des voyageurs
if (count($ages) !== $nb_voyageurs || count($poids) !== $nb_voyageurs) {
    $erreurs[] = "Les données des voyageurs sont incomplètes";
}

// Validation de chaque voyageur
$voyageurs = [];
for ($i = 0; $i < $nb_voyageurs; $i++) {
    $age = intval($ages[$i] ?? 0);
    $poidsBagage = floatval($poids[$i] ?? 0);
    
    if ($age < 0 || $age > 120) {
        $erreurs[] = "L'âge du voyageur " . ($i + 1) . " est invalide";
    }
    
    if ($poidsBagage < 0 || $poidsBagage > POIDS_MAX_BAGAGE) {
        $erreurs[] = "Le poids des bagages du voyageur " . ($i + 1) . " est invalide (max " . POIDS_MAX_BAGAGE . " kg)";
    }
    
    $voyageurs[] = [
        'age' => $age,
        'poids_bagage' => $poidsBagage
    ];
}

// Récupération du menu
$entree = trim($_POST['entree'] ?? '');
$plat = trim($_POST['plat'] ?? '');
$dessert = trim($_POST['dessert'] ?? '');

if (empty($entree) || !in_array($entree, $menu_entrees)) {
    $erreurs[] = "L'entrée sélectionnée est invalide";
}

if (empty($plat) || !in_array($plat, $menu_plats)) {
    $erreurs[] = "Le plat sélectionné est invalide";
}

if (empty($dessert) || !in_array($dessert, $menu_desserts)) {
    $erreurs[] = "Le dessert sélectionné est invalide";
}

// Récupération des boissons
$boissonsSelectionnees = $_POST['boissons'] ?? [];

// Récupération du mode de paiement
$mode_paiement = trim($_POST['mode_paiement'] ?? '');

if ($mode_paiement !== 'carte' && $mode_paiement !== 'virement') {
    $erreurs[] = "Le mode de paiement est invalide";
}

// Si des erreurs existent, rediriger vers le formulaire avec les erreurs
if (!empty($erreurs)) {
    $params = http_build_query([
        'erreur' => implode(' | ', $erreurs),
        'nom' => $nom,
        'prenom' => $prenom,
        'email' => $email,
        'telephone' => $telephone,
        'adresse' => $adresse,
        'continent' => $continent,
        'pays' => $pays,
        'ville' => $ville,
        'aeroport' => $aeroport,
        'date_depart' => $date_depart,
        'date_retour' => $date_retour,
        'nb_voyageurs' => $nb_voyageurs
    ]);
    header('Location: index.php?' . $params);
    exit;
}

// ============================================
// CALCULS DES PRIX
// ============================================

$dateReservation = date('Y-m-d');
$reductionApplicable = aDroitReduction($date_depart, $dateReservation);

// Calculer le prix pour chaque voyageur
$totalVoyageHT = 0;
$totalBagagesHT = 0;
$detailsVoyageurs = [];

foreach ($voyageurs as $index => $voyageur) {
    $age = $voyageur['age'];
    $poidsBagage = $voyageur['poids_bagage'];
    
    // Tarif de base
    $tarifBase = calculerTarifBase($pays, $ville, $age, $aeroport);
    
    // Réduction si applicable
    $reduction = calculerReduction($tarifBase, $reductionApplicable);
    $tarifAvecReduction = $tarifBase - $reduction;
    
    // Prix des bagages supplémentaires
    $prixBagages = calculerPrixBagages($poidsBagage);
    
    // Total pour ce voyageur
    $totalVoyageur = $tarifAvecReduction + $prixBagages;
    
    $totalVoyageHT += $tarifAvecReduction;
    $totalBagagesHT += $prixBagages;
    
    $detailsVoyageurs[] = [
        'numero' => $index + 1,
        'age' => $age,
        'categorie_age' => getCategorieAge($age),
        'tarif_base' => $tarifBase,
        'reduction' => $reduction,
        'tarif_avec_reduction' => $tarifAvecReduction,
        'poids_bagage' => $poidsBagage,
        'poids_supplementaire' => max(0, $poidsBagage - POIDS_BAGAGE_INCLUS),
        'prix_bagages' => $prixBagages,
        'total' => $totalVoyageur
    ];
}

// Calculer le prix des boissons
$totalBoissonsHT = calculerPrixBoissons($boissonsSelectionnees);

// Calculer les frais de paiement
$fraisPaiementHT = calculerFraisPaiement($mode_paiement);

// Total HT
$totalHT = $totalVoyageHT + $totalBagagesHT + $totalBoissonsHT + $fraisPaiementHT;

// Calcul TVA
$montantTVA = calculerTVA($totalHT);

// Total TTC
$totalTTC = calculerTTC($totalHT);

// Stocker toutes les données dans la session pour la page récapitulatif
$_SESSION['reservation'] = [
    // Informations personnelles
    'nom' => $nom,
    'prenom' => $prenom,
    'email' => $email,
    'telephone' => $telephone,
    'adresse' => $adresse,
    
    // Informations de voyage
    'continent' => $continent,
    'pays' => $pays,
    'ville' => $ville,
    'aeroport' => $aeroport,
    'date_depart' => $date_depart,
    'date_retour' => $date_retour,
    'date_reservation' => $dateReservation,
    
    // Voyageurs
    'nb_voyageurs' => $nb_voyageurs,
    'voyageurs' => $detailsVoyageurs,
    
    // Menu
    'entree' => $entree,
    'plat' => $plat,
    'dessert' => $dessert,
    
    // Boissons
    'boissons' => $boissonsSelectionnees,
    
    // Paiement
    'mode_paiement' => $mode_paiement,
    
    // Calculs
    'reduction_applicable' => $reductionApplicable,
    'total_voyage_ht' => $totalVoyageHT,
    'total_bagages_ht' => $totalBagagesHT,
    'total_boissons_ht' => $totalBoissonsHT,
    'frais_paiement_ht' => $fraisPaiementHT,
    'total_ht' => $totalHT,
    'montant_tva' => $montantTVA,
    'total_ttc' => $totalTTC
];

// Rediriger vers la page récapitulatif
header('Location: recapitulatif.php');
exit;

?>

