<?php
/**
 * Application complète de gestion d'agence de voyage
 * Tous les fichiers fusionnés en un seul fichier PHP
 */

// ============================================
// CONFIGURATION ET INITIALISATION
// ============================================

session_start();

// Connexion minimale à la base de données
$pdo = new PDO('mysql:host=127.0.0.1:3306;dbname=compagnieaerienne', 'root', '');

// Nom de l'agence
define('NOM_AGENCE', 'Voyage Express');

// Configuration des prix
define('PRIX_KG_SUPPLEMENTAIRE', 20.00);
define('FRAIS_CARTE_BANCAIRE', 30.00);
define('FRAIS_VIREMENT', 20.00);
define('TVA', 0.20);
define('REDUCTION_3_MOIS', 0.10);
define('POIDS_BAGAGE_INCLUS', 25);

// Dates limites
define('DATE_DEPART_MIN', '2025-12-01');
define('DATE_DEPART_MAX', '2026-06-30');

// Prix des boissons
$prix_boissons = [
    'eau' => 1.50,
    'biere' => 1.20,
    'vin' => 1.70,
    'coca' => 1.80,
    'jus_orange' => 2.00
];

// Options de menu
$options_menu = [
    'entrees' => ['Salade verte', 'Soupe du jour', 'Terrine de légumes', 'Salade de fruits de mer'],
    'plats' => ['Poulet rôti', 'Saumon grillé', 'Pâtes carbonara', 'Steak haché'],
    'desserts' => ['Tarte aux pommes', 'Mousse au chocolat', 'Salade de fruits', 'Glace vanille']
];

// ============================================
// FONCTIONS UTILITAIRES
// ============================================

function getContinents($pdo) {
    $stmt = $pdo->query("SELECT * FROM continent ORDER BY nom");
    return $stmt->fetchAll();
}

function getPaysByContinent($pdo, $id_continent) {
    $stmt = $pdo->prepare("SELECT * FROM pays WHERE id_continent = ? ORDER BY nom");
    $stmt->execute([$id_continent]);
    return $stmt->fetchAll();
}

function getVillesByPays($pdo, $id_pays) {
    $stmt = $pdo->prepare("SELECT * FROM ville WHERE id_pays = ? ORDER BY nom");
    $stmt->execute([$id_pays]);
    return $stmt->fetchAll();
}

function getVolByVille($pdo, $id_ville) {
    $stmt = $pdo->prepare("SELECT * FROM vol WHERE id_ville_arrivee = ? LIMIT 1");
    $stmt->execute([$id_ville]);
    return $stmt->fetch();
}

function getDestinationInfo($pdo, $id_ville) {
    $stmt = $pdo->prepare("
        SELECT v.id_ville, v.nom as nom_ville, p.id_pays, p.nom as nom_pays,
               c.id_continent, c.nom as nom_continent,
               vol.prix_bebe, vol.prix_enfant, vol.prix_adulte
        FROM ville v
        JOIN pays p ON v.id_pays = p.id_pays
        JOIN continent c ON p.id_continent = c.id_continent
        LEFT JOIN vol ON vol.id_ville_arrivee = v.id_ville
        WHERE v.id_ville = ?
    ");
    $stmt->execute([$id_ville]);
    return $stmt->fetch();
}

function getCategorieAge($age) {
    if ($age < 2) return 'bebe';
    elseif ($age >= 2 && $age <= 11) return 'enfant';
    else return 'adulte';
}

function calculerPrixVoyageur($vol, $age) {
    $categorie = getCategorieAge($age);
    switch ($categorie) {
        case 'bebe': return floatval($vol['prix_bebe']);
        case 'enfant': return floatval($vol['prix_enfant']);
        case 'adulte': return floatval($vol['prix_adulte']);
        default: return 0;
    }
}

function calculerReduction($date_depart, $montant) {
    $date_depart_obj = new DateTime($date_depart);
    $date_aujourdhui = new DateTime();
    $interval = $date_aujourdhui->diff($date_depart_obj);
    $mois_avant = ($interval->y * 12) + $interval->m;
    return ($mois_avant >= 3) ? $montant * REDUCTION_3_MOIS : 0;
}

function calculerSupplementBagage($poids_bagage, $nb_voyageurs) {
    $poids_inclus = POIDS_BAGAGE_INCLUS * $nb_voyageurs;
    $poids_supplementaire = $poids_bagage - $poids_inclus;
    return ($poids_supplementaire > 0) ? $poids_supplementaire * PRIX_KG_SUPPLEMENTAIRE : 0;
}

function calculerTotalBoissons($boissons, $prix_boissons) {
    $total = 0;
    foreach ($boissons as $boisson => $quantite) {
        if (isset($prix_boissons[$boisson]) && $quantite > 0) {
            $total += $prix_boissons[$boisson] * $quantite;
        }
    }
    return $total;
}

function calculerFraisPaiement($moyen_paiement) {
    if ($moyen_paiement === 'Carte Bancaire') return FRAIS_CARTE_BANCAIRE;
    elseif ($moyen_paiement === 'Virement sur compte') return FRAIS_VIREMENT;
    return 0;
}

function validerNom($nom) {
    return preg_match('/^[a-zA-Z0-9\s\'-]{1,30}$/u', $nom);
}

function validerEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strpos($email, '@') !== false;
}

function validerTelephone($telephone) {
    return preg_match('/^\d+$/', $telephone);
}

function validerAdresse($adresse) {
    return strlen($adresse) <= 60 && strlen($adresse) > 0;
}

function validerDateDepart($date_depart) {
    $date_depart_obj = new DateTime($date_depart);
    $date_aujourdhui = new DateTime();
    $date_min = new DateTime(DATE_DEPART_MIN);
    $date_max = new DateTime(DATE_DEPART_MAX);
    
    if ($date_depart_obj < $date_min) {
        return ['valide' => false, 'message' => 'La date de départ doit être après le ' . date('d/m/Y', $date_min->getTimestamp())];
    }
    if ($date_depart_obj > $date_max) {
        return ['valide' => false, 'message' => 'La date de départ doit être avant le ' . date('d/m/Y', $date_max->getTimestamp())];
    }
    if ($date_depart_obj <= $date_aujourdhui) {
        return ['valide' => false, 'message' => 'La date de départ doit être postérieure à aujourd\'hui'];
    }
    return ['valide' => true, 'message' => ''];
}

function validerDateRetour($date_depart, $date_retour) {
    $date_depart_obj = new DateTime($date_depart);
    $date_retour_obj = new DateTime($date_retour);
    if ($date_retour_obj <= $date_depart_obj) {
        return ['valide' => false, 'message' => 'La date de retour doit être postérieure à la date de départ'];
    }
    return ['valide' => true, 'message' => ''];
}

function validerNombreVoyageurs($nb_voyageurs) {
    return is_numeric($nb_voyageurs) && $nb_voyageurs >= 1 && $nb_voyageurs <= 20;
}

function validerPoidsBagage($poids, $nb_voyageurs = 1) {
    return is_numeric($poids) && $poids >= 0 && $poids <= (100 * $nb_voyageurs);
}

function formaterPrix($prix) {
    return number_format($prix, 2, ',', ' ') . ' €';
}

function h($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function calculerTTC($total_ht) {
    $tva = $total_ht * TVA;
    return ['ht' => $total_ht, 'tva' => $tva, 'ttc' => $total_ht + $tva];
}

// ============================================
// GESTION DES ACTIONS
// ============================================

$action = $_GET['action'] ?? '';

// Action AJAX pour récupérer les pays
if ($action === 'getPays') {
    header('Content-Type: application/json');
    $id_continent = intval($_GET['continent'] ?? 0);
    if ($id_continent > 0) {
        echo json_encode(getPaysByContinent($pdo, $id_continent));
    } else {
        echo json_encode([]);
    }
    exit;
}

// Action AJAX pour récupérer les villes
if ($action === 'getVilles') {
    header('Content-Type: application/json');
    $id_pays = intval($_GET['pays'] ?? 0);
    if ($id_pays > 0) {
        echo json_encode(getVillesByPays($pdo, $id_pays));
    } else {
        echo json_encode([]);
    }
    exit;
}

// Action traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['action'])) {
    $erreurs = [];
    
    // Validation des données
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $mail = trim($_POST['mail'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    
    if (empty($nom) || !validerNom($nom)) $erreurs[] = "Le nom est invalide";
    if (empty($prenom) || !validerNom($prenom)) $erreurs[] = "Le prénom est invalide";
    if (empty($mail) || !validerEmail($mail)) $erreurs[] = "L'email est invalide";
    if (empty($telephone) || !validerTelephone($telephone)) $erreurs[] = "Le téléphone est invalide";
    if (empty($adresse) || !validerAdresse($adresse)) $erreurs[] = "L'adresse est invalide";
    
    $id_ville = intval($_POST['ville'] ?? 0);
    if ($id_ville <= 0) $erreurs[] = "Veuillez sélectionner une destination";
    
    $date_depart = $_POST['date_depart'] ?? '';
    $date_retour = $_POST['date_retour'] ?? '';
    
    if (empty($date_depart)) {
        $erreurs[] = "La date de départ est requise";
    } else {
        $validation_depart = validerDateDepart($date_depart);
        if (!$validation_depart['valide']) $erreurs[] = $validation_depart['message'];
    }
    
    if (empty($date_retour)) {
        $erreurs[] = "La date de retour est requise";
    } else {
        $validation_retour = validerDateRetour($date_depart, $date_retour);
        if (!$validation_retour['valide']) $erreurs[] = $validation_retour['message'];
    }
    
    $nb_voyageurs = intval($_POST['nb_voyageurs'] ?? 0);
    if (!validerNombreVoyageurs($nb_voyageurs)) $erreurs[] = "Le nombre de voyageurs doit être entre 1 et 20";
    
    $ages = $_POST['ages'] ?? [];
    $poids_bagages = $_POST['poids_bagages'] ?? [];
    
    if (count($ages) !== $nb_voyageurs) $erreurs[] = "Le nombre d'âges ne correspond pas";
    if (count($poids_bagages) !== $nb_voyageurs) $erreurs[] = "Le nombre de poids ne correspond pas";
    
    $poids_total = 0;
    foreach ($poids_bagages as $index => $poids) {
        $poids_float = floatval($poids);
        if (!validerPoidsBagage($poids_float, $nb_voyageurs)) {
            $erreurs[] = "Le poids des bagages du voyageur " . ($index + 1) . " est invalide";
        }
        $poids_total += $poids_float;
    }
    
    $entree = $_POST['entree'] ?? '';
    $plat = $_POST['plat'] ?? '';
    $dessert = $_POST['dessert'] ?? '';
    if (empty($entree) || empty($plat) || empty($dessert)) $erreurs[] = "Veuillez sélectionner un menu complet";
    
    $moyen_paiement = $_POST['moyen_paiement'] ?? '';
    if (empty($moyen_paiement) || !in_array($moyen_paiement, ['Carte Bancaire', 'Virement sur compte'])) {
        $erreurs[] = "Veuillez sélectionner un mode de paiement";
    }
    
    if (!empty($erreurs)) {
        $_SESSION['erreurs'] = $erreurs;
        $_SESSION['donnees_formulaire'] = $_POST;
        header('Location: index.php');
        exit;
    }
    
    // Récupérer les informations
    $destination = getDestinationInfo($pdo, $id_ville);
    if (!$destination) {
        $_SESSION['erreurs'] = ["Destination introuvable"];
        header('Location: index.php');
        exit;
    }
    
    $vol = getVolByVille($pdo, $id_ville);
    if (!$vol) {
        $_SESSION['erreurs'] = ["Aucun vol disponible"];
        header('Location: index.php');
        exit;
    }
    
    // Calculs
    $total_voyage = 0;
    $details_voyageurs = [];
    foreach ($ages as $index => $age) {
        $age_int = intval($age);
        $prix_voyageur = calculerPrixVoyageur($vol, $age_int);
        $total_voyage += $prix_voyageur;
        $details_voyageurs[] = [
            'age' => $age_int,
            'categorie' => getCategorieAge($age_int),
            'prix' => $prix_voyageur,
            'poids_bagage' => floatval($poids_bagages[$index])
        ];
    }
    
    $reduction = calculerReduction($date_depart, $total_voyage);
    $total_voyage_apres_reduction = $total_voyage - $reduction;
    $supplement_bagage = calculerSupplementBagage($poids_total, $nb_voyageurs);
    $boissons = $_POST['boissons'] ?? [];
    $total_boissons = calculerTotalBoissons($boissons, $prix_boissons);
    $frais_paiement = calculerFraisPaiement($moyen_paiement);
    $total_ht = $total_voyage_apres_reduction + $supplement_bagage + $total_boissons + $frais_paiement;
    $calcul_ttc = calculerTTC($total_ht);
    
    // Stocker dans la session
    $_SESSION['reservation'] = [
        'client' => ['nom' => $nom, 'prenom' => $prenom, 'mail' => $mail, 'telephone' => $telephone, 'adresse' => $adresse],
        'destination' => $destination,
        'vol' => $vol,
        'dates' => ['depart' => $date_depart, 'retour' => $date_retour],
        'voyageurs' => $details_voyageurs,
        'menu' => ['entree' => $entree, 'plat' => $plat, 'dessert' => $dessert],
        'boissons' => $boissons,
        'prix_boissons' => $prix_boissons,
        'moyen_paiement' => $moyen_paiement,
        'calculs' => [
            'total_voyage' => $total_voyage,
            'reduction' => $reduction,
            'total_voyage_apres_reduction' => $total_voyage_apres_reduction,
            'supplement_bagage' => $supplement_bagage,
            'total_boissons' => $total_boissons,
            'frais_paiement' => $frais_paiement,
            'total_ht' => $total_ht,
            'tva' => $calcul_ttc['tva'],
            'total_ttc' => $calcul_ttc['ttc'],
            'poids_total' => $poids_total
        ]
    ];
    
    header('Location: index.php?action=recap');
    exit;
}

// Action récapitulatif
if ($action === 'recap') {
    if (!isset($_SESSION['reservation'])) {
        header('Location: index.php');
        exit;
    }
    
    $reservation = $_SESSION['reservation'];
    $client = $reservation['client'];
    $destination = $reservation['destination'];
    $vol = $reservation['vol'];
    $dates = $reservation['dates'];
    $voyageurs = $reservation['voyageurs'];
    $menu = $reservation['menu'];
    $boissons = $reservation['boissons'];
    $prix_boissons = $reservation['prix_boissons'] ?? [];
    $moyen_paiement = $reservation['moyen_paiement'];
    $calculs = $reservation['calculs'];
    
    // Enregistrer dans la base de données
    try {
        $stmt = $pdo->prepare("INSERT INTO client (nom, prenom, mail, telephone, adresse) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$client['nom'], $client['prenom'], $client['mail'], $client['telephone'], $client['adresse']]);
        $id_client = $pdo->lastInsertId();
    } catch (PDOException $e) {
        die("Erreur : " . $e->getMessage());
    }
    
    $poids_total_bagages = 0;
    foreach ($voyageurs as $voyageur) {
        $poids_total_bagages += $voyageur['poids_bagage'];
    }
    
    $nb_bebe = 0; $nb_enfant = 0; $nb_adulte = 0;
    foreach ($voyageurs as $voyageur) {
        switch ($voyageur['categorie']) {
            case 'bebe': $nb_bebe++; break;
            case 'enfant': $nb_enfant++; break;
            case 'adulte': $nb_adulte++; break;
        }
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO voyage (id_client, id_vol, date_depart, date_retour, nb_adulte, nb_enfant, nb_bebe, poids_bagage, moyen_paiement) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id_client, $vol['id_vol'], $dates['depart'], $dates['retour'], $nb_adulte, $nb_enfant, $nb_bebe, intval($poids_total_bagages), $moyen_paiement]);
        $id_voyage = $pdo->lastInsertId();
    } catch (PDOException $e) {
        die("Erreur : " . $e->getMessage());
    }
    
    // Afficher le récapitulatif
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Récapitulatif - <?php echo h(NOM_AGENCE); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .recap-container { background: white; padding: 40px; }
        .recap-header { text-align: center; margin-bottom: 40px; padding-bottom: 30px; border-bottom: 3px solid; border-image: linear-gradient(135deg, #667eea 0%, #764ba2 100%) 1; }
        .recap-header h2 { color: var(--primary-color); font-size: 2.5rem; margin-bottom: 10px; }
        .recap-section { background: #f9fafb; padding: 25px; margin-bottom: 25px; border-radius: var(--radius-lg); border-left: 4px solid var(--primary-color); }
        .recap-section h3 { color: var(--primary-color); margin-bottom: 20px; font-size: 1.3rem; display: flex; align-items: center; gap: 10px; }
        .recap-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
        .recap-row:last-child { border-bottom: none; }
        .recap-label { font-weight: 600; color: var(--text-dark); }
        .recap-value { color: var(--text-medium); }
        .recap-total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: var(--radius-xl); margin-top: 30px; }
        .recap-total h3 { color: white; margin-bottom: 20px; font-size: 1.5rem; }
        .recap-total .recap-row { border-bottom-color: rgba(255, 255, 255, 0.2); color: white; }
        .recap-total .recap-label, .recap-total .recap-value { color: white; }
        .grand-total { font-size: 1.8rem; font-weight: 800; margin-top: 15px; padding-top: 15px; border-top: 2px solid rgba(255, 255, 255, 0.3); }
        .btn-actions { text-align: center; margin-top: 40px; display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; }
        .voyageur-item { background: white; padding: 15px; margin-bottom: 10px; border-radius: var(--radius-md); border-left: 3px solid var(--primary-color); }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-decoration"></div>
            <div class="header-content">
                <div class="header-icon">✈️</div>
                <h1><?php echo h(NOM_AGENCE); ?></h1>
                <div class="sous-titre">
                    <i class="fas fa-check-circle"></i>
                    <span>Réservation confirmée</span>
                </div>
            </div>
        </header>

        <main>
            <div class="recap-container">
                <div class="recap-header">
                    <h2><i class="fas fa-receipt"></i> Récapitulatif de votre Réservation</h2>
                    <p style="color: var(--text-medium);">Numéro de réservation : #<?php echo str_pad($id_voyage, 6, '0', STR_PAD_LEFT); ?></p>
                </div>

                <div class="recap-section">
                    <h3><i class="fas fa-user"></i> Informations Client</h3>
                    <div class="recap-row"><span class="recap-label">Nom :</span><span class="recap-value"><?php echo h($client['nom']); ?></span></div>
                    <div class="recap-row"><span class="recap-label">Prénom :</span><span class="recap-value"><?php echo h($client['prenom']); ?></span></div>
                    <div class="recap-row"><span class="recap-label">Email :</span><span class="recap-value"><?php echo h($client['mail']); ?></span></div>
                    <div class="recap-row"><span class="recap-label">Téléphone :</span><span class="recap-value"><?php echo h($client['telephone']); ?></span></div>
                    <div class="recap-row"><span class="recap-label">Adresse :</span><span class="recap-value"><?php echo h($client['adresse']); ?></span></div>
                </div>

                <div class="recap-section">
                    <h3><i class="fas fa-map-marked-alt"></i> Destination</h3>
                    <div class="recap-row"><span class="recap-label">Continent :</span><span class="recap-value"><?php echo h($destination['nom_continent']); ?></span></div>
                    <div class="recap-row"><span class="recap-label">Pays :</span><span class="recap-value"><?php echo h($destination['nom_pays']); ?></span></div>
                    <div class="recap-row"><span class="recap-label">Ville :</span><span class="recap-value"><?php echo h($destination['nom_ville']); ?></span></div>
                    <div class="recap-row"><span class="recap-label">Aéroport de départ :</span><span class="recap-value">Bruxelles Charleroi Sud</span></div>
                </div>

                <div class="recap-section">
                    <h3><i class="fas fa-calendar"></i> Dates de Voyage</h3>
                    <div class="recap-row"><span class="recap-label">Date de départ :</span><span class="recap-value"><?php echo date('d/m/Y', strtotime($dates['depart'])); ?></span></div>
                    <div class="recap-row"><span class="recap-label">Date de retour :</span><span class="recap-value"><?php echo date('d/m/Y', strtotime($dates['retour'])); ?></span></div>
                </div>

                <div class="recap-section">
                    <h3><i class="fas fa-users"></i> Voyageurs (<?php echo count($voyageurs); ?>)</h3>
                    <?php foreach ($voyageurs as $index => $voyageur): ?>
                        <div class="voyageur-item">
                            <strong>Voyageur <?php echo $index + 1; ?></strong> - 
                            Âge : <?php echo $voyageur['age']; ?> ans 
                            (<?php echo $voyageur['categorie'] === 'bebe' ? 'Bébé' : ($voyageur['categorie'] === 'enfant' ? 'Enfant' : 'Adulte'); ?>) - 
                            Prix : <?php echo formaterPrix($voyageur['prix']); ?> - 
                            Bagages : <?php echo $voyageur['poids_bagage']; ?> kg
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="recap-section">
                    <h3><i class="fas fa-utensils"></i> Menu (Inclus)</h3>
                    <div class="recap-row"><span class="recap-label">Entrée :</span><span class="recap-value"><?php echo h($menu['entree']); ?></span></div>
                    <div class="recap-row"><span class="recap-label">Plat :</span><span class="recap-value"><?php echo h($menu['plat']); ?></span></div>
                    <div class="recap-row"><span class="recap-label">Dessert :</span><span class="recap-value"><?php echo h($menu['dessert']); ?></span></div>
                </div>

                <?php if ($calculs['total_boissons'] > 0): ?>
                <div class="recap-section">
                    <h3><i class="fas fa-glass-water"></i> Boissons</h3>
                    <?php 
                    $noms_boissons = ['eau' => 'Eau (1/2)', 'biere' => 'Cannette Bière', 'vin' => '1/4 l Vin', 'coca' => 'Cannette Coca', 'jus_orange' => 'Jus d\'Orange'];
                    foreach ($boissons as $boisson => $quantite): 
                        if ($quantite > 0):
                    ?>
                        <div class="recap-row">
                            <span class="recap-label"><?php echo h($noms_boissons[$boisson] ?? $boisson); ?> (x<?php echo $quantite; ?>) :</span>
                            <span class="recap-value"><?php echo formaterPrix($prix_boissons[$boisson] * $quantite); ?></span>
                        </div>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="recap-section">
                    <h3><i class="fas fa-calculator"></i> Détail des Calculs</h3>
                    <div class="recap-row"><span class="recap-label">Total voyage (HT) :</span><span class="recap-value"><?php echo formaterPrix($calculs['total_voyage']); ?></span></div>
                    <?php if ($calculs['reduction'] > 0): ?>
                    <div class="recap-row" style="color: var(--success-color);">
                        <span class="recap-label">Réduction 10% (≥3 mois avant) :</span>
                        <span class="recap-value">-<?php echo formaterPrix($calculs['reduction']); ?></span>
                    </div>
                    <div class="recap-row"><span class="recap-label">Total voyage après réduction (HT) :</span><span class="recap-value"><?php echo formaterPrix($calculs['total_voyage_apres_reduction']); ?></span></div>
                    <?php endif; ?>
                    <div class="recap-row"><span class="recap-label">Supplément bagage (<?php echo $calculs['poids_total']; ?> kg, <?php echo POIDS_BAGAGE_INCLUS * count($voyageurs); ?> kg inclus) :</span><span class="recap-value"><?php echo formaterPrix($calculs['supplement_bagage']); ?></span></div>
                    <?php if ($calculs['total_boissons'] > 0): ?>
                    <div class="recap-row"><span class="recap-label">Boissons :</span><span class="recap-value"><?php echo formaterPrix($calculs['total_boissons']); ?></span></div>
                    <?php endif; ?>
                    <div class="recap-row"><span class="recap-label">Frais de paiement (<?php echo h($moyen_paiement); ?>) :</span><span class="recap-value"><?php echo formaterPrix($calculs['frais_paiement']); ?></span></div>
                </div>

                <div class="recap-total">
                    <h3><i class="fas fa-euro-sign"></i> Montant Total</h3>
                    <div class="recap-row"><span class="recap-label">Total HT :</span><span class="recap-value"><?php echo formaterPrix($calculs['total_ht']); ?></span></div>
                    <div class="recap-row"><span class="recap-label">TVA (20%) :</span><span class="recap-value"><?php echo formaterPrix($calculs['tva']); ?></span></div>
                    <div class="recap-row grand-total"><span class="recap-label">Total TTC :</span><span class="recap-value"><?php echo formaterPrix($calculs['total_ttc']); ?></span></div>
                </div>

                <div class="btn-actions">
                    <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Imprimer</button>
                    <a href="index.php" class="btn btn-secondary"><i class="fas fa-home"></i> Nouvelle Réservation</a>
                </div>
            </div>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> <?php echo h(NOM_AGENCE); ?> - Tous droits réservés</p>
        </footer>
    </div>
</body>
</html>
    <?php
    exit;
}

// ============================================
// AFFICHAGE DU FORMULAIRE (par défaut)
// ============================================

$continents = getContinents($pdo);
$donnees = $_SESSION['donnees_formulaire'] ?? [];
unset($_SESSION['donnees_formulaire']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservation - <?php echo h(NOM_AGENCE); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-decoration"></div>
            <div class="header-content">
                <div class="header-icon">✈️</div>
                <h1><?php echo h(NOM_AGENCE); ?></h1>
                <div class="sous-titre">
                    <i class="fas fa-plane-departure"></i>
                    <span>Départ depuis Bruxelles Charleroi Sud</span>
                </div>
                <div class="periode">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Voyages disponibles du 01/12/2025 au 30/06/2026</span>
                </div>
            </div>
        </header>

        <main>
            <div class="form-container">
                <div class="form-header">
                    <h2><i class="fas fa-clipboard-list"></i> Formulaire de Réservation</h2>
                    <p class="form-subtitle">Remplissez tous les champs pour réserver votre voyage</p>
                </div>

                <?php if (isset($_SESSION['erreurs']) && !empty($_SESSION['erreurs'])): ?>
                    <div class="message erreur">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <strong>Erreurs détectées :</strong>
                            <ul style="margin-top: 10px; margin-left: 20px;">
                                <?php foreach ($_SESSION['erreurs'] as $erreur): ?>
                                    <li><?php echo h($erreur); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <?php unset($_SESSION['erreurs']); ?>
                <?php endif; ?>

                <form action="index.php" method="POST" id="formReservation">
                    <div class="section-form">
                        <div class="section-header">
                            <i class="fas fa-user"></i>
                            <h3>Informations Personnelles</h3>
                        </div>

                        <div class="form-group">
                            <label for="nom">Nom <span class="requis">*</span></label>
                            <input type="text" id="nom" name="nom" required pattern="[a-zA-Z0-9\s'-]{1,30}" maxlength="30" value="<?php echo h($donnees['nom'] ?? ''); ?>" placeholder="Votre nom">
                            <small>Alphanumérique, max 30 caractères</small>
                        </div>

                        <div class="form-group">
                            <label for="prenom">Prénom <span class="requis">*</span></label>
                            <input type="text" id="prenom" name="prenom" required pattern="[a-zA-Z0-9\s'-]{1,30}" maxlength="30" value="<?php echo h($donnees['prenom'] ?? ''); ?>" placeholder="Votre prénom">
                            <small>Alphanumérique, max 30 caractères</small>
                        </div>

                        <div class="form-group">
                            <label for="mail">Email <span class="requis">*</span></label>
                            <input type="email" id="mail" name="mail" required value="<?php echo h($donnees['mail'] ?? ''); ?>" placeholder="exemple@email.com">
                            <small>Doit contenir @</small>
                        </div>

                        <div class="form-group">
                            <label for="telephone">Téléphone <span class="requis">*</span></label>
                            <input type="tel" id="telephone" name="telephone" required pattern="[0-9]+" value="<?php echo h($donnees['telephone'] ?? ''); ?>" placeholder="0123456789">
                            <small>Uniquement des chiffres</small>
                        </div>

                        <div class="form-group">
                            <label for="adresse">Adresse <span class="requis">*</span></label>
                            <textarea id="adresse" name="adresse" required maxlength="60" rows="2" placeholder="Votre adresse complète"><?php echo h($donnees['adresse'] ?? ''); ?></textarea>
                            <small>Maximum 60 caractères</small>
                        </div>
                    </div>

                    <div class="section-form">
                        <div class="section-header">
                            <i class="fas fa-map-marked-alt"></i>
                            <h3>Destination</h3>
                        </div>

                        <div class="form-group">
                            <label for="continent">Continent <span class="requis">*</span></label>
                            <select id="continent" name="continent" required>
                                <option value="">-- Sélectionnez un continent --</option>
                                <?php foreach ($continents as $continent): ?>
                                    <option value="<?php echo h($continent['id_continent']); ?>"><?php echo h($continent['nom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="pays">Pays <span class="requis">*</span></label>
                            <select id="pays" name="pays" required disabled>
                                <option value="">-- Sélectionnez d'abord un continent --</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="ville">Ville <span class="requis">*</span></label>
                            <select id="ville" name="ville" required disabled>
                                <option value="">-- Sélectionnez d'abord un pays --</option>
                            </select>
                        </div>
                    </div>

                    <div class="section-form">
                        <div class="section-header">
                            <i class="fas fa-calendar"></i>
                            <h3>Dates de Voyage</h3>
                        </div>

                        <div class="form-group">
                            <label for="date_depart">Date de Départ <span class="requis">*</span></label>
                            <input type="date" id="date_depart" name="date_depart" required min="<?php echo DATE_DEPART_MIN; ?>" max="<?php echo DATE_DEPART_MAX; ?>">
                            <small>Entre le <?php echo date('d/m/Y', strtotime(DATE_DEPART_MIN)); ?> et le <?php echo date('d/m/Y', strtotime(DATE_DEPART_MAX)); ?></small>
                        </div>

                        <div class="form-group">
                            <label for="date_retour">Date de Retour <span class="requis">*</span></label>
                            <input type="date" id="date_retour" name="date_retour" required>
                            <small>Doit être postérieure à la date de départ</small>
                        </div>
                    </div>

                    <div class="section-form">
                        <div class="section-header">
                            <i class="fas fa-users"></i>
                            <h3>Voyageurs</h3>
                        </div>

                        <div class="form-group">
                            <label for="nb_voyageurs">Nombre de Voyageurs <span class="requis">*</span></label>
                            <input type="number" id="nb_voyageurs" name="nb_voyageurs" required min="1" max="20" value="1">
                            <small>Entre 1 et 20 voyageurs</small>
                        </div>

                        <div id="voyageurs-container"></div>
                    </div>

                    <div class="section-form">
                        <div class="section-header">
                            <i class="fas fa-utensils"></i>
                            <h3>Menu (Inclus dans le tarif)</h3>
                        </div>

                        <div class="form-group">
                            <label for="entree">Entrée <span class="requis">*</span></label>
                            <select id="entree" name="entree" required>
                                <?php foreach ($options_menu['entrees'] as $entree): ?>
                                    <option value="<?php echo h($entree); ?>"><?php echo h($entree); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="plat">Plat <span class="requis">*</span></label>
                            <select id="plat" name="plat" required>
                                <?php foreach ($options_menu['plats'] as $plat): ?>
                                    <option value="<?php echo h($plat); ?>"><?php echo h($plat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="dessert">Dessert <span class="requis">*</span></label>
                            <select id="dessert" name="dessert" required>
                                <?php foreach ($options_menu['desserts'] as $dessert): ?>
                                    <option value="<?php echo h($dessert); ?>"><?php echo h($dessert); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="section-form">
                        <div class="section-header">
                            <i class="fas fa-glass-water"></i>
                            <h3>Boissons (Payantes)</h3>
                        </div>

                        <div class="form-group">
                            <label>Eau (1/2) - <?php echo formaterPrix($prix_boissons['eau']); ?>
                                <input type="number" name="boissons[eau]" min="0" value="0" class="boisson-input">
                            </label>
                        </div>

                        <div class="form-group">
                            <label>Cannette Bière - <?php echo formaterPrix($prix_boissons['biere']); ?>
                                <input type="number" name="boissons[biere]" min="0" value="0" class="boisson-input">
                            </label>
                        </div>

                        <div class="form-group">
                            <label>1/4 l Vin - <?php echo formaterPrix($prix_boissons['vin']); ?>
                                <input type="number" name="boissons[vin]" min="0" value="0" class="boisson-input">
                            </label>
                        </div>

                        <div class="form-group">
                            <label>Cannette Coca - <?php echo formaterPrix($prix_boissons['coca']); ?>
                                <input type="number" name="boissons[coca]" min="0" value="0" class="boisson-input">
                            </label>
                        </div>

                        <div class="form-group">
                            <label>Jus d'Orange - <?php echo formaterPrix($prix_boissons['jus_orange']); ?>
                                <input type="number" name="boissons[jus_orange]" min="0" value="0" class="boisson-input">
                            </label>
                        </div>
                    </div>

                    <div class="section-form">
                        <div class="section-header">
                            <i class="fas fa-credit-card"></i>
                            <h3>Mode de Paiement</h3>
                        </div>

                        <div class="payment-options">
                            <label class="payment-option">
                                <input type="radio" name="moyen_paiement" value="Carte Bancaire" required>
                                <div class="payment-card">
                                    <i class="fas fa-credit-card"></i>
                                    <div>
                                        <strong>Carte Bancaire Internationale</strong>
                                        <span>Frais : <?php echo formaterPrix(FRAIS_CARTE_BANCAIRE); ?></span>
                                    </div>
                                </div>
                            </label>

                            <label class="payment-option">
                                <input type="radio" name="moyen_paiement" value="Virement sur compte" required>
                                <div class="payment-card">
                                    <i class="fas fa-university"></i>
                                    <div>
                                        <strong>Virement Bancaire</strong>
                                        <span>Frais : <?php echo formaterPrix(FRAIS_VIREMENT); ?></span>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Valider la Réservation
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Réinitialiser
                        </button>
                    </div>
                </form>
            </div>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> <?php echo h(NOM_AGENCE); ?> - Tous droits réservés</p>
        </footer>
    </div>

    <script>
        document.getElementById('continent').addEventListener('change', function() {
            const continentId = this.value;
            const paysSelect = document.getElementById('pays');
            const villeSelect = document.getElementById('ville');
            
            paysSelect.innerHTML = '<option value="">Chargement...</option>';
            paysSelect.disabled = true;
            villeSelect.innerHTML = '<option value="">-- Sélectionnez d\'abord un pays --</option>';
            villeSelect.disabled = true;
            
            if (continentId) {
                fetch('index.php?action=getPays&continent=' + continentId)
                    .then(response => response.json())
                    .then(data => {
                        paysSelect.innerHTML = '<option value="">-- Sélectionnez un pays --</option>';
                        data.forEach(pays => {
                            const option = document.createElement('option');
                            option.value = pays.id_pays;
                            option.textContent = pays.nom;
                            paysSelect.appendChild(option);
                        });
                        paysSelect.disabled = false;
                    });
            }
        });

        document.getElementById('pays').addEventListener('change', function() {
            const paysId = this.value;
            const villeSelect = document.getElementById('ville');
            
            villeSelect.innerHTML = '<option value="">Chargement...</option>';
            villeSelect.disabled = true;
            
            if (paysId) {
                fetch('index.php?action=getVilles&pays=' + paysId)
                    .then(response => response.json())
                    .then(data => {
                        villeSelect.innerHTML = '<option value="">-- Sélectionnez une ville --</option>';
                        data.forEach(ville => {
                            const option = document.createElement('option');
                            option.value = ville.id_ville;
                            option.textContent = ville.nom;
                            villeSelect.appendChild(option);
                        });
                        villeSelect.disabled = false;
                    });
            }
        });

        document.getElementById('nb_voyageurs').addEventListener('change', function() {
            const nbVoyageurs = parseInt(this.value);
            const container = document.getElementById('voyageurs-container');
            container.innerHTML = '';
            
            for (let i = 1; i <= nbVoyageurs; i++) {
                const voyageurDiv = document.createElement('div');
                voyageurDiv.className = 'voyageur-group';
                voyageurDiv.innerHTML = `
                    <h4>Voyageur ${i}</h4>
                    <div class="form-group">
                        <label>Âge <span class="requis">*</span></label>
                        <input type="number" name="ages[]" required min="0" max="120" placeholder="Âge du voyageur">
                        <small>Âge du voyageur</small>
                    </div>
                    <div class="form-group">
                        <label>Poids des bagages (kg) <span class="requis">*</span></label>
                        <input type="number" name="poids_bagages[]" required min="0" max="100" step="0.1" placeholder="Poids en kg">
                        <small>25 kg inclus par voyageur</small>
                    </div>
                `;
                container.appendChild(voyageurDiv);
            }
        });

        document.getElementById('nb_voyageurs').dispatchEvent(new Event('change'));

        document.getElementById('date_depart').addEventListener('change', function() {
            document.getElementById('date_retour').min = this.value;
        });
    </script>
</body>
</html>
