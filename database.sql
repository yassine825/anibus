-- ============================================================
--  MediRDV — Base de données complète
--  Importez ce fichier dans phpMyAdmin ou MySQL CLI
--  mysql -u root -p < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS medirdv CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE medirdv;

-- ─── TABLE : patients ───────────────────────────────────────
CREATE TABLE patients (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    prenom      VARCHAR(100) NOT NULL,
    nom         VARCHAR(100) NOT NULL,
    email       VARCHAR(180) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    telephone   VARCHAR(20),
    date_naissance DATE,
    sexe        ENUM('homme','femme','autre'),
    adresse     VARCHAR(255),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── TABLE : médecins ───────────────────────────────────────
CREATE TABLE medecins (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    prenom       VARCHAR(100) NOT NULL,
    nom          VARCHAR(100) NOT NULL,
    email        VARCHAR(180) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    telephone    VARCHAR(20),
    specialite   VARCHAR(100) NOT NULL,
    ville        VARCHAR(100) NOT NULL,
    adresse      VARCHAR(255),
    tarif        DECIMAL(8,2) DEFAULT 0,
    description  TEXT,
    photo        VARCHAR(255),
    note_moyenne DECIMAL(3,2) DEFAULT 0,
    nb_avis      INT DEFAULT 0,
    disponible   TINYINT(1) DEFAULT 1,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── TABLE : disponibilités des médecins ────────────────────
CREATE TABLE disponibilites (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    medecin_id INT NOT NULL,
    jour       ENUM('lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche') NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin   TIME NOT NULL,
    actif       TINYINT(1) DEFAULT 1,
    FOREIGN KEY (medecin_id) REFERENCES medecins(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── TABLE : rendez-vous ────────────────────────────────────
CREATE TABLE rendez_vous (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    patient_id  INT NOT NULL,
    medecin_id  INT NOT NULL,
    date_rdv    DATE NOT NULL,
    heure_rdv   TIME NOT NULL,
    motif       VARCHAR(255),
    statut      ENUM('en_attente','confirme','annule','termine') DEFAULT 'en_attente',
    notes_medecin TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (medecin_id) REFERENCES medecins(id) ON DELETE CASCADE,
    UNIQUE KEY unique_rdv (medecin_id, date_rdv, heure_rdv)
) ENGINE=InnoDB;

-- ─── TABLE : avis / notes ───────────────────────────────────
CREATE TABLE avis (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    medecin_id INT NOT NULL,
    rdv_id     INT,
    note       TINYINT NOT NULL CHECK (note BETWEEN 1 AND 5),
    commentaire TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (medecin_id) REFERENCES medecins(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
--  DONNÉES DE DÉMONSTRATION
-- ============================================================

-- Patients (mot de passe : "demo1234" hashé)
INSERT INTO patients (prenom, nom, email, mot_de_passe, telephone, sexe) VALUES
('Ahmed',   'Ben Ali',    'ahmed@demo.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+216 22 111 222', 'homme'),
('Sonia',   'Gharbi',     'sonia@demo.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+216 22 333 444', 'femme'),
('Mehdi',   'Khelifi',    'mehdi@demo.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+216 22 555 666', 'homme');

-- Médecins (mot de passe : "demo1234")
INSERT INTO medecins (prenom, nom, email, mot_de_passe, telephone, specialite, ville, adresse, tarif, note_moyenne, nb_avis, disponible) VALUES
('Sarah',   'Mansour',  'sarah@demo.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+216 55 001 001', 'Cardiologue',    'Tunis',   '12 Rue Ibn Khaldoun, Tunis',          80.00, 4.8, 124, 1),
('Karim',   'Bouazizi', 'karim@demo.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+216 55 002 002', 'Pédiatre',       'Tunis',   '5 Avenue Habib Bourguiba, Tunis',     50.00, 4.9, 212, 1),
('Leila',   'Trabelsi', 'leila@demo.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+216 55 003 003', 'Dermatologue',   'Sfax',    '33 Rue de la République, Sfax',       60.00, 4.7, 98,  1),
('Mohamed', 'Gharbi',   'med@demo.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+216 55 004 004', 'Généraliste',    'Sousse',  '8 Avenue du 7 Novembre, Sousse',      35.00, 4.6, 310, 1),
('Fatma',   'Jlassi',   'fatma@demo.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+216 55 005 005', 'Gynécologue',    'Tunis',   '20 Rue Charles de Gaulle, Tunis',     70.00, 4.9, 176, 1),
('Riadh',   'Khelil',   'riadh@demo.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+216 55 006 006', 'Ophtalmologue',  'Bizerte', '15 Rue de Carthage, Bizerte',         65.00, 4.5, 88,  1),
('Ines',    'Hamdi',    'ines@demo.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+216 55 007 007', 'Neurologue',     'Tunis',   '7 Rue de Marseille, Tunis',           90.00, 4.7, 65,  1),
('Tarek',   'Saidi',    'tarek@demo.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+216 55 008 008', 'Orthopédiste',   'Sousse',  '2 Boulevard du Maghreb, Sousse',      75.00, 4.4, 142, 1);

-- Disponibilités
INSERT INTO disponibilites (medecin_id, jour, heure_debut, heure_fin) VALUES
(1,'lundi','08:00','12:00'),(1,'lundi','14:00','18:00'),
(1,'mardi','08:00','12:00'),(1,'jeudi','08:00','12:00'),(1,'vendredi','14:00','18:00'),
(2,'lundi','09:00','13:00'),(2,'mercredi','09:00','13:00'),(2,'vendredi','09:00','13:00'),
(3,'mardi','08:00','17:00'),(3,'jeudi','08:00','17:00'),
(4,'lundi','08:00','18:00'),(4,'mardi','08:00','18:00'),(4,'mercredi','08:00','18:00'),(4,'jeudi','08:00','18:00'),(4,'vendredi','08:00','18:00'),
(5,'mardi','09:00','13:00'),(5,'mercredi','14:00','18:00'),(5,'jeudi','09:00','13:00'),
(6,'lundi','10:00','16:00'),(6,'mercredi','10:00','16:00'),(6,'vendredi','10:00','16:00'),
(7,'lundi','08:00','12:00'),(7,'jeudi','14:00','18:00'),
(8,'mardi','08:00','12:00'),(8,'vendredi','08:00','12:00');

-- Rendez-vous de démo
INSERT INTO rendez_vous (patient_id, medecin_id, date_rdv, heure_rdv, motif, statut) VALUES
(1, 1, DATE_ADD(CURDATE(), INTERVAL 3 DAY),  '09:00', 'Consultation cardiaque', 'confirme'),
(1, 2, DATE_ADD(CURDATE(), INTERVAL 7 DAY),  '10:30', 'Visite pédiatrique',     'en_attente'),
(2, 5, DATE_ADD(CURDATE(), INTERVAL 5 DAY),  '14:00', 'Consultation gynéco',    'confirme'),
(3, 4, DATE_ADD(CURDATE(), INTERVAL 1 DAY),  '08:30', 'Fièvre et fatigue',       'en_attente');
