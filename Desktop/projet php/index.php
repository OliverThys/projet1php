<?php
/**
 * Page principale - Formulaire de réservation
 * Agence de voyage
 */

require_once 'config.php';
require_once 'fonctions.php';

// Déterminer le nom de l'agence (peut être personnalisé)
$nomAgence = NOM_AGENCE;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservation - <?php echo htmlspecialchars($nomAgence); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1><?php echo htmlspecialchars($nomAgence); ?></h1>
            <p class="sous-titre">Aéroport de départ : <?php echo htmlspecialchars(AEROPORT_DEPART); ?></p>
            <p class="periode">Période de validité : du <?php echo date('d/m/Y', strtotime(DATE_DEBUT)); ?> au <?php echo date('d/m/Y', strtotime(DATE_FIN)); ?></p>
        </header>

        <main>
            <div class="form-container">
                <h2>Formulaire de Réservation</h2>
                
                <?php
                // Afficher les messages d'erreur s'il y en a
                if (isset($_GET['erreur'])) {
                    echo '<div class="message erreur">' . htmlspecialchars($_GET['erreur']) . '</div>';
                }
                ?>

                <form action="traitement.php" method="POST" id="formReservation">
                    
                    <!-- Informations personnelles -->
                    <section class="section-form">
                        <h3>Informations Personnelles</h3>
                        
                        <div class="form-group">
                            <label for="nom">Nom <span class="requis">*</span></label>
                            <input type="text" id="nom" name="nom" required 
                                   maxlength="30" pattern="[a-zA-Z0-9\s'-]+"
                                   title="Alphanumérique, maximum 30 caractères"
                                   value="<?php echo isset($_GET['nom']) ? htmlspecialchars($_GET['nom']) : ''; ?>">
                            <small>Alphanumérique, max 30 caractères</small>
                        </div>

                        <div class="form-group">
                            <label for="prenom">Prénom <span class="requis">*</span></label>
                            <input type="text" id="prenom" name="prenom" required 
                                   maxlength="30" pattern="[a-zA-Z0-9\s'-]+"
                                   title="Alphanumérique, maximum 30 caractères"
                                   value="<?php echo isset($_GET['prenom']) ? htmlspecialchars($_GET['prenom']) : ''; ?>">
                            <small>Alphanumérique, max 30 caractères</small>
                        </div>

                        <div class="form-group">
                            <label for="email">Email <span class="requis">*</span></label>
                            <input type="email" id="email" name="email" required 
                                   value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>">
                            <small>Doit contenir @</small>
                        </div>

                        <div class="form-group">
                            <label for="telephone">Téléphone <span class="requis">*</span></label>
                            <input type="tel" id="telephone" name="telephone" required 
                                   pattern="[0-9]+" title="Uniquement des chiffres"
                                   value="<?php echo isset($_GET['telephone']) ? htmlspecialchars($_GET['telephone']) : ''; ?>">
                            <small>Uniquement des chiffres</small>
                        </div>

                        <div class="form-group">
                            <label for="adresse">Adresse <span class="requis">*</span></label>
                            <input type="text" id="adresse" name="adresse" required 
                                   maxlength="60"
                                   value="<?php echo isset($_GET['adresse']) ? htmlspecialchars($_GET['adresse']) : ''; ?>">
                            <small>Maximum 60 caractères</small>
                        </div>
                    </section>

                    <!-- Informations de voyage -->
                    <section class="section-form">
                        <h3>Informations de Voyage</h3>

                        <div class="form-group">
                            <label for="continent">Continent <span class="requis">*</span></label>
                            <select id="continent" name="continent" required onchange="updatePays()">
                                <option value="">-- Sélectionnez un continent --</option>
                                <?php foreach ($destinations as $continent => $paysList): ?>
                                    <option value="<?php echo htmlspecialchars($continent); ?>"
                                            <?php echo (isset($_GET['continent']) && $_GET['continent'] === $continent) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($continent); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="pays">Pays <span class="requis">*</span></label>
                            <select id="pays" name="pays" required onchange="updateVilles()">
                                <option value="">-- Sélectionnez d'abord un continent --</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="ville">Ville <span class="requis">*</span></label>
                            <select id="ville" name="ville" required onchange="updateAeroport()">
                                <option value="">-- Sélectionnez d'abord un pays --</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="aeroport">Aéroport <span class="requis">*</span></label>
                            <select id="aeroport" name="aeroport" required>
                                <option value="">-- Sélectionnez d'abord une ville --</option>
                            </select>
                            <small>Ville 1 = Aéroport 1, Ville 2 = Aéroport 2</small>
                        </div>

                        <div class="form-group">
                            <label for="date_depart">Date de départ <span class="requis">*</span></label>
                            <input type="date" id="date_depart" name="date_depart" required 
                                   min="<?php echo DATE_DEBUT; ?>" 
                                   max="<?php echo DATE_FIN; ?>"
                                   value="<?php echo isset($_GET['date_depart']) ? htmlspecialchars($_GET['date_depart']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="date_retour">Date de retour <span class="requis">*</span></label>
                            <input type="date" id="date_retour" name="date_retour" required 
                                   min="<?php echo DATE_DEBUT; ?>"
                                   value="<?php echo isset($_GET['date_retour']) ? htmlspecialchars($_GET['date_retour']) : ''; ?>">
                        </div>
                    </section>

                    <!-- Voyageurs -->
                    <section class="section-form">
                        <h3>Informations des Voyageurs</h3>

                        <div class="form-group">
                            <label for="nb_voyageurs">Nombre de voyageurs <span class="requis">*</span></label>
                            <input type="number" id="nb_voyageurs" name="nb_voyageurs" required 
                                   min="1" max="20" value="<?php echo isset($_GET['nb_voyageurs']) ? htmlspecialchars($_GET['nb_voyageurs']) : '1'; ?>"
                                   onchange="updateVoyageurs()">
                            <small>Entier positif</small>
                        </div>

                        <div id="voyageurs-container">
                            <!-- Les champs pour chaque voyageur seront générés dynamiquement -->
                        </div>
                    </section>

                    <!-- Menu -->
                    <section class="section-form">
                        <h3>Menu (inclus dans le tarif)</h3>
                        <p class="info">Un menu est inclus pour chaque voyageur (entrée, plat, dessert)</p>

                        <div class="form-group">
                            <label for="entree">Entrée <span class="requis">*</span></label>
                            <select id="entree" name="entree" required>
                                <?php foreach ($menu_entrees as $entree): ?>
                                    <option value="<?php echo htmlspecialchars($entree); ?>">
                                        <?php echo htmlspecialchars($entree); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="plat">Plat de résistance <span class="requis">*</span></label>
                            <select id="plat" name="plat" required>
                                <?php foreach ($menu_plats as $plat): ?>
                                    <option value="<?php echo htmlspecialchars($plat); ?>">
                                        <?php echo htmlspecialchars($plat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="dessert">Dessert <span class="requis">*</span></label>
                            <select id="dessert" name="dessert" required>
                                <?php foreach ($menu_desserts as $dessert): ?>
                                    <option value="<?php echo htmlspecialchars($dessert); ?>">
                                        <?php echo htmlspecialchars($dessert); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </section>

                    <!-- Boissons -->
                    <section class="section-form">
                        <h3>Boissons (payantes)</h3>
                        <p class="info">Sélectionnez les boissons et quantités souhaitées</p>

                        <?php foreach ($boissons as $boisson => $prix): ?>
                            <div class="form-group">
                                <label>
                                    <?php echo htmlspecialchars($boisson); ?> 
                                    (<?php echo formaterMontant($prix); ?>)
                                </label>
                                <input type="number" name="boissons[<?php echo htmlspecialchars($boisson); ?>]" 
                                       min="0" value="0" class="boisson-input">
                            </div>
                        <?php endforeach; ?>
                    </section>

                    <!-- Paiement -->
                    <section class="section-form">
                        <h3>Mode de Paiement <span class="requis">*</span></h3>

                        <div class="form-group">
                            <label>
                                <input type="radio" name="mode_paiement" value="carte" required>
                                Carte bancaire internationale (+<?php echo formaterMontant(FRAIS_CARTE_BANCAIRE); ?>)
                            </label>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="radio" name="mode_paiement" value="virement" required>
                                Virement bancaire (+<?php echo formaterMontant(FRAIS_VIREMENT); ?>)
                            </label>
                        </div>
                    </section>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Calculer le prix et voir le récapitulatif</button>
                        <button type="reset" class="btn btn-secondary">Réinitialiser</button>
                    </div>
                </form>
            </div>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($nomAgence); ?>. Tous droits réservés.</p>
        </footer>
    </div>

    <script>
        // Données des destinations pour JavaScript
        const destinations = <?php echo json_encode($destinations); ?>;

        // Fonction pour mettre à jour les pays selon le continent
        function updatePays() {
            const continent = document.getElementById('continent').value;
            const paysSelect = document.getElementById('pays');
            const villeSelect = document.getElementById('ville');
            const aeroportSelect = document.getElementById('aeroport');
            
            paysSelect.innerHTML = '<option value="">-- Sélectionnez un pays --</option>';
            villeSelect.innerHTML = '<option value="">-- Sélectionnez d\'abord un pays --</option>';
            aeroportSelect.innerHTML = '<option value="">-- Sélectionnez d\'abord une ville --</option>';
            
            if (continent && destinations[continent]) {
                for (const pays in destinations[continent]) {
                    const option = document.createElement('option');
                    option.value = pays;
                    option.textContent = pays;
                    paysSelect.appendChild(option);
                }
            }
        }

        // Fonction pour mettre à jour les villes selon le pays
        function updateVilles() {
            const continent = document.getElementById('continent').value;
            const pays = document.getElementById('pays').value;
            const villeSelect = document.getElementById('ville');
            const aeroportSelect = document.getElementById('aeroport');
            
            villeSelect.innerHTML = '<option value="">-- Sélectionnez une ville --</option>';
            aeroportSelect.innerHTML = '<option value="">-- Sélectionnez d\'abord une ville --</option>';
            
            if (continent && pays && destinations[continent] && destinations[continent][pays]) {
                const villes = destinations[continent][pays];
                villes.forEach((ville, index) => {
                    const option = document.createElement('option');
                    option.value = ville;
                    option.textContent = ville;
                    villeSelect.appendChild(option);
                });
            }
        }

        // Fonction pour mettre à jour les aéroports selon la ville
        function updateAeroport() {
            const ville = document.getElementById('ville').value;
            const aeroportSelect = document.getElementById('aeroport');
            
            aeroportSelect.innerHTML = '<option value="">-- Sélectionnez un aéroport --</option>';
            
            if (ville) {
                const option1 = document.createElement('option');
                option1.value = '0';
                option1.textContent = 'Aéroport 1 (Ville 1)';
                aeroportSelect.appendChild(option1);
                
                const option2 = document.createElement('option');
                option2.value = '1';
                option2.textContent = 'Aéroport 2 (Ville 2)';
                aeroportSelect.appendChild(option2);
            }
        }

        // Fonction pour mettre à jour les champs voyageurs
        function updateVoyageurs() {
            const nbVoyageurs = parseInt(document.getElementById('nb_voyageurs').value) || 1;
            const container = document.getElementById('voyageurs-container');
            
            container.innerHTML = '';
            
            for (let i = 1; i <= nbVoyageurs; i++) {
                const div = document.createElement('div');
                div.className = 'voyageur-group';
                div.innerHTML = `
                    <h4>Voyageur ${i}</h4>
                    <div class="form-group">
                        <label for="age_${i}">Âge <span class="requis">*</span></label>
                        <input type="number" id="age_${i}" name="ages[]" required min="0" max="120">
                    </div>
                    <div class="form-group">
                        <label for="poids_${i}">Poids des bagages (kg) <span class="requis">*</span></label>
                        <input type="number" id="poids_${i}" name="poids[]" required min="0" max="<?php echo POIDS_MAX_BAGAGE; ?>" step="0.1">
                        <small>25 kg inclus, supplément: <?php echo formaterMontant(PRIX_KG_SUPPLEMENTAIRE); ?>/kg</small>
                    </div>
                `;
                container.appendChild(div);
            }
        }

        // Initialiser les voyageurs au chargement
        document.addEventListener('DOMContentLoaded', function() {
            updateVoyageurs();
            
            // Restaurer les valeurs si elles existent
            <?php if (isset($_GET['continent'])): ?>
                document.getElementById('continent').value = '<?php echo htmlspecialchars($_GET['continent']); ?>';
                updatePays();
                <?php if (isset($_GET['pays'])): ?>
                    document.getElementById('pays').value = '<?php echo htmlspecialchars($_GET['pays']); ?>';
                    updateVilles();
                    <?php if (isset($_GET['ville'])): ?>
                        document.getElementById('ville').value = '<?php echo htmlspecialchars($_GET['ville']); ?>';
                        updateAeroport();
                        <?php if (isset($_GET['aeroport'])): ?>
                            document.getElementById('aeroport').value = '<?php echo htmlspecialchars($_GET['aeroport']); ?>';
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        });

        // Validation de la date de retour
        document.getElementById('date_depart').addEventListener('change', function() {
            const dateDepart = this.value;
            const dateRetour = document.getElementById('date_retour');
            if (dateDepart) {
                dateRetour.min = dateDepart;
            }
        });
    </script>
</body>
</html>

