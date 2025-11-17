<?php

// Démarrage de la session pour garder les données entre les pages
session_start();

// ============================================
// CONFIGURATION ET CONNEXION BASE DE DONNÉES
// ============================================

// Connexion à la base de données MySQL avec PDO
// J'utilise PDO car c'est ce qu'on a vu en cours
try {
    $pdo = new PDO('mysql:host=127.0.0.1:3306;dbname=compagnieaerienne', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// Nom de l'agence (utilisé dans les titres et le footer)
define('NOM_AGENCE', 'Thys\'Air');

// Constantes pour les prix et paramètres
// J'ai mis ça en constantes pour pouvoir les modifier facilement si besoin
define('PRIX_KG_SUPPLEMENTAIRE', 20.00);      // Prix par kg de bagage supplémentaire
define('FRAIS_CARTE_BANCAIRE', 30.00);        // Frais pour paiement par carte
define('FRAIS_VIREMENT', 20.00);              // Frais pour virement bancaire
define('TVA', 0.20);                          // Taux de TVA (20%)
define('REDUCTION_3_MOIS', 0.10);             // Réduction de 10% si réservation 3 mois avant
define('POIDS_BAGAGE_INCLUS', 25);            // Poids de bagage inclus par voyageur (en kg)

// Dates limites pour les réservations
define('DATE_DEPART_MIN', '2025-12-01');
define('DATE_DEPART_MAX', '2026-06-30');

// Prix des différentes boissons disponibles
$prix_boissons = [
    'eau' => 1.50,
    'biere' => 1.20,
    'vin' => 1.70,
    'coca' => 1.80,
    'jus_orange' => 2.00
];

// Options de menu disponibles
$options_menu = [
    'entrees' => ['Salade verte', 'Soupe du jour', 'Terrine de légumes', 'Salade de fruits de mer'],
    'plats' => ['Poulet rôti', 'Saumon grillé', 'Pâtes carbonara', 'Steak haché'],
    'desserts' => ['Tarte aux pommes', 'Mousse au chocolat', 'Salade de fruits', 'Glace vanille']
];

// ============================================
// FONCTIONS UTILITAIRES
// ============================================

/**
 * Récupère tous les continents depuis la base de données
 * @param PDO $pdo Connexion à la base de données
 * @return array Liste des continents
 */
function getContinents($pdo) {
    $stmt = $pdo->query("SELECT * FROM continent ORDER BY nom");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère tous les pays d'un continent donné
 * @param PDO $pdo Connexion à la base de données
 * @param int $id_continent ID du continent
 * @return array Liste des pays
 */
function getPaysByContinent($pdo, $id_continent) {
    $stmt = $pdo->prepare("SELECT * FROM pays WHERE id_continent = ? ORDER BY nom");
    $stmt->execute([$id_continent]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère toutes les villes d'un pays donné
 * @param PDO $pdo Connexion à la base de données
 * @param int $id_pays ID du pays
 * @return array Liste des villes
 */
function getVillesByPays($pdo, $id_pays) {
    $stmt = $pdo->prepare("SELECT * FROM ville WHERE id_pays = ? ORDER BY nom");
    $stmt->execute([$id_pays]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les informations du vol pour une ville donnée
 * @param PDO $pdo Connexion à la base de données
 * @param int $id_ville ID de la ville
 * @return array|false Informations du vol ou false si pas trouvé
 */
function getVolByVille($pdo, $id_ville) {
    $stmt = $pdo->prepare("SELECT * FROM vol WHERE id_ville_arrivee = ? LIMIT 1");
    $stmt->execute([$id_ville]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère toutes les infos d'une destination (ville, pays, continent, prix)
 * J'utilise un JOIN pour tout récupérer en une seule requête, c'est plus efficace
 * @param PDO $pdo Connexion à la base de données
 * @param int $id_ville ID de la ville
 * @return array|false Informations complètes de la destination
 */
function getDestinationInfo($pdo, $id_ville) {
    $stmt = $pdo->prepare("
        SELECT v.id_ville, v.nom as nom_ville, 
               p.id_pays, p.nom as nom_pays,
               c.id_continent, c.nom as nom_continent,
               vol.prix_bebe, vol.prix_enfant, vol.prix_adulte
        FROM ville v
        JOIN pays p ON v.id_pays = p.id_pays
        JOIN continent c ON p.id_continent = c.id_continent
        LEFT JOIN vol ON vol.id_ville_arrivee = v.id_ville
        WHERE v.id_ville = ?
    ");
    $stmt->execute([$id_ville]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Détermine la catégorie d'âge d'un voyageur
 * - Bébé : moins de 2 ans
 * - Enfant : entre 2 et 11 ans
 * - Adulte : 12 ans et plus
 * @param int $age Âge du voyageur
 * @return string 'bebe', 'enfant' ou 'adulte'
 */
function getCategorieAge($age) {
    if ($age < 2) {
        return 'bebe';
    } elseif ($age >= 2 && $age <= 11) {
        return 'enfant';
    } else {
        return 'adulte';
    }
}

/**
 * Calcule le prix d'un voyageur selon son âge
 * @param array $vol Informations du vol (contient les prix)
 * @param int $age Âge du voyageur
 * @return float Prix du voyageur
 */
function calculerPrixVoyageur($vol, $age) {
    $categorie = getCategorieAge($age);
    
    switch ($categorie) {
        case 'bebe':
            return floatval($vol['prix_bebe']);
        case 'enfant':
            return floatval($vol['prix_enfant']);
        case 'adulte':
            return floatval($vol['prix_adulte']);
        default:
            return 0;
    }
}

/**
 * Calcule la réduction si la réservation est faite 3 mois ou plus avant le départ
 * @param string $date_depart Date de départ au format YYYY-MM-DD
 * @param float $montant Montant sur lequel appliquer la réduction
 * @return float Montant de la réduction
 */
function calculerReduction($date_depart, $montant) {
    $date_depart_obj = new DateTime($date_depart);
    $date_aujourdhui = new DateTime();
    $interval = $date_aujourdhui->diff($date_depart_obj);
    
    // Calcul du nombre de mois entre aujourd'hui et le départ
    $mois_avant = ($interval->y * 12) + $interval->m;
    
    // Si 3 mois ou plus, on applique la réduction
    if ($mois_avant >= 3) {
        return $montant * REDUCTION_3_MOIS;
    }
    
    return 0;
}

/**
 * Calcule le supplément à payer pour les bagages en trop
 * Chaque voyageur a 25kg inclus, au-delà c'est payant
 * @param float $poids_bagage Poids total des bagages
 * @param int $nb_voyageurs Nombre de voyageurs
 * @return float Montant du supplément
 */
function calculerSupplementBagage($poids_bagage, $nb_voyageurs) {
    $poids_inclus = POIDS_BAGAGE_INCLUS * $nb_voyageurs;
    $poids_supplementaire = $poids_bagage - $poids_inclus;
    
    if ($poids_supplementaire > 0) {
        return $poids_supplementaire * PRIX_KG_SUPPLEMENTAIRE;
    }
    
    return 0;
}

/**
 * Calcule le total des boissons commandées
 * @param array $boissons Tableau associatif [type_boisson => quantité]
 * @param array $prix_boissons Tableau des prix par type de boisson
 * @return float Total des boissons
 */
function calculerTotalBoissons($boissons, $prix_boissons) {
    $total = 0;
    
    foreach ($boissons as $type_boisson => $quantite) {
        // Vérifier que le type de boisson existe et que la quantité est positive
        if (isset($prix_boissons[$type_boisson]) && $quantite > 0) {
            $total += $prix_boissons[$type_boisson] * intval($quantite);
        }
    }
    
    return $total;
}

/**
 * Calcule les frais selon le moyen de paiement choisi
 * @param string $moyen_paiement Moyen de paiement sélectionné
 * @return float Montant des frais
 */
function calculerFraisPaiement($moyen_paiement) {
    if ($moyen_paiement === 'Carte Bancaire') {
        return FRAIS_CARTE_BANCAIRE;
    } elseif ($moyen_paiement === 'Virement sur compte') {
        return FRAIS_VIREMENT;
    }
    
    return 0;
}

/**
 * Calcule le TTC (Toutes Taxes Comprises) à partir du HT
 * @param float $total_ht Montant hors taxes
 * @return array Tableau avec HT, TVA et TTC
 */
function calculerTTC($total_ht) {
    $tva = $total_ht * TVA;
    $ttc = $total_ht + $tva;
    
    return [
        'ht' => $total_ht,
        'tva' => $tva,
        'ttc' => $ttc
    ];
}

// ============================================
// FONCTIONS DE VALIDATION
// ============================================

/**
 * Valide un nom (alphanumérique, max 30 caractères)
 * @param string $nom Nom à valider
 * @return bool True si valide
 */
function validerNom($nom) {
    return preg_match('/^[a-zA-Z0-9\s\'-]{1,30}$/u', $nom);
}

/**
 * Valide un email (doit contenir @ et être un email valide)
 * @param string $email Email à valider
 * @return bool True si valide
 */
function validerEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strpos($email, '@') !== false;
}

/**
 * Valide un numéro de téléphone (uniquement des chiffres)
 * @param string $telephone Téléphone à valider
 * @return bool True si valide
 */
function validerTelephone($telephone) {
    return preg_match('/^\d+$/', $telephone);
}

/**
 * Valide une adresse (max 60 caractères, non vide)
 * @param string $adresse Adresse à valider
 * @return bool True si valide
 */
function validerAdresse($adresse) {
    return strlen($adresse) > 0 && strlen($adresse) <= 60;
}

/**
 * Valide la date de départ
 * Doit être dans la période autorisée et après aujourd'hui
 * @param string $date_depart Date au format YYYY-MM-DD
 * @return array ['valide' => bool, 'message' => string]
 */
function validerDateDepart($date_depart) {
    $date_depart_obj = new DateTime($date_depart);
    $date_aujourdhui = new DateTime();
    $date_min = new DateTime(DATE_DEPART_MIN);
    $date_max = new DateTime(DATE_DEPART_MAX);
    
    // Vérifier que la date est après la date minimum
    if ($date_depart_obj < $date_min) {
        return [
            'valide' => false,
            'message' => 'La date de départ doit être après le ' . date('d/m/Y', $date_min->getTimestamp())
        ];
    }
    
    // Vérifier que la date est avant la date maximum
    if ($date_depart_obj > $date_max) {
        return [
            'valide' => false,
            'message' => 'La date de départ doit être avant le ' . date('d/m/Y', $date_max->getTimestamp())
        ];
    }
    
    // Vérifier que la date est après aujourd'hui
    if ($date_depart_obj <= $date_aujourdhui) {
        return [
            'valide' => false,
            'message' => 'La date de départ doit être postérieure à aujourd\'hui'
        ];
    }
    
    return ['valide' => true, 'message' => ''];
}

/**
 * Valide la date de retour (doit être après la date de départ)
 * @param string $date_depart Date de départ
 * @param string $date_retour Date de retour
 * @return array ['valide' => bool, 'message' => string]
 */
function validerDateRetour($date_depart, $date_retour) {
    $date_depart_obj = new DateTime($date_depart);
    $date_retour_obj = new DateTime($date_retour);
    
    if ($date_retour_obj <= $date_depart_obj) {
        return [
            'valide' => false,
            'message' => 'La date de retour doit être postérieure à la date de départ'
        ];
    }
    
    return ['valide' => true, 'message' => ''];
}

/**
 * Valide le nombre de voyageurs (entre 1 et 20)
 * @param mixed $nb_voyageurs Nombre de voyageurs
 * @return bool True si valide
 */
function validerNombreVoyageurs($nb_voyageurs) {
    return is_numeric($nb_voyageurs) && $nb_voyageurs >= 1 && $nb_voyageurs <= 20;
}

/**
 * Valide le poids des bagages
 * @param float $poids Poids à valider
 * @param int $nb_voyageurs Nombre de voyageurs (pour calculer le max)
 * @return bool True si valide
 */
function validerPoidsBagage($poids, $nb_voyageurs = 1) {
    $poids_max = 100 * $nb_voyageurs; // 100kg max par voyageur
    return is_numeric($poids) && $poids >= 0 && $poids <= $poids_max;
}

// ============================================
// FONCTIONS D'AFFICHAGE
// ============================================

/**
 * Formate un prix en euros avec 2 décimales
 * @param float $prix Prix à formater
 * @return string Prix formaté (ex: "123,45 €")
 */
function formaterPrix($prix) {
    return number_format($prix, 2, ',', ' ') . ' €';
}

/**
 * Échappe les caractères HTML pour éviter les attaques XSS
 * C'est important pour la sécurité, on échappe toutes les données avant de les afficher
 * @param string $data Donnée à échapper
 * @return string Donnée échappée
 */
function h($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// ============================================
// GESTION DES ACTIONS
// ============================================

// Récupère l'action demandée (via GET)
$action = $_GET['action'] ?? '';

// Action pour mettre à jour les menus déroulants
// Maintenant, continent, pays et date de départ ne rechargent plus grâce au JavaScript
// On garde cette action uniquement pour le nombre de voyageurs (qui doit recharger pour ajouter/supprimer les champs)
if ($action === 'updateSelects') {
    // On sauvegarde toutes les données du formulaire dans la session
    $_SESSION['form_data'] = $_POST;
    
    // On détermine vers quelle section rediriger pour que l'utilisateur reste au bon endroit
    $anchor = '#section-voyageurs'; // Par défaut, on revient à la section voyageurs
    
    // Redirection vers le formulaire avec l'ancre appropriée
    header('Location: index.php' . $anchor);
    exit;
}

// Traitement du formulaire complet quand l'utilisateur clique sur "Valider"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['action'])) {
    $erreurs = [];
    
    // ============================================
    // VALIDATION DES DONNÉES
    // ============================================
    
    // Récupération et nettoyage des données du formulaire
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $mail = trim($_POST['mail'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    
    // Validation des informations personnelles
    if (empty($nom) || !validerNom($nom)) {
        $erreurs[] = "Le nom est invalide";
    }
    if (empty($prenom) || !validerNom($prenom)) {
        $erreurs[] = "Le prénom est invalide";
    }
    if (empty($mail) || !validerEmail($mail)) {
        $erreurs[] = "L'email est invalide";
    }
    if (empty($telephone) || !validerTelephone($telephone)) {
        $erreurs[] = "Le téléphone est invalide";
    }
    if (empty($adresse) || !validerAdresse($adresse)) {
        $erreurs[] = "L'adresse est invalide";
    }
    
    // Validation de la destination
    $id_ville = intval($_POST['ville'] ?? 0);
    if ($id_ville <= 0) {
        $erreurs[] = "Veuillez sélectionner une destination";
    }
    
    // Validation des dates
    $date_depart = $_POST['date_depart'] ?? '';
    $date_retour = $_POST['date_retour'] ?? '';
    
    if (empty($date_depart)) {
        $erreurs[] = "La date de départ est requise";
    } else {
        $validation_depart = validerDateDepart($date_depart);
        if (!$validation_depart['valide']) {
            $erreurs[] = $validation_depart['message'];
        }
    }
    
    if (empty($date_retour)) {
        $erreurs[] = "La date de retour est requise";
    } else {
        $validation_retour = validerDateRetour($date_depart, $date_retour);
        if (!$validation_retour['valide']) {
            $erreurs[] = $validation_retour['message'];
        }
    }
    
    // Validation du nombre de voyageurs
    $nb_voyageurs = intval($_POST['nb_voyageurs'] ?? 0);
    if (!validerNombreVoyageurs($nb_voyageurs)) {
        $erreurs[] = "Le nombre de voyageurs doit être entre 1 et 20";
    }
    
    // Validation des âges et poids de bagages
    $ages = $_POST['ages'] ?? [];
    $poids_bagages = $_POST['poids_bagages'] ?? [];
    
    // Vérifier qu'on a bien le bon nombre d'âges et de poids
    if (count($ages) !== $nb_voyageurs) {
        $erreurs[] = "Le nombre d'âges ne correspond pas au nombre de voyageurs";
    }
    if (count($poids_bagages) !== $nb_voyageurs) {
        $erreurs[] = "Le nombre de poids de bagages ne correspond pas au nombre de voyageurs";
    }
    
    // Valider chaque poids de bagage
    $poids_total = 0;
    foreach ($poids_bagages as $index => $poids) {
        $poids_float = floatval($poids);
        if (!validerPoidsBagage($poids_float, $nb_voyageurs)) {
            $erreurs[] = "Le poids des bagages du voyageur " . ($index + 1) . " est invalide";
        }
        $poids_total += $poids_float;
    }
    
    // Validation du menu
    $entree = $_POST['entree'] ?? '';
    $plat = $_POST['plat'] ?? '';
    $dessert = $_POST['dessert'] ?? '';
    if (empty($entree) || empty($plat) || empty($dessert)) {
        $erreurs[] = "Veuillez sélectionner un menu complet";
    }
    
    // Validation du moyen de paiement
    $moyen_paiement = $_POST['moyen_paiement'] ?? '';
    if (empty($moyen_paiement) || !in_array($moyen_paiement, ['Carte Bancaire', 'Virement sur compte'])) {
        $erreurs[] = "Veuillez sélectionner un mode de paiement";
    }
    
    // Si il y a des erreurs, on les sauvegarde et on redirige vers le formulaire
    if (!empty($erreurs)) {
        $_SESSION['erreurs'] = $erreurs;
        $_SESSION['donnees_formulaire'] = $_POST; // On garde les données pour les réafficher
        header('Location: index.php');
        exit;
    }
    
    // ============================================
    // RÉCUPÉRATION DES INFORMATIONS DE DESTINATION
    // ============================================
    
    // Récupérer les infos complètes de la destination
    $destination = getDestinationInfo($pdo, $id_ville);
    if (!$destination) {
        $_SESSION['erreurs'] = ["Destination introuvable"];
        header('Location: index.php');
        exit;
    }
    
    // Récupérer les infos du vol
    $vol = getVolByVille($pdo, $id_ville);
    if (!$vol) {
        $_SESSION['erreurs'] = ["Aucun vol disponible pour cette destination"];
        header('Location: index.php');
        exit;
    }
    
    // ============================================
    // CALCUL DES PRIX
    // ============================================
    
    // Calculer le prix pour chaque voyageur selon son âge
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
    
    // Calculer la réduction si réservation 3 mois avant
    $reduction = calculerReduction($date_depart, $total_voyage);
    $total_voyage_apres_reduction = $total_voyage - $reduction;
    
    // Calculer le supplément bagage
    $supplement_bagage = calculerSupplementBagage($poids_total, $nb_voyageurs);
    
    // Calculer le total des boissons
    $boissons = $_POST['boissons'] ?? [];
    $total_boissons = calculerTotalBoissons($boissons, $prix_boissons);
    
    // Calculer les frais de paiement
    $frais_paiement = calculerFraisPaiement($moyen_paiement);
    
    // Calculer le total HT
    $total_ht = $total_voyage_apres_reduction + $supplement_bagage + $total_boissons + $frais_paiement;
    
    // Calculer le TTC
    $calcul_ttc = calculerTTC($total_ht);
    
    // ============================================
    // SAUVEGARDE DANS LA SESSION
    // ============================================
    
    // On stocke tout dans la session pour pouvoir l'afficher dans le récapitulatif
    $_SESSION['reservation'] = [
        'client' => [
            'nom' => $nom,
            'prenom' => $prenom,
            'mail' => $mail,
            'telephone' => $telephone,
            'adresse' => $adresse
        ],
        'destination' => $destination,
        'vol' => $vol,
        'dates' => [
            'depart' => $date_depart,
            'retour' => $date_retour
        ],
        'voyageurs' => $details_voyageurs,
        'menu' => [
            'entree' => $entree,
            'plat' => $plat,
            'dessert' => $dessert
        ],
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
    
    // Redirection vers la page de récapitulatif
    header('Location: index.php?action=recap');
    exit;
}

// ============================================
// AFFICHAGE DU RÉCAPITULATIF
// ============================================

if ($action === 'recap') {
    // Vérifier qu'il y a bien une réservation en session
    if (!isset($_SESSION['reservation'])) {
        header('Location: index.php');
        exit;
    }
    
    // Récupérer toutes les données de la réservation
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
    
    // ============================================
    // ENREGISTREMENT EN BASE DE DONNÉES
    // ============================================
    
    // Enregistrer le client dans la table client
    try {
        $stmt = $pdo->prepare("INSERT INTO client (nom, prenom, mail, telephone, adresse) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $client['nom'],
            $client['prenom'],
            $client['mail'],
            $client['telephone'],
            $client['adresse']
        ]);
        $id_client = $pdo->lastInsertId();
    } catch (PDOException $e) {
        die("Erreur lors de l'enregistrement du client : " . $e->getMessage());
    }
    
    // Calculer le poids total des bagages
    $poids_total_bagages = 0;
    foreach ($voyageurs as $voyageur) {
        $poids_total_bagages += $voyageur['poids_bagage'];
    }
    
    // Compter le nombre de bébés, enfants et adultes
    $nb_bebe = 0;
    $nb_enfant = 0;
    $nb_adulte = 0;
    
    foreach ($voyageurs as $voyageur) {
        switch ($voyageur['categorie']) {
            case 'bebe':
                $nb_bebe++;
                break;
            case 'enfant':
                $nb_enfant++;
                break;
            case 'adulte':
                $nb_adulte++;
                break;
        }
    }
    
    // Enregistrer le voyage dans la table voyage
    try {
        $stmt = $pdo->prepare("
            INSERT INTO voyage (id_client, id_vol, date_depart, date_retour, nb_adulte, nb_enfant, nb_bebe, poids_bagage, moyen_paiement) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id_client,
            $vol['id_vol'],
            $dates['depart'],
            $dates['retour'],
            $nb_adulte,
            $nb_enfant,
            $nb_bebe,
            intval($poids_total_bagages),
            $moyen_paiement
        ]);
        $id_voyage = $pdo->lastInsertId();
    } catch (PDOException $e) {
        die("Erreur lors de l'enregistrement du voyage : " . $e->getMessage());
    }
    
    // Affichage du récapitulatif
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
        .recap-header { text-align: center; margin-bottom: 40px; padding-bottom: 30px; border-bottom: 3px solid #FFB800; }
        .recap-header h2 { color: #003580; font-size: 2.5rem; margin-bottom: 10px; }
        .recap-section { background: #f9fafb; padding: 25px; margin-bottom: 25px; border-radius: var(--radius-lg); border-left: 4px solid #003580; }
        .recap-section h3 { color: #003580; margin-bottom: 20px; font-size: 1.3rem; display: flex; align-items: center; gap: 10px; }
        .recap-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
        .recap-row:last-child { border-bottom: none; }
        .recap-label { font-weight: 600; color: var(--text-dark); }
        .recap-value { color: var(--text-medium); }
        .recap-total { background: #003580; color: white; padding: 30px; border-radius: var(--radius-xl); margin-top: 30px; border-top: 4px solid #FFB800; }
        .recap-total h3 { color: white; margin-bottom: 20px; font-size: 1.5rem; }
        .recap-total .recap-row { border-bottom-color: rgba(255, 255, 255, 0.2); color: white; }
        .recap-total .recap-label, .recap-total .recap-value { color: white; }
        .grand-total { font-size: 1.8rem; font-weight: 800; margin-top: 15px; padding-top: 15px; border-top: 2px solid rgba(255, 255, 255, 0.3); }
        .btn-actions { text-align: center; margin-top: 40px; display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; }
        .voyageur-item { background: white; padding: 15px; margin-bottom: 10px; border-radius: var(--radius-md); border-left: 3px solid #003580; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-decoration"></div>
            <div class="header-content">
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
                            (<?php 
                                if ($voyageur['categorie'] === 'bebe') {
                                    echo 'Bébé';
                                } elseif ($voyageur['categorie'] === 'enfant') {
                                    echo 'Enfant';
                                } else {
                                    echo 'Adulte';
                                }
                            ?>) - 
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
                    $noms_boissons = [
                        'eau' => 'Eau (1/2)',
                        'biere' => 'Cannette Bière',
                        'vin' => '1/4 l Vin',
                        'coca' => 'Cannette Coca',
                        'jus_orange' => 'Jus d\'Orange'
                    ];
                    foreach ($boissons as $boisson => $quantite): 
                        if ($quantite > 0):
                    ?>
                        <div class="recap-row">
                            <span class="recap-label"><?php echo h($noms_boissons[$boisson] ?? $boisson); ?> (x<?php echo $quantite; ?>) :</span>
                            <span class="recap-value"><?php echo formaterPrix($prix_boissons[$boisson] * $quantite); ?></span>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
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
// AFFICHAGE DU FORMULAIRE DE RÉSERVATION
// ============================================

// Récupérer tous les continents pour le menu déroulant
$continents = getContinents($pdo);

// Récupérer les données du formulaire depuis la session
// Soit après une erreur de validation, soit après une mise à jour des sélections
$donnees = $_SESSION['donnees_formulaire'] ?? $_SESSION['form_data'] ?? [];
unset($_SESSION['donnees_formulaire']); // On supprime après récupération

// PRÉCHARGER TOUTES LES DONNÉES pour éviter les rechargements
// On charge tous les pays et toutes les villes une seule fois au début
// Ensuite, un petit script JavaScript les filtrera localement sans recharger la page
$tous_les_pays = [];
$tous_les_villes = [];

foreach ($continents as $continent) {
    $pays_continent = getPaysByContinent($pdo, $continent['id_continent']);
    foreach ($pays_continent as $p) {
        // S'assurer que l'ID du continent est présent dans le tableau pays
        if (!isset($p['id_continent'])) {
            $p['id_continent'] = $continent['id_continent'];
        }
        $tous_les_pays[] = $p;
        
        // Charger toutes les villes de ce pays
        $villes_pays = getVillesByPays($pdo, $p['id_pays']);
        foreach ($villes_pays as $v) {
            // Ajouter l'ID du pays parent pour pouvoir filtrer ensuite
            $v['id_pays_parent'] = $p['id_pays'];
            $tous_les_villes[] = $v;
        }
    }
}

// Pour l'affichage initial, on récupère les pays et villes sélectionnés si ils existent
$pays = [];
if (!empty($donnees['continent'])) {
    $pays = getPaysByContinent($pdo, intval($donnees['continent']));
}

$villes = [];
if (!empty($donnees['pays'])) {
    $villes = getVillesByPays($pdo, intval($donnees['pays']));
}

// Nombre de voyageurs (par défaut 1)
$nb_voyageurs = intval($donnees['nb_voyageurs'] ?? 1);
if ($nb_voyageurs < 1) {
    $nb_voyageurs = 1;
}
if ($nb_voyageurs > 20) {
    $nb_voyageurs = 20;
}

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
                    <!-- Section : Informations personnelles -->
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

                    <!-- Section : Destination -->
                    <div class="section-form" id="section-destination">
                        <div class="section-header">
                            <i class="fas fa-map-marked-alt"></i>
                            <h3>Destination</h3>
                        </div>

                        <div class="form-group">
                            <label for="continent">Continent <span class="requis">*</span></label>
                            <select id="continent" name="continent" required onchange="filtrerPays();">
                                <option value="">-- Sélectionnez un continent --</option>
                                <?php foreach ($continents as $continent): ?>
                                    <option value="<?php echo h($continent['id_continent']); ?>" <?php echo (isset($donnees['continent']) && $donnees['continent'] == $continent['id_continent']) ? 'selected' : ''; ?>><?php echo h($continent['nom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="pays">Pays <span class="requis">*</span></label>
                            <select id="pays" name="pays" required onchange="filtrerVilles();">
                                <option value="">-- Sélectionnez d'abord un continent --</option>
                                <?php foreach ($tous_les_pays as $p): ?>
                                    <option value="<?php echo h($p['id_pays']); ?>" 
                                            data-continent="<?php echo h($p['id_continent']); ?>" 
                                            style="display:none;"
                                            <?php echo (isset($donnees['pays']) && $donnees['pays'] == $p['id_pays']) ? 'selected' : ''; ?>>
                                        <?php echo h($p['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="ville">Ville <span class="requis">*</span></label>
                            <select id="ville" name="ville" required>
                                <option value="">-- Sélectionnez d'abord un pays --</option>
                                <?php foreach ($tous_les_villes as $v): ?>
                                    <option value="<?php echo h($v['id_ville']); ?>" 
                                            data-pays="<?php echo h($v['id_pays_parent'] ?? $v['id_pays']); ?>" 
                                            style="display:none;"
                                            <?php echo (isset($donnees['ville']) && $donnees['ville'] == $v['id_ville']) ? 'selected' : ''; ?>>
                                        <?php echo h($v['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <script>
                        // Script minimal pour filtrer les pays et villes sans recharger la page
                        // Toutes les données sont déjà chargées en PHP, on filtre juste localement
                        
                        function filtrerPays() {
                            var continentSelect = document.getElementById('continent');
                            var paysSelect = document.getElementById('pays');
                            var villeSelect = document.getElementById('ville');
                            var idContinent = continentSelect.value;
                            
                            // Réinitialiser les sélections dépendantes
                            if (paysSelect) paysSelect.value = '';
                            if (villeSelect) villeSelect.value = '';
                            
                            // Filtrer les pays selon le continent
                            var paysOptions = document.querySelectorAll('#pays option[data-continent]');
                            var hasVisible = false;
                            
                            paysOptions.forEach(function(option) {
                                if (idContinent && option.getAttribute('data-continent') == idContinent) {
                                    option.style.display = '';
                                    hasVisible = true;
                                } else {
                                    option.style.display = 'none';
                                }
                            });
                            
                            // Activer/désactiver le select pays
                            if (paysSelect) {
                                paysSelect.disabled = !idContinent || !hasVisible;
                            }
                            
                            // Réinitialiser les villes
                            filtrerVilles();
                        }
                        
                        function filtrerVilles() {
                            var paysSelect = document.getElementById('pays');
                            var villeSelect = document.getElementById('ville');
                            var idPays = paysSelect ? paysSelect.value : '';
                            
                            // Réinitialiser la sélection de ville
                            if (villeSelect) villeSelect.value = '';
                            
                            // Filtrer les villes selon le pays
                            var villeOptions = document.querySelectorAll('#ville option[data-pays]');
                            var hasVisible = false;
                            
                            villeOptions.forEach(function(option) {
                                if (idPays && option.getAttribute('data-pays') == idPays) {
                                    option.style.display = '';
                                    hasVisible = true;
                                } else {
                                    option.style.display = 'none';
                                }
                            });
                            
                            // Activer/désactiver le select ville
                            if (villeSelect) {
                                villeSelect.disabled = !idPays || !hasVisible;
                            }
                        }
                        
                        // Initialiser au chargement de la page
                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', function() {
                                filtrerPays();
                                filtrerVilles();
                            });
                        } else {
                            filtrerPays();
                            filtrerVilles();
                        }
                        </script>
                    </div>

                    <!-- Section : Dates de voyage -->
                    <div class="section-form" id="section-dates">
                        <div class="section-header">
                            <i class="fas fa-calendar"></i>
                            <h3>Dates de Voyage</h3>
                        </div>

                        <div class="form-group">
                            <label for="date_depart">Date de Départ <span class="requis">*</span></label>
                            <input type="date" id="date_depart" name="date_depart" required 
                                   min="<?php echo DATE_DEPART_MIN; ?>" max="<?php echo DATE_DEPART_MAX; ?>"
                                   value="<?php echo h($donnees['date_depart'] ?? ''); ?>"
                                   onchange="mettreAJourDateRetour();">
                            <small>Entre le <?php echo date('d/m/Y', strtotime(DATE_DEPART_MIN)); ?> et le <?php echo date('d/m/Y', strtotime(DATE_DEPART_MAX)); ?></small>
                        </div>

                        <div class="form-group">
                            <label for="date_retour">Date de Retour <span class="requis">*</span></label>
                            <input type="date" id="date_retour" name="date_retour" required 
                                   min="<?php echo isset($donnees['date_depart']) && !empty($donnees['date_depart']) ? $donnees['date_depart'] : DATE_DEPART_MIN; ?>"
                                   value="<?php echo h($donnees['date_retour'] ?? ''); ?>">
                            <small>Doit être postérieure à la date de départ</small>
                        </div>
                        
                        <script>
                        // Fonction pour mettre à jour la date de retour minimum sans recharger
                        function mettreAJourDateRetour() {
                            var dateDepartInput = document.getElementById('date_depart');
                            var dateRetourInput = document.getElementById('date_retour');
                            
                            if (dateDepartInput && dateRetourInput) {
                                var dateDepart = dateDepartInput.value;
                                
                                if (dateDepart) {
                                    // Calculer la date minimum (lendemain de la date de départ)
                                    var dateMin = new Date(dateDepart);
                                    dateMin.setDate(dateMin.getDate() + 1);
                                    
                                    // Mettre à jour le min de la date de retour
                                    var dateMinStr = dateMin.toISOString().split('T')[0];
                                    dateRetourInput.min = dateMinStr;
                                    
                                    // Si la date de retour actuelle est invalide, la réinitialiser
                                    if (dateRetourInput.value && dateRetourInput.value <= dateDepart) {
                                        dateRetourInput.value = '';
                                    }
                                }
                            }
                        }
                        </script>
                    </div>

                    <!-- Section : Voyageurs -->
                    <div class="section-form" id="section-voyageurs">
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

                    <!-- Section : Menu -->
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

                    <!-- Section : Boissons -->
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

                    <!-- Section : Mode de paiement -->
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

                    <!-- Boutons d'action -->
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
