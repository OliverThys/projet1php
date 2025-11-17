<?php
// Projet agence de voyage
// J'ai tout foutu dans un seul fichier, c'est plus simple comme ça

session_start(); // Pour garder les données entre les pages

// Connexion à la DB
// J'utilise PDO comme on a vu en cours, c'est plus safe
$pdo = new PDO('mysql:host=127.0.0.1:3306;dbname=compagnieaerienne', 'root', '');

define('NOM_AGENCE', 'Voyage Express');

// Constantes pour les prix
// J'ai mis ça en constantes pour pas avoir à chercher partout dans le code
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
// FONCTIONS
// ============================================

// Récupère tous les continents
function getContinents($pdo) {
    $stmt = $pdo->query("SELECT * FROM continent ORDER BY nom");
    return $stmt->fetchAll();
}

// Récupère les pays d'un continent
function getPaysByContinent($pdo, $id_continent) {
    $stmt = $pdo->prepare("SELECT * FROM pays WHERE id_continent = ? ORDER BY nom");
    $stmt->execute([$id_continent]);
    return $stmt->fetchAll();
}

// Récupère les villes d'un pays
function getVillesByPays($pdo, $id_pays) {
    $stmt = $pdo->prepare("SELECT * FROM ville WHERE id_pays = ? ORDER BY nom");
    $stmt->execute([$id_pays]);
    return $stmt->fetchAll();
}

// Récupère le vol pour une ville
// J'ai mis LIMIT 1 au cas où il y en aurait plusieurs
function getVolByVille($pdo, $id_ville) {
    $stmt = $pdo->prepare("SELECT * FROM vol WHERE id_ville_arrivee = ? LIMIT 1");
    $stmt->execute([$id_ville]);
    return $stmt->fetch();
}

// Récupère toutes les infos de la destination
// J'ai fait un JOIN pour tout récupérer d'un coup, c'est plus pratique
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

// Détermine si c'est un bébé, enfant ou adulte selon l'âge
function getCategorieAge($age) {
    if ($age < 2) return 'bebe';
    elseif ($age >= 2 && $age <= 11) return 'enfant';
    else return 'adulte';
}

// Calcule le prix selon l'âge
function calculerPrixVoyageur($vol, $age) {
    $categorie = getCategorieAge($age);
    switch ($categorie) {
        case 'bebe': return floatval($vol['prix_bebe']);
        case 'enfant': return floatval($vol['prix_enfant']);
        case 'adulte': return floatval($vol['prix_adulte']);
        default: return 0;
    }
}

// Calcule la réduction si on réserve 3 mois avant
function calculerReduction($date_depart, $montant) {
    $date_depart_obj = new DateTime($date_depart);
    $date_aujourdhui = new DateTime();
    $interval = $date_aujourdhui->diff($date_depart_obj);
    $mois_avant = ($interval->y * 12) + $interval->m;
    return ($mois_avant >= 3) ? $montant * REDUCTION_3_MOIS : 0;
}

// Calcule le supplément pour les bagages en trop
function calculerSupplementBagage($poids_bagage, $nb_voyageurs) {
    $poids_inclus = POIDS_BAGAGE_INCLUS * $nb_voyageurs;
    $poids_supplementaire = $poids_bagage - $poids_inclus;
    return ($poids_supplementaire > 0) ? $poids_supplementaire * PRIX_KG_SUPPLEMENTAIRE : 0;
}

// Calcule le total des boissons
function calculerTotalBoissons($boissons, $prix_boissons) {
    $total = 0;
    foreach ($boissons as $boisson => $quantite) {
        if (isset($prix_boissons[$boisson]) && $quantite > 0) {
            $total += $prix_boissons[$boisson] * $quantite;
        }
    }
    return $total;
}

// Calcule les frais selon le moyen de paiement
function calculerFraisPaiement($moyen_paiement) {
    if ($moyen_paiement === 'Carte Bancaire') return FRAIS_CARTE_BANCAIRE;
    elseif ($moyen_paiement === 'Virement sur compte') return FRAIS_VIREMENT;
    return 0;
}

// Validation du nom (alphanumérique, max 30 caractères)
function validerNom($nom) {
    return preg_match('/^[a-zA-Z0-9\s\'-]{1,30}$/u', $nom);
}

// Validation de l'email (doit contenir @)
function validerEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strpos($email, '@') !== false;
}

// Validation du téléphone (que des chiffres)
function validerTelephone($telephone) {
    return preg_match('/^\d+$/', $telephone);
}

// Validation de l'adresse (max 60 caractères)
function validerAdresse($adresse) {
    return strlen($adresse) <= 60 && strlen($adresse) > 0;
}

// Validation de la date de départ
// Vérifie que c'est dans la période autorisée et après aujourd'hui
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

// Validation de la date de retour (doit être après le départ)
function validerDateRetour($date_depart, $date_retour) {
    $date_depart_obj = new DateTime($date_depart);
    $date_retour_obj = new DateTime($date_retour);
    if ($date_retour_obj <= $date_depart_obj) {
        return ['valide' => false, 'message' => 'La date de retour doit être postérieure à la date de départ'];
    }
    return ['valide' => true, 'message' => ''];
}

// Validation du nombre de voyageurs (entre 1 et 20)
function validerNombreVoyageurs($nb_voyageurs) {
    return is_numeric($nb_voyageurs) && $nb_voyageurs >= 1 && $nb_voyageurs <= 20;
}

// Validation du poids des bagages
function validerPoidsBagage($poids, $nb_voyageurs = 1) {
    return is_numeric($poids) && $poids >= 0 && $poids <= (100 * $nb_voyageurs);
}

// Formate le prix en euros
function formaterPrix($prix) {
    return number_format($prix, 2, ',', ' ') . ' €';
}

// Fonction pour échapper les caractères HTML (sécurité contre XSS)
function h($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Calcule le TTC avec la TVA
function calculerTTC($total_ht) {
    $tva = $total_ht * TVA;
    return ['ht' => $total_ht, 'tva' => $tva, 'ttc' => $total_ht + $tva];
}

// ============================================
// GESTION DES ACTIONS
// ============================================

$action = $_GET['action'] ?? '';

// Action pour mettre à jour les menus déroulants
// Quand on change continent ou pays, le formulaire se soumet et recharge la page
if ($action === 'updateSelects') {
    $_SESSION['form_data'] = $_POST;
    header('Location: index.php');
    exit;
}

// Traitement du formulaire quand on clique sur "Valider"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['action'])) {
    $erreurs = [];
    
    // Validation de toutes les données
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
    
    // Si il y a des erreurs, on les sauvegarde et on redirige vers le formulaire
    if (!empty($erreurs)) {
        $_SESSION['erreurs'] = $erreurs;
        $_SESSION['donnees_formulaire'] = $_POST;
        header('Location: index.php');
        exit;
    }
    
    // Récupère les infos de la destination
    $destination = getDestinationInfo($pdo, $id_ville);
    if (!$destination) {
        $_SESSION['erreurs'] = ["Destination introuvable"];
        header('Location: index.php');
        exit;
    }
    
    // Récupère le vol
    $vol = getVolByVille($pdo, $id_ville);
    if (!$vol) {
        $_SESSION['erreurs'] = ["Aucun vol disponible"];
        header('Location: index.php');
        exit;
    }
    
    // Calcule les prix pour chaque voyageur
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
    
    // Calcule tous les montants (réduction, bagages, boissons, etc.)
    $reduction = calculerReduction($date_depart, $total_voyage);
    $total_voyage_apres_reduction = $total_voyage - $reduction;
    $supplement_bagage = calculerSupplementBagage($poids_total, $nb_voyageurs);
    $boissons = $_POST['boissons'] ?? [];
    $total_boissons = calculerTotalBoissons($boissons, $prix_boissons);
    $frais_paiement = calculerFraisPaiement($moyen_paiement);
    $total_ht = $total_voyage_apres_reduction + $supplement_bagage + $total_boissons + $frais_paiement;
    $calcul_ttc = calculerTTC($total_ht);
    
    // Stocke tout dans la session pour le récap
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

// Page récapitulatif
if ($action === 'recap') {
    // Vérifie qu'il y a bien une réservation
    if (!isset($_SESSION['reservation'])) {
        header('Location: index.php');
        exit;
    }
    
    // Récupère toutes les données
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
    
    // Enregistre le client dans la DB
    try {
        $stmt = $pdo->prepare("INSERT INTO client (nom, prenom, mail, telephone, adresse) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$client['nom'], $client['prenom'], $client['mail'], $client['telephone'], $client['adresse']]);
        $id_client = $pdo->lastInsertId();
    } catch (PDOException $e) {
        die("Erreur : " . $e->getMessage());
    }
    
    // Calcule le poids total des bagages
    $poids_total_bagages = 0;
    foreach ($voyageurs as $voyageur) {
        $poids_total_bagages += $voyageur['poids_bagage'];
    }
    
    // Compte combien il y a de bébés, enfants et adultes
    $nb_bebe = 0; $nb_enfant = 0; $nb_adulte = 0;
    foreach ($voyageurs as $voyageur) {
        switch ($voyageur['categorie']) {
            case 'bebe': $nb_bebe++; break;
            case 'enfant': $nb_enfant++; break;
            case 'adulte': $nb_adulte++; break;
        }
    }
    
    // Enregistre le voyage dans la DB
    try {
        $stmt = $pdo->prepare("INSERT INTO voyage (id_client, id_vol, date_depart, date_retour, nb_adulte, nb_enfant, nb_bebe, poids_bagage, moyen_paiement) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id_client, $vol['id_vol'], $dates['depart'], $dates['retour'], $nb_adulte, $nb_enfant, $nb_bebe, intval($poids_total_bagages), $moyen_paiement]);
        $id_voyage = $pdo->lastInsertId();
    } catch (PDOException $e) {
        die("Erreur : " . $e->getMessage());
    }
    
    // Affiche le récap
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
// AFFICHAGE DU FORMULAIRE
// ============================================

// Récupère les continents
$continents = getContinents($pdo);

// Récupère les données du formulaire (soit après erreur, soit pour garder les sélections)
$donnees = $_SESSION['donnees_formulaire'] ?? $_SESSION['form_data'] ?? [];
unset($_SESSION['donnees_formulaire']);
unset($_SESSION['form_data']);

// Récupère les pays si un continent est sélectionné
$pays = [];
if (!empty($donnees['continent'])) {
    $pays = getPaysByContinent($pdo, intval($donnees['continent']));
}

// Récupère les villes si un pays est sélectionné
$villes = [];
if (!empty($donnees['pays'])) {
    $villes = getVillesByPays($pdo, intval($donnees['pays']));
}

// Nombre de voyageurs (par défaut 1)
$nb_voyageurs = intval($donnees['nb_voyageurs'] ?? 1);
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
                            <select id="continent" name="continent" required onchange="this.form.action='index.php?action=updateSelects'; this.form.submit();">
                                <option value="">-- Sélectionnez un continent --</option>
                                <?php foreach ($continents as $continent): ?>
                                    <option value="<?php echo h($continent['id_continent']); ?>" <?php echo (isset($donnees['continent']) && $donnees['continent'] == $continent['id_continent']) ? 'selected' : ''; ?>><?php echo h($continent['nom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="pays">Pays <span class="requis">*</span></label>
                            <select id="pays" name="pays" required <?php echo empty($pays) ? 'disabled' : ''; ?> onchange="this.form.action='index.php?action=updateSelects'; this.form.submit();">
                                <?php if (empty($pays)): ?>
                                    <option value="">-- Sélectionnez d'abord un continent --</option>
                                <?php else: ?>
                                    <option value="">-- Sélectionnez un pays --</option>
                                    <?php foreach ($pays as $p): ?>
                                        <option value="<?php echo h($p['id_pays']); ?>" <?php echo (isset($donnees['pays']) && $donnees['pays'] == $p['id_pays']) ? 'selected' : ''; ?>><?php echo h($p['nom']); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="ville">Ville <span class="requis">*</span></label>
                            <select id="ville" name="ville" required <?php echo empty($villes) ? 'disabled' : ''; ?>>
                                <?php if (empty($villes)): ?>
                                    <option value="">-- Sélectionnez d'abord un pays --</option>
                                <?php else: ?>
                                    <option value="">-- Sélectionnez une ville --</option>
                                    <?php foreach ($villes as $v): ?>
                                        <option value="<?php echo h($v['id_ville']); ?>" <?php echo (isset($donnees['ville']) && $donnees['ville'] == $v['id_ville']) ? 'selected' : ''; ?>><?php echo h($v['nom']); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
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
                            <input type="date" id="date_depart" name="date_depart" required 
                                   min="<?php echo DATE_DEPART_MIN; ?>" max="<?php echo DATE_DEPART_MAX; ?>"
                                   value="<?php echo h($donnees['date_depart'] ?? ''); ?>"
                                   onchange="this.form.action='index.php?action=updateSelects'; this.form.submit();">
                            <small>Entre le <?php echo date('d/m/Y', strtotime(DATE_DEPART_MIN)); ?> et le <?php echo date('d/m/Y', strtotime(DATE_DEPART_MAX)); ?></small>
                        </div>

                        <div class="form-group">
                            <label for="date_retour">Date de Retour <span class="requis">*</span></label>
                            <input type="date" id="date_retour" name="date_retour" required 
                                   min="<?php echo isset($donnees['date_depart']) ? $donnees['date_depart'] : DATE_DEPART_MIN; ?>"
                                   value="<?php echo h($donnees['date_retour'] ?? ''); ?>">
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
                            <input type="number" id="nb_voyageurs" name="nb_voyageurs" required min="1" max="20" 
                                   value="<?php echo $nb_voyageurs; ?>"
                                   onchange="this.form.action='index.php?action=updateSelects'; this.form.submit();">
                            <small>Entre 1 et 20 voyageurs</small>
                        </div>

                        <?php for ($i = 0; $i < $nb_voyageurs; $i++): ?>
                            <div class="voyageur-group">
                                <h4>Voyageur <?php echo $i + 1; ?></h4>
                                <div class="form-group">
                                    <label>Âge <span class="requis">*</span></label>
                                    <input type="number" name="ages[]" required min="0" max="120" 
                                           placeholder="Âge du voyageur"
                                           value="<?php echo h($donnees['ages'][$i] ?? ''); ?>">
                                    <small>Âge du voyageur</small>
                                </div>
                                <div class="form-group">
                                    <label>Poids des bagages (kg) <span class="requis">*</span></label>
                                    <input type="number" name="poids_bagages[]" required min="0" max="100" step="0.1" 
                                           placeholder="Poids en kg"
                                           value="<?php echo h($donnees['poids_bagages'][$i] ?? ''); ?>">
                                    <small>25 kg inclus par voyageur</small>
                                </div>
                            </div>
                        <?php endfor; ?>
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
                                    <option value="<?php echo h($entree); ?>" <?php echo (isset($donnees['entree']) && $donnees['entree'] == $entree) ? 'selected' : ''; ?>><?php echo h($entree); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="plat">Plat <span class="requis">*</span></label>
                            <select id="plat" name="plat" required>
                                <?php foreach ($options_menu['plats'] as $plat): ?>
                                    <option value="<?php echo h($plat); ?>" <?php echo (isset($donnees['plat']) && $donnees['plat'] == $plat) ? 'selected' : ''; ?>><?php echo h($plat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="dessert">Dessert <span class="requis">*</span></label>
                            <select id="dessert" name="dessert" required>
                                <?php foreach ($options_menu['desserts'] as $dessert): ?>
                                    <option value="<?php echo h($dessert); ?>" <?php echo (isset($donnees['dessert']) && $donnees['dessert'] == $dessert) ? 'selected' : ''; ?>><?php echo h($dessert); ?></option>
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
                                <input type="number" name="boissons[eau]" min="0" value="<?php echo h($donnees['boissons']['eau'] ?? 0); ?>" class="boisson-input">
                            </label>
                        </div>

                        <div class="form-group">
                            <label>Cannette Bière - <?php echo formaterPrix($prix_boissons['biere']); ?>
                                <input type="number" name="boissons[biere]" min="0" value="<?php echo h($donnees['boissons']['biere'] ?? 0); ?>" class="boisson-input">
                            </label>
                        </div>

                        <div class="form-group">
                            <label>1/4 l Vin - <?php echo formaterPrix($prix_boissons['vin']); ?>
                                <input type="number" name="boissons[vin]" min="0" value="<?php echo h($donnees['boissons']['vin'] ?? 0); ?>" class="boisson-input">
                            </label>
                        </div>

                        <div class="form-group">
                            <label>Cannette Coca - <?php echo formaterPrix($prix_boissons['coca']); ?>
                                <input type="number" name="boissons[coca]" min="0" value="<?php echo h($donnees['boissons']['coca'] ?? 0); ?>" class="boisson-input">
                            </label>
                        </div>

                        <div class="form-group">
                            <label>Jus d'Orange - <?php echo formaterPrix($prix_boissons['jus_orange']); ?>
                                <input type="number" name="boissons[jus_orange]" min="0" value="<?php echo h($donnees['boissons']['jus_orange'] ?? 0); ?>" class="boisson-input">
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
                                <input type="radio" name="moyen_paiement" value="Carte Bancaire" required 
                                       <?php echo (isset($donnees['moyen_paiement']) && $donnees['moyen_paiement'] == 'Carte Bancaire') ? 'checked' : ''; ?>>
                                <div class="payment-card">
                                    <i class="fas fa-credit-card"></i>
                                    <div>
                                        <strong>Carte Bancaire Internationale</strong>
                                        <span>Frais : <?php echo formaterPrix(FRAIS_CARTE_BANCAIRE); ?></span>
                                    </div>
                                </div>
                            </label>

                            <label class="payment-option">
                                <input type="radio" name="moyen_paiement" value="Virement sur compte" required
                                       <?php echo (isset($donnees['moyen_paiement']) && $donnees['moyen_paiement'] == 'Virement sur compte') ? 'checked' : ''; ?>>
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

</body>
</html>
