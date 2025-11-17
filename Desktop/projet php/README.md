# Projet : Gestion d'une Agence de Voyage en PHP

## Description

Application web complète de gestion de réservations pour une agence de voyage, développée en PHP 7. Le système permet aux clients de réserver des voyages aller-retour depuis Bruxelles Charleroi Sud vers diverses destinations internationales.

## Fonctionnalités

### ✅ Réservation de voyages
- Sélection de destinations par continent (Afrique, Europe, Amérique, Asie)
- 12 pays avec 2 villes chacun
- Gestion de multiples voyageurs avec leurs âges
- Calcul automatique des tarifs selon l'âge et la destination
- Réduction de 10% si réservation ≥ 3 mois avant le départ

### ✅ Gestion des bagages
- 25 kg inclus par voyageur
- Supplément de 20€/kg au-delà

### ✅ Menu et boissons
- Menu inclus : entrée, plat, dessert (choix unique)
- Boissons payantes (eau, bière, vin, coca, jus d'orange)

### ✅ Calculs automatiques
- Calcul des tarifs selon l'âge (3 catégories : <2 ans, 2-11 ans, ≥12 ans)
- Calcul des suppléments bagages
- Calcul des frais de paiement (carte : +30€, virement : +20€)
- Calcul de la TVA (20%)
- Affichage détaillé HT et TTC

### ✅ Validation des données
- Validation complète côté client (HTML5) et serveur (PHP)
- Vérification des formats (nom, email, téléphone, dates)
- Contrôles de cohérence (dates, poids, etc.)

### ✅ Interface professionnelle
- Design moderne et responsive
- Formulaire intuitif avec validation en temps réel
- Récapitulatif détaillé imprimable
- Navigation fluide entre les pages

## Structure du projet

```
projet-php/
├── index.php                    # Application complète (formulaire, traitement, récapitulatif)
├── style.css                    # Feuille de style
├── compagnieaerienne.sql        # Script SQL pour créer la base de données
└── README.md                    # Documentation
```

## Prérequis

- PHP 7.0 ou supérieur
- MySQL 5.7 ou supérieur (ou MariaDB)
- Serveur web (Apache, Nginx, ou serveur PHP intégré)
- Navigateur web moderne

## Installation

1. **Télécharger les fichiers** dans le répertoire de votre serveur web (ex: `htdocs`, `www`)

2. **Créer la base de données** :
   - Importer le fichier `compagnieaerienne.sql` dans phpMyAdmin ou MySQL
   - Ou exécuter : `mysql -u root -p < compagnieaerienne.sql`

3. **Configurer la connexion** :
   - Modifier les paramètres de connexion dans `index.php` (ligne 10) si nécessaire :
   ```php
   $pdo = new PDO('mysql:host=127.0.0.1:3306;dbname=compagnieaerienne', 'root', '');
   ```

4. **Configurer le serveur** :
   - Avec WAMP/XAMPP : placer les fichiers dans `wamp/www/` ou `xampp/htdocs/`
   - Avec serveur PHP intégré : `php -S localhost:8000` dans le dossier du projet

5. **Accéder à l'application** :
   - Ouvrir `http://localhost/projet-php/index.php` dans votre navigateur

## Utilisation

### Pour le client

1. **Accéder au formulaire** : Ouvrir `index.php`
2. **Remplir les informations** :
   - Informations personnelles (nom, prénom, email, téléphone, adresse)
   - Sélectionner la destination (continent → pays → ville)
   - Choisir les dates de départ et retour
   - Indiquer le nombre de voyageurs
   - Pour chaque voyageur : âge et poids des bagages
   - Sélectionner le menu (entrée, plat, dessert)
   - Choisir les boissons et quantités
   - Sélectionner le mode de paiement
3. **Valider** : Le système calcule automatiquement tous les prix
4. **Consulter le récapitulatif** : Affichage détaillé avec prix total TTC et enregistrement en base de données

### Pour le développeur

#### Personnaliser le nom de l'agence
Modifier la constante `NOM_AGENCE` dans `index.php` (ligne 13) :
```php
define('NOM_AGENCE', 'Votre Nom d\'Agence');
```

#### Modifier les tarifs
Les tarifs sont stockés dans la base de données MySQL, dans la table `vol`. Chaque vol a :
- `prix_bebe` : prix pour les moins de 2 ans
- `prix_enfant` : prix pour les 2-11 ans
- `prix_adulte` : prix pour les 12 ans et plus

#### Ajouter des destinations
1. Ajouter les données dans la base de données :
   - Ajouter un continent dans la table `continent`
   - Ajouter un pays dans la table `pays` (avec `id_continent`)
   - Ajouter une ville dans la table `ville` (avec `id_pays`)
   - Ajouter un vol dans la table `vol` (avec `id_ville_arrivee` et les prix)
2. Les données seront automatiquement disponibles dans les menus déroulants

## Règles de calcul

### Tarifs de base
- Selon l'âge du voyageur (3 catégories : bébé <2 ans, enfant 2-11 ans, adulte ≥12 ans)
- Selon la destination (ville) choisie
- Tarifs stockés dans la base de données (table `vol`)
- Tarifs en euros HT

### Réduction
- **10% de réduction** si réservation effectuée au minimum 3 mois avant la date de départ
- Calculée sur le tarif de base du voyage

### Bagages
- **25 kg inclus** par voyageur
- **20€ par kg supplémentaire**

### Menu
- **Inclus dans le tarif** : 1 entrée + 1 plat + 1 dessert par voyageur
- Choix unique pour tous les voyageurs

### Boissons
- **Payantes** : prix selon le document C
- Quantité choisie par voyageur

### Frais de paiement
- **Carte bancaire internationale** : +30€ HT
- **Virement bancaire** : +20€ HT

### TVA
- **20%** sur le total HT
- Prix final affiché en TTC

## Validation des données

### Nom et Prénom
- Alphanumérique (lettres, chiffres, espaces, apostrophes, tirets)
- Maximum 30 caractères

### Email
- Doit contenir @
- Validation format email standard

### Téléphone
- Uniquement des chiffres (pas de /, \, parenthèses)

### Adresse
- Maximum 60 caractères

### Dates
- Date de départ : entre le 01/12/2025 et le 30/06/2026
- Date de départ : postérieure à aujourd'hui
- Date de retour : postérieure à la date de départ

### Nombre de voyageurs
- Entier positif (1 à 20)

### Poids bagages
- Nombre positif (décimal autorisé)
- Maximum 100 kg par voyageur

## Technologies utilisées

- **PHP 7** : Langage serveur
- **MySQL** : Base de données pour stocker les destinations, vols et réservations
- **PDO** : Interface d'accès aux données pour la sécurité
- **HTML5** : Structure des pages
- **CSS3** : Styles et design responsive
- **Sessions PHP** : Stockage temporaire des données de réservation

## Bonnes pratiques respectées

✅ Code commenté et documenté  
✅ Indentation cohérente  
✅ Noms de variables explicites  
✅ Architecture monolithique simple (un seul fichier PHP)  
✅ Utilisation de PDO pour la sécurité (protection contre les injections SQL)  
✅ Validation côté client (HTML5) et serveur (PHP)  
✅ Protection contre les injections XSS (htmlspecialchars)  
✅ Gestion des erreurs  
✅ Interface utilisateur intuitive  
✅ Base de données normalisée avec clés étrangères  

## Améliorations possibles

- Système d'authentification pour l'agence
- Envoi d'email de confirmation
- Génération de PDF pour le récapitulatif
- Gestion des paiements en ligne
- Historique des réservations avec recherche
- Statistiques et rapports
- Interface d'administration pour gérer les destinations
- API REST pour intégration avec d'autres systèmes

## Auteur

Projet développé dans le cadre d'un cours de PHP niveau Bac+2 Informatique.

## Licence

Ce projet est fourni à des fins éducatives.

---

**Date de création** : 2025  
**Version** : 1.0  
**Dernière mise à jour** : 2025

