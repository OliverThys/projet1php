<?php
/**
 * Page récapitulatif de la réservation
 * 
 * Affiche tous les détails de la réservation avec le prix total
 */

session_start();
require_once 'config.php';
require_once 'fonctions.php';

// Vérifier qu'une réservation existe en session
if (!isset($_SESSION['reservation'])) {
    header('Location: index.php');
    exit;
}

$reservation = $_SESSION['reservation'];
$nomAgence = NOM_AGENCE;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Récapitulatif - <?php echo htmlspecialchars($nomAgence); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .recap-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .recap-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 5px;
            border-left: 4px solid #007bff;
        }
        .recap-section h3 {
            margin-top: 0;
            color: #007bff;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .recap-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
        }
        .recap-row:last-child {
            border-bottom: none;
        }
        .recap-label {
            font-weight: bold;
            color: #555;
        }
        .recap-value {
            color: #333;
        }
        .table-recap {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .table-recap th,
        .table-recap td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .table-recap th {
            background: #007bff;
            color: white;
            font-weight: bold;
        }
        .table-recap tr:hover {
            background: #f5f5f5;
        }
        .total-box {
            background: #007bff;
            color: white;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .total-box h3 {
            margin-top: 0;
            color: white;
            border-bottom: 2px solid rgba(255,255,255,0.3);
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 1.1em;
        }
        .total-final {
            font-size: 1.5em;
            font-weight: bold;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid rgba(255,255,255,0.3);
        }
        .btn-actions {
            text-align: center;
            margin-top: 30px;
        }
        .btn-actions .btn {
            margin: 0 10px;
        }
        .info-badge {
            display: inline-block;
            padding: 5px 10px;
            background: #28a745;
            color: white;
            border-radius: 3px;
            font-size: 0.9em;
            margin-left: 10px;
        }
        .reduction-badge {
            background: #ffc107;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><?php echo htmlspecialchars($nomAgence); ?></h1>
            <p class="sous-titre">Récapitulatif de votre réservation</p>
        </header>

        <main>
            <div class="recap-container">
                
                <!-- Informations personnelles -->
                <section class="recap-section">
                    <h3>Informations Personnelles</h3>
                    <div class="recap-row">
                        <span class="recap-label">Nom :</span>
                        <span class="recap-value"><?php echo htmlspecialchars($reservation['nom']); ?></span>
                    </div>
                    <div class="recap-row">
                        <span class="recap-label">Prénom :</span>
                        <span class="recap-value"><?php echo htmlspecialchars($reservation['prenom']); ?></span>
                    </div>
                    <div class="recap-row">
                        <span class="recap-label">Email :</span>
                        <span class="recap-value"><?php echo htmlspecialchars($reservation['email']); ?></span>
                    </div>
                    <div class="recap-row">
                        <span class="recap-label">Téléphone :</span>
                        <span class="recap-value"><?php echo htmlspecialchars($reservation['telephone']); ?></span>
                    </div>
                    <div class="recap-row">
                        <span class="recap-label">Adresse :</span>
                        <span class="recap-value"><?php echo htmlspecialchars($reservation['adresse']); ?></span>
                    </div>
                </section>

                <!-- Informations de voyage -->
                <section class="recap-section">
                    <h3>Détails du Voyage</h3>
                    <div class="recap-row">
                        <span class="recap-label">Aéroport de départ :</span>
                        <span class="recap-value"><?php echo htmlspecialchars(AEROPORT_DEPART); ?></span>
                    </div>
                    <div class="recap-row">
                        <span class="recap-label">Destination :</span>
                        <span class="recap-value">
                            <?php echo htmlspecialchars($reservation['ville']); ?>, 
                            <?php echo htmlspecialchars($reservation['pays']); ?> 
                            (<?php echo htmlspecialchars($reservation['continent']); ?>)
                        </span>
                    </div>
                    <div class="recap-row">
                        <span class="recap-label">Aéroport d'arrivée :</span>
                        <span class="recap-value">
                            Aéroport <?php echo ($reservation['aeroport'] == 0) ? '1' : '2'; ?> 
                            (<?php echo htmlspecialchars($reservation['ville']); ?>)
                        </span>
                    </div>
                    <div class="recap-row">
                        <span class="recap-label">Date de départ :</span>
                        <span class="recap-value"><?php echo formaterDate($reservation['date_depart']); ?></span>
                    </div>
                    <div class="recap-row">
                        <span class="recap-label">Date de retour :</span>
                        <span class="recap-value"><?php echo formaterDate($reservation['date_retour']); ?></span>
                    </div>
                    <div class="recap-row">
                        <span class="recap-label">Date de réservation :</span>
                        <span class="recap-value"><?php echo formaterDate($reservation['date_reservation']); ?></span>
                    </div>
                    <?php if ($reservation['reduction_applicable']): ?>
                        <div class="recap-row">
                            <span class="recap-label">Réduction :</span>
                            <span class="recap-value">
                                <span class="info-badge reduction-badge">
                                    10% de réduction appliquée (réservation ≥ 3 mois avant)
                                </span>
                            </span>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Détails des voyageurs -->
                <section class="recap-section">
                    <h3>Détails par Voyageur (<?php echo $reservation['nb_voyageurs']; ?> voyageur(s))</h3>
                    <table class="table-recap">
                        <thead>
                            <tr>
                                <th>Voyageur</th>
                                <th>Âge</th>
                                <th>Catégorie</th>
                                <th>Tarif de base HT</th>
                                <th>Réduction</th>
                                <th>Poids bagages</th>
                                <th>Supplément bagages HT</th>
                                <th>Total HT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $categories = ['< 2 ans', '2-11 ans', '≥ 12 ans'];
                            foreach ($reservation['voyageurs'] as $voyageur): 
                            ?>
                                <tr>
                                    <td><?php echo $voyageur['numero']; ?></td>
                                    <td><?php echo $voyageur['age']; ?> ans</td>
                                    <td><?php echo $categories[$voyageur['categorie_age']]; ?></td>
                                    <td><?php echo formaterMontant($voyageur['tarif_base']); ?></td>
                                    <td>
                                        <?php if ($voyageur['reduction'] > 0): ?>
                                            -<?php echo formaterMontant($voyageur['reduction']); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($voyageur['poids_bagage'], 1, ',', ' '); ?> kg</td>
                                    <td>
                                        <?php if ($voyageur['prix_bagages'] > 0): ?>
                                            <?php echo formaterMontant($voyageur['prix_bagages']); ?>
                                            <small>(<?php echo number_format($voyageur['poids_supplementaire'], 1, ',', ' '); ?> kg supplémentaire)</small>
                                        <?php else: ?>
                                            <span class="info-badge">Inclus (≤ 25 kg)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo formaterMontant($voyageur['total']); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>

                <!-- Menu -->
                <section class="recap-section">
                    <h3>Menu Sélectionné (inclus dans le tarif)</h3>
                    <div class="recap-row">
                        <span class="recap-label">Entrée :</span>
                        <span class="recap-value"><?php echo htmlspecialchars($reservation['entree']); ?></span>
                    </div>
                    <div class="recap-row">
                        <span class="recap-label">Plat de résistance :</span>
                        <span class="recap-value"><?php echo htmlspecialchars($reservation['plat']); ?></span>
                    </div>
                    <div class="recap-row">
                        <span class="recap-label">Dessert :</span>
                        <span class="recap-value"><?php echo htmlspecialchars($reservation['dessert']); ?></span>
                    </div>
                    <p class="info"><em>Le menu est inclus pour chaque voyageur dans le tarif du voyage.</em></p>
                </section>

                <!-- Boissons -->
                <section class="recap-section">
                    <h3>Boissons Sélectionnées</h3>
                    <?php 
                    $boissonsCommande = false;
                    foreach ($reservation['boissons'] as $boisson => $quantite): 
                        if (intval($quantite) > 0):
                            $boissonsCommande = true;
                            $prixBoisson = $boissons[$boisson] ?? 0;
                            $prixTotal = $prixBoisson * intval($quantite);
                    ?>
                        <div class="recap-row">
                            <span class="recap-label">
                                <?php echo htmlspecialchars($boisson); ?> 
                                (<?php echo formaterMontant($prixBoisson); ?> × <?php echo $quantite; ?>)
                            </span>
                            <span class="recap-value"><?php echo formaterMontant($prixTotal); ?></span>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    if (!$boissonsCommande):
                    ?>
                        <p class="info">Aucune boisson sélectionnée</p>
                    <?php endif; ?>
                </section>

                <!-- Mode de paiement -->
                <section class="recap-section">
                    <h3>Mode de Paiement</h3>
                    <div class="recap-row">
                        <span class="recap-label">Mode :</span>
                        <span class="recap-value">
                            <?php 
                            if ($reservation['mode_paiement'] === 'carte') {
                                echo 'Carte bancaire internationale';
                            } else {
                                echo 'Virement bancaire';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="recap-row">
                        <span class="recap-label">Frais de paiement HT :</span>
                        <span class="recap-value"><?php echo formaterMontant($reservation['frais_paiement_ht']); ?></span>
                    </div>
                </section>

                <!-- Récapitulatif financier -->
                <section class="total-box">
                    <h3>Récapitulatif Financier</h3>
                    
                    <div class="total-row">
                        <span>Total voyages HT :</span>
                        <span><?php echo formaterMontant($reservation['total_voyage_ht']); ?></span>
                    </div>
                    
                    <div class="total-row">
                        <span>Supplément bagages HT :</span>
                        <span><?php echo formaterMontant($reservation['total_bagages_ht']); ?></span>
                    </div>
                    
                    <div class="total-row">
                        <span>Boissons HT :</span>
                        <span><?php echo formaterMontant($reservation['total_boissons_ht']); ?></span>
                    </div>
                    
                    <div class="total-row">
                        <span>Frais de paiement HT :</span>
                        <span><?php echo formaterMontant($reservation['frais_paiement_ht']); ?></span>
                    </div>
                    
                    <div class="total-row" style="border-top: 1px solid rgba(255,255,255,0.3); padding-top: 10px; margin-top: 10px;">
                        <span><strong>Total HT :</strong></span>
                        <span><strong><?php echo formaterMontant($reservation['total_ht']); ?></strong></span>
                    </div>
                    
                    <div class="total-row">
                        <span>TVA (<?php echo (TAUX_TVA * 100); ?>%) :</span>
                        <span><?php echo formaterMontant($reservation['montant_tva']); ?></span>
                    </div>
                    
                    <div class="total-row total-final">
                        <span>TOTAL TTC :</span>
                        <span><?php echo formaterMontant($reservation['total_ttc']); ?></span>
                    </div>
                </section>

                <!-- Informations importantes -->
                <section class="recap-section" style="background: #fff3cd; border-left-color: #ffc107;">
                    <h3 style="color: #856404;">Informations Importantes</h3>
                    <ul style="color: #856404;">
                        <li>Ce récapitulatif doit être envoyé par l'agence au responsable du voyage avant le départ.</li>
                        <li>Les vols ont lieu pendant le repas de midi, le menu est inclus dans le tarif.</li>
                        <li>Bagage inclus : <?php echo POIDS_BAGAGE_INCLUS; ?> kg par voyageur.</li>
                        <li>Les prix sont valables pour la période du <?php echo date('d/m/Y', strtotime(DATE_DEBUT)); ?> au <?php echo date('d/m/Y', strtotime(DATE_FIN)); ?>.</li>
                        <li>Réservation effectuée le <?php echo formaterDate($reservation['date_reservation']); ?>.</li>
                    </ul>
                </section>

                <!-- Actions -->
                <div class="btn-actions">
                    <a href="index.php" class="btn btn-secondary">Nouvelle réservation</a>
                    <button onclick="window.print()" class="btn btn-primary">Imprimer le récapitulatif</button>
                </div>

            </div>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($nomAgence); ?>. Tous droits réservés.</p>
        </footer>
    </div>
</body>
</html>

