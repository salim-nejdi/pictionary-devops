-- =============================================================================
-- init.sql — Initialisation de la base de données Pictionary
--
-- Ce fichier est utilisé UNIQUEMENT en développement local via Docker Compose.
-- Il est monté dans le conteneur MariaDB et exécuté automatiquement au
-- premier démarrage, quand le volume de données est vide.
--
-- =============================================================================


-- -----------------------------------------------------------------------------
-- BASE DE DONNÉES
-- -----------------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS pictionary
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE pictionary;


-- -----------------------------------------------------------------------------
-- TABLE
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mots (
    id  INT          AUTO_INCREMENT PRIMARY KEY,
    mot VARCHAR(255) NOT NULL,
    UNIQUE KEY uq_mot (mot)
) ENGINE=InnoDB
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- DONNÉES
-- -----------------------------------------------------------------------------

-- Animaux
INSERT IGNORE INTO mots (mot) VALUES
    ('Chat'), ('Chien'), ('Lion'), ('Tigre'), ('Éléphant'),
    ('Girafe'), ('Singe'), ('Lapin'), ('Cochon'), ('Vache'),
    ('Cheval'), ('Mouton'), ('Poule'), ('Canard'), ('Poisson'),
    ('Requin'), ('Dauphin'), ('Pieuvre'), ('Crabe'), ('Grenouille'),
    ('Serpent'), ('Crocodile'), ('Tortue'), ('Dinosaure'), ('Dragon'),
    ('Papillon'), ('Abeille'), ('Araignée'), ('Hibou'), ('Pingouin'),
    ('Flamant'), ('Perroquet'), ('Aigle');

-- Nourriture
INSERT IGNORE INTO mots (mot) VALUES
    ('Pizza'), ('Burger'), ('Frite'), ('Hot Dog'), ('Sushi'),
    ('Ramen'), ('Taco'), ('Glace'), ('Gâteau'), ('Cookie'),
    ('Chocolat'), ('Bonbon'), ('Pomme'), ('Banane'), ('Fraise'),
    ('Raisin'), ('Pastèque'), ('Ananas'), ('Citron'), ('Cerise'),
    ('Mangue'), ('Carotte'), ('Maïs'), ('Brocoli'), ('Aubergine'),
    ('Champignon'), ('Fromage'), ('Pain'), ('Café'), ('Thé');

-- Véhicules
INSERT IGNORE INTO mots (mot) VALUES
    ('Voiture'), ('Camion'), ('Bus'), ('Moto'), ('Vélo'),
    ('Trottinette'), ('Avion'), ('Hélicoptère'), ('Fusée'), ('Bateau'),
    ('Train'), ('Taxi'), ('Ambulance'), ('Tracteur'), ('Skateboard');

-- Nature & météo
INSERT IGNORE INTO mots (mot) VALUES
    ('Soleil'), ('Lune'), ('Étoile'), ('Nuage'), ('Pluie'),
    ('Neige'), ('Arc-en-ciel'), ('Éclair'), ('Volcan'), ('Montagne'),
    ('Plage'), ('Désert'), ('Forêt'), ('Fleur'), ('Cactus'),
    ('Arbre'), ('Feuille'), ('Mer'), ('Feu'), ('Vent');

-- Objets & lieux
INSERT IGNORE INTO mots (mot) VALUES
    ('Maison'), ('Château'), ('École'), ('Hôpital'), ('Église'),
    ('Tente'), ('Téléphone'), ('Ordinateur'), ('Télévision'), ('Caméra'),
    ('Lunettes'), ('Clé'), ('Cadenas'), ('Lampe'), ('Bougie'),
    ('Livre'), ('Crayon'), ('Marteau'), ('Ballon'), ('Cadeau'),
    ('Couronne'), ('Diamant'), ('Bague'), ('Chapeau'), ('Parapluie');

-- Sport & musique
INSERT IGNORE INTO mots (mot) VALUES
    ('Football'), ('Basket'), ('Tennis'), ('Baseball'), ('Golf'),
    ('Boxe'), ('Karaté'), ('Ski'), ('Surf'), ('Guitare');

-- Personnages
INSERT IGNORE INTO mots (mot) VALUES
    ('Robot'), ('Fantôme'), ('Monstre'), ('Zombie'), ('Fée'),
    ('Sorcière'), ('Vampire'), ('Pirate'), ('Cowboy'), ('Clown'),
    ('Père Noël'), ('Alien'), ('Licorne'), ('Sirène'), ('Astronaute');
