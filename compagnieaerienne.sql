-- Script SQL pour créer la base de données de l'agence de voyage
-- J'ai exporté ça depuis phpMyAdmin

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Base de données compagnieaerienne

-- Table client - infos des clients qui réservent
DROP TABLE IF EXISTS `client`;
CREATE TABLE IF NOT EXISTS `client` (
  `id_client` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(30) DEFAULT NULL,
  `prenom` varchar(30) DEFAULT NULL,
  `mail` varchar(50) DEFAULT NULL,
  `telephone` varchar(15) DEFAULT NULL,
  `adresse` varchar(60) NOT NULL,
  PRIMARY KEY (`id_client`)
) ;

-- Table continent - les continents disponibles
DROP TABLE IF EXISTS `continent`;
CREATE TABLE IF NOT EXISTS `continent` (
  `id_continent` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(30) NOT NULL,
  PRIMARY KEY (`id_continent`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Données des continents
INSERT INTO `continent` (`id_continent`, `nom`) VALUES
(1, 'Afrique'),
(2, 'Europe'),
(3, 'Amérique'),
(4, 'Asie');

-- Table pays - les pays par continent
DROP TABLE IF EXISTS `pays`;
CREATE TABLE IF NOT EXISTS `pays` (
  `id_pays` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(30) NOT NULL,
  `id_continent` int NOT NULL,
  PRIMARY KEY (`id_pays`),
  KEY `id_continent` (`id_continent`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Données des pays
INSERT INTO `pays` (`id_pays`, `nom`, `id_continent`) VALUES
(1, 'Maroc', 1),
(2, 'Sénégal', 1),
(3, 'Kenya', 1),
(4, 'Espagne', 2),
(5, 'Italie', 2),
(6, 'Portugal', 2),
(7, 'Etats Unis', 3),
(8, 'Brésil', 3),
(9, 'Argentine', 3),
(10, 'Japon', 4),
(11, 'Chine', 4),
(12, 'Turquie', 4);

-- Table ville - les villes par pays
DROP TABLE IF EXISTS `ville`;
CREATE TABLE IF NOT EXISTS `ville` (
  `id_ville` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) NOT NULL,
  `id_pays` int NOT NULL,
  PRIMARY KEY (`id_ville`),
  KEY `id_pays` (`id_pays`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Données des villes
INSERT INTO `ville` (`id_ville`, `nom`, `id_pays`) VALUES
(1, 'Tokyo', 10),
(2, 'Nagoya', 10),
(3, 'Pékin', 11),
(4, 'Shanghai', 11),
(5, 'Istanbul', 12),
(6, 'Izmir', 12),
(7, 'Los angeles', 7),
(8, 'New York', 7),
(9, 'Rio de Janeiro', 8),
(10, 'Sao Paulo', 8),
(11, 'Buenos Aires', 9),
(12, 'Mendoza', 9),
(13, 'Madrid', 4),
(14, 'Barcelone', 4),
(15, 'Rome', 5),
(16, 'Venise', 5),
(17, 'Porto', 6),
(18, 'Lisbonne', 6),
(19, 'Marrakech', 1),
(20, 'Agadir', 1),
(21, 'Dakar', 2),
(22, 'Touba', 2),
(23, 'Nairobi', 3),
(24, 'Mombasa', 3);

-- Table vol - les vols disponibles avec leurs prix
DROP TABLE IF EXISTS `vol`;
CREATE TABLE IF NOT EXISTS `vol` (
  `id_vol` int NOT NULL AUTO_INCREMENT,
  `id_ville_arrivee` int NOT NULL,
  `prix_bebe` decimal(6,2) NOT NULL,
  `prix_enfant` decimal(6,2) NOT NULL,
  `prix_adulte` decimal(6,2) NOT NULL,
  PRIMARY KEY (`id_vol`),
  KEY `id_ville_arrivee` (`id_ville_arrivee`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Données des vols avec les prix selon l'âge
INSERT INTO `vol` (`id_vol`, `id_ville_arrivee`, `prix_bebe`, `prix_enfant`, `prix_adulte`) VALUES
(1, 1, 200.00, 250.00, 400.00),
(2, 2, 210.00, 260.00, 450.00),
(3, 3, 180.00, 240.00, 390.00),
(4, 4, 190.00, 250.00, 420.00),
(5, 5, 100.00, 150.00, 250.00),
(6, 6, 120.00, 160.00, 260.00),
(7, 7, 160.00, 180.00, 280.00),
(8, 8, 170.00, 200.00, 300.00),
(9, 9, 180.00, 250.00, 380.00),
(10, 10, 190.00, 260.00, 400.00),
(11, 11, 200.00, 260.00, 390.00),
(12, 12, 220.00, 290.00, 420.00),
(13, 13, 150.00, 200.00, 390.00),
(14, 14, 160.00, 220.00, 400.00),
(15, 15, 160.00, 220.00, 400.00),
(16, 16, 190.00, 250.00, 450.00),
(17, 17, 150.00, 200.00, 350.00),
(18, 18, 160.00, 230.00, 380.00),
(19, 19, 140.00, 180.00, 250.00),
(20, 20, 150.00, 190.00, 300.00),
(21, 21, 200.00, 250.00, 380.00),
(22, 22, 220.00, 290.00, 400.00),
(23, 23, 220.00, 290.00, 400.00),
(24, 24, 250.00, 320.00, 420.00),
(26, 2, 210.00, 260.00, 450.00);

-- Table voyage - les réservations des clients
DROP TABLE IF EXISTS `voyage`;
CREATE TABLE IF NOT EXISTS `voyage` (
  `id_voyage` int NOT NULL AUTO_INCREMENT,
  `id_client` int NOT NULL,
  `id_vol` int NOT NULL,
  `date_depart` date NOT NULL,
  `date_retour` date NOT NULL,
  `nb_adulte` int NOT NULL,
  `nb_enfant` int NOT NULL,
  `nb_bebe` int NOT NULL,
  `poids_bagage` int NOT NULL,
  `moyen_paiement` enum('Carte Bancaire','Virement sur compte') NOT NULL,
  PRIMARY KEY (`id_voyage`),
  KEY `id_client` (`id_client`),
  KEY `id_vol` (`id_vol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Contraintes de clés étrangères - pour lier les tables entre elles
-- Pays -> Continent
ALTER TABLE `pays`
  ADD CONSTRAINT `pays_ibfk_1` FOREIGN KEY (`id_continent`) REFERENCES `continent` (`id_continent`) ON DELETE RESTRICT ON UPDATE RESTRICT;

-- Ville -> Pays
ALTER TABLE `ville`
  ADD CONSTRAINT `ville_ibfk_1` FOREIGN KEY (`id_pays`) REFERENCES `pays` (`id_pays`) ON DELETE RESTRICT ON UPDATE RESTRICT;

-- Vol -> Ville
ALTER TABLE `vol`
  ADD CONSTRAINT `vol_ibfk_1` FOREIGN KEY (`id_ville_arrivee`) REFERENCES `ville` (`id_ville`) ON DELETE RESTRICT ON UPDATE RESTRICT;

-- Voyage -> Client et Vol
ALTER TABLE `voyage`
  ADD CONSTRAINT `voyage_ibfk_1` FOREIGN KEY (`id_client`) REFERENCES `client` (`id_client`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `voyage_ibfk_2` FOREIGN KEY (`id_vol`) REFERENCES `vol` (`id_vol`) ON DELETE RESTRICT ON UPDATE RESTRICT;
COMMIT;

