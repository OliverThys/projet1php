<?php
/**
 * Fonctions utilitaires pour l'agence de voyage
 * 
 * Ce fichier contient toutes les fonctions de calcul, validation et affichage
 */

require_once 'config.php';

/**
 * Détermine la catégorie d'âge selon l'âge du voyageur
 * 
 * @param int $age L'âge du voyageur
 * @return int Catégorie : 0 (<2 ans), 1 (2-11 ans), 2 (>=12 ans)
 */
function getCategorieAge($age) {
    if ($age < 2) {
        return 0;
    } elseif ($age >= 2 && $age <= 11) {
        return 1;
    } else {
        return 2;
    }
}

/**
 * Calcule le tarif de base pour un voyageur
 * 
 * @param string $pays Le pays de destination
 * @param string $ville La ville de destination
 * @param int $age L'âge du voyageur
 * @param int $aeroport Index de l'aéroport (0 ou 1)
 * @return float Le tarif de base HT
 */
function calculerTarifBase($pays, $ville, $age, $aeroport) {
    global $tarifs;
    
    $categorie = getCategorieAge($age);
    
    // Vérifier que les données existent
    if (!isset($tarifs[$pays][$ville][$categorie])) {
        return 0;
    }
    
    // Récupérer le tarif selon l'aéroport (0 = aéroport 1, 1 = aéroport 2)
    $tarif = $tarifs[$pays][$ville][$categorie][$aeroport];
    
    return floatval($tarif);
}

/**
 * Vérifie si le voyageur a droit à la réduction de 10%
 * (réservation au minimum 3 mois avant la date de départ)
 * 
 * @param string $dateDepart Date de départ au format Y-m-d
 * @param string $dateReservation Date de réservation au format Y-m-d (optionnel, utilise date système si non fourni)
 * @return bool True si réduction applicable
 */
function aDroitReduction($dateDepart, $dateReservation = null) {
    if ($dateReservation === null) {
        $dateReservation = date('Y-m-d');
    }
    
    $timestampDepart = strtotime($dateDepart);
    $timestampReservation = strtotime($dateReservation);
    
    // Calculer la différence en mois
    $diff = $timestampDepart - $timestampReservation;
    $diffMois = floor($diff / (30 * 24 * 3600)); // Approximation 30 jours par mois
    
    return $diffMois >= MOIS_REDUCTION;
}

/**
 * Calcule le montant de la réduction
 * 
 * @param float $montant Montant HT
 * @param bool $reductionApplicable Si la réduction est applicable
 * @return float Montant de la réduction
 */
function calculerReduction($montant, $reductionApplicable) {
    if ($reductionApplicable) {
        return $montant * POURCENTAGE_REDUCTION;
    }
    return 0;
}

/**
 * Calcule le prix des bagages supplémentaires
 * 
 * @param float $poidsTotal Poids total des bagages du voyageur en kg
 * @return float Prix des bagages supplémentaires HT
 */
function calculerPrixBagages($poidsTotal) {
    if ($poidsTotal <= POIDS_BAGAGE_INCLUS) {
        return 0;
    }
    
    $poidsSupplementaire = $poidsTotal - POIDS_BAGAGE_INCLUS;
    return $poidsSupplementaire * PRIX_KG_SUPPLEMENTAIRE;
}

/**
 * Calcule le prix des boissons
 * 
 * @param array $boissonsSelectionnees Tableau des boissons sélectionnées avec quantités
 * @return float Prix total des boissons HT
 */
function calculerPrixBoissons($boissonsSelectionnees) {
    global $boissons;
    $total = 0;
    
    if (is_array($boissonsSelectionnees)) {
        foreach ($boissonsSelectionnees as $boisson => $quantite) {
            if (isset($boissons[$boisson]) && $quantite > 0) {
                $total += $boissons[$boisson] * intval($quantite);
            }
        }
    }
    
    return $total;
}

/**
 * Calcule les frais de paiement
 * 
 * @param string $modePaiement Mode de paiement ('carte' ou 'virement')
 * @return float Frais de paiement HT
 */
function calculerFraisPaiement($modePaiement) {
    if ($modePaiement === 'carte') {
        return FRAIS_CARTE_BANCAIRE;
    } elseif ($modePaiement === 'virement') {
        return FRAIS_VIREMENT;
    }
    return 0;
}

/**
 * Calcule le montant TTC à partir du montant HT
 * 
 * @param float $montantHT Montant hors taxes
 * @return float Montant TTC
 */
function calculerTTC($montantHT) {
    return $montantHT * (1 + TAUX_TVA);
}

/**
 * Calcule le montant de la TVA
 * 
 * @param float $montantHT Montant hors taxes
 * @return float Montant de la TVA
 */
function calculerTVA($montantHT) {
    return $montantHT * TAUX_TVA;
}

/**
 * Valide le nom ou prénom (alphanumérique, max 30 caractères)
 * 
 * @param string $nom Le nom à valider
 * @return bool True si valide
 */
function validerNom($nom) {
    if (empty($nom)) {
        return false;
    }
    
    if (strlen($nom) > 30) {
        return false;
    }
    
    // Alphanumérique avec espaces, apostrophes et tirets
    return preg_match('/^[a-zA-Z0-9\s\'-]+$/', $nom);
}

/**
 * Valide l'email (doit contenir @)
 * 
 * @param string $email L'email à valider
 * @return bool True si valide
 */
function validerEmail($email) {
    if (empty($email)) {
        return false;
    }
    
    // Vérifier qu'il contient @ et utiliser filter_var pour validation complète
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valide le téléphone (uniquement des chiffres)
 * 
 * @param string $tel Le numéro de téléphone à valider
 * @return bool True si valide
 */
function validerTelephone($tel) {
    if (empty($tel)) {
        return false;
    }
    
    // Uniquement des chiffres
    return preg_match('/^[0-9]+$/', $tel);
}

/**
 * Valide l'adresse (max 60 caractères)
 * 
 * @param string $adresse L'adresse à valider
 * @return bool True si valide
 */
function validerAdresse($adresse) {
    if (empty($adresse)) {
        return false;
    }
    
    return strlen($adresse) <= 60;
}

/**
 * Valide un entier positif
 * 
 * @param mixed $valeur La valeur à valider
 * @return bool True si valide
 */
function validerEntierPositif($valeur) {
    if (!is_numeric($valeur)) {
        return false;
    }
    
    $entier = intval($valeur);
    return $entier > 0 && $entier == $valeur;
}

/**
 * Valide les dates de voyage
 * 
 * @param string $dateDepart Date de départ
 * @param string $dateRetour Date de retour
 * @return array ['valide' => bool, 'message' => string]
 */
function validerDates($dateDepart, $dateRetour) {
    $dateSysteme = date('Y-m-d');
    $timestampSysteme = strtotime($dateSysteme);
    $timestampDepart = strtotime($dateDepart);
    $timestampRetour = strtotime($dateRetour);
    
    // Vérifier que la date de départ est dans la période valide
    $dateDebut = strtotime(DATE_DEBUT);
    $dateFin = strtotime(DATE_FIN);
    
    if ($timestampDepart < $dateDebut || $timestampDepart > $dateFin) {
        return [
            'valide' => false,
            'message' => 'La date de départ doit être entre le ' . date('d/m/Y', $dateDebut) . ' et le ' . date('d/m/Y', $dateFin)
        ];
    }
    
    // Vérifier que la date de départ est après la date système
    if ($timestampDepart <= $timestampSysteme) {
        return [
            'valide' => false,
            'message' => 'La date de départ doit être postérieure à aujourd\'hui'
        ];
    }
    
    // Vérifier que la date de retour est après la date de départ
    if ($timestampRetour <= $timestampDepart) {
        return [
            'valide' => false,
            'message' => 'La date de retour doit être postérieure à la date de départ'
        ];
    }
    
    return ['valide' => true, 'message' => ''];
}

/**
 * Formate un montant en euros
 * 
 * @param float $montant Le montant à formater
 * @return string Montant formaté
 */
function formaterMontant($montant) {
    return number_format($montant, 2, ',', ' ') . ' €';
}

/**
 * Formate une date au format français
 * 
 * @param string $date Date au format Y-m-d
 * @return string Date formatée
 */
function formaterDate($date) {
    $timestamp = strtotime($date);
    $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 
             'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    
    $jourSemaine = $jours[date('w', $timestamp)];
    $jour = date('d', $timestamp);
    $moisStr = $mois[intval(date('m', $timestamp))];
    $annee = date('Y', $timestamp);
    
    return ucfirst($jourSemaine) . ' ' . $jour . ' ' . $moisStr . ' ' . $annee;
}

/**
 * Calcule l'âge à partir d'une date de naissance
 * 
 * @param string $dateNaissance Date de naissance au format Y-m-d
 * @return int L'âge en années
 */
function calculerAge($dateNaissance) {
    $timestampNaissance = strtotime($dateNaissance);
    $timestampAujourdhui = time();
    
    $age = date('Y', $timestampAujourdhui) - date('Y', $timestampNaissance);
    
    // Ajuster si l'anniversaire n'est pas encore passé cette année
    if (date('md', $timestampAujourdhui) < date('md', $timestampNaissance)) {
        $age--;
    }
    
    return $age;
}

?>

