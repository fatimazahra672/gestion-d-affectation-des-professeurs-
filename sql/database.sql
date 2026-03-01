CREATE TABLE compte_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,                        -- Identifiant unique de la demande
    user_id INT NOT NULL,                                     -- Référence à l'utilisateur concerné
    request_type VARCHAR(50) NOT NULL,                        -- Type de demande (ex: 'modification', 'création', etc.)
    status ENUM('pending', 'approved', 'rejected')            -- État de la demande
           DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,           -- Date de création de la demande
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP 
               ON UPDATE CURRENT_TIMESTAMP,                   -- Mise à jour automatique à chaque modification
    FOREIGN KEY (user_id) REFERENCES utilisateurs(id)         -- Clé étrangère vers la table utilisateurs
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE activites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    description VARCHAR(255) NOT NULL,
    type ENUM('success', 'warning', 'danger', 'info') NOT NULL DEFAULT 'info',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE specialites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(255) NOT NULL,
    filiere_id INT,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id)
);
CREATE TABLE `affectations` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `professeur_id` INT NOT NULL,
  `specialite_id` INT NOT NULL,
  `date_debut` DATE NOT NULL,
  `date_fin` DATE,
  `heures` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`professeur_id`) REFERENCES `professeurs`(`id`),
  FOREIGN KEY (`specialite_id`) REFERENCES `specialites`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE professeurs 
ADD COLUMN type VARCHAR(20) NOT NULL DEFAULT 'permanent'
COMMENT 'Types possibles: permanent, vacataire';

CREATE TABLE vacataires (
    id_vacataire INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    specialite VARCHAR(100),
    statut VARCHAR(100) DEFAULT 'Vacataire'
) ENGINE=InnoDB;

ALTER TABLE affectations 
ADD COLUMN departement_id INT NOT NULL 
AFTER specialite_id;

CREATE TABLE responsabilites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    professeur_id INT,
    role ENUM('Coordinateur UE', 'Responsable Département'),
    date_debut DATE,
    date_fin DATE,
    FOREIGN KEY (professeur_id) REFERENCES professeurs(id)
);

ALTER TABLE responsabilites 
CHANGE professeur_id utilisateur_id INT;

ALTER TABLE responsabilites
ADD COLUMN departement_id INT AFTER role,
ADD COLUMN specialite_id INT AFTER departement_id,
ADD COLUMN status ENUM('active', 'termine') DEFAULT 'active' AFTER date_fin;

ALTER TABLE responsabilites
DROP FOREIGN KEY [nom_de_votre_contrainte], -- À remplacer par le vrai nom
ADD FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
ADD FOREIGN KEY (departement_id) REFERENCES departements(id) ON DELETE SET NULL,
ADD FOREIGN KEY (specialite_id) REFERENCES specialites(id) ON DELETE SET NULL;
-- Pour les coordinateurs UE existants
UPDATE responsabilites 
SET specialite_id = (
    SELECT id 
    FROM specialites 
    WHERE nom = 'Informatique'
    LIMIT 1
)
WHERE role = 'Coordinateur UE';

-- Pour les responsables de département
UPDATE responsabilites 
SET departement_id = (
    SELECT departement_id 
    FROM departements 
    WHERE nom = 'Mathématiques'
    LIMIT 1
)
WHERE role = 'Responsable Département';
-- Ajoutez la colonne departement_id
ALTER TABLE specialites
ADD departement_id INT;

-- Liez-la à la table departements
ALTER TABLE specialites
ADD FOREIGN KEY (departement_id) REFERENCES departements(departement_id);
-- Création de la table unites_enseignement
CREATE TABLE unites_enseignement (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code_ue VARCHAR(50) NOT NULL UNIQUE,
    nom VARCHAR(255) NOT NULL,
    credit INT NOT NULL,
    volume_horaire DECIMAL(10,2) NOT NULL,
    semestre VARCHAR(20) NOT NULL,
    departement_id INT NOT NULL,
    FOREIGN KEY (departement_id) REFERENCES departements(departement_id)
);

-- Création de la table choix_professeurs
CREATE TABLE choix_professeurs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    professeur_id INT NOT NULL,
    ue_id INT NOT NULL,
    statut ENUM('en_attente', 'valide', 'rejete') DEFAULT 'en_attente',
    date_choix DATETIME DEFAULT CURRENT_TIMESTAMP,
    departement_id INT NOT NULL,
    FOREIGN KEY (professeur_id) REFERENCES professeurs(id),
    FOREIGN KEY (ue_id) REFERENCES unites_enseignement(id),
    FOREIGN KEY (departement_id) REFERENCES departements(departement_id)
);
INSERT INTO utilisateurs 
(email, mot_de_passe, role, date_creation) 
VALUES 
(
    'admin@ensah.ma', 
    '$2y$10$6ohZw99o1PFCtqHFsWTnrOiAI.SVDT8xU0JbmCBeV.1brbb0aImMK', 
    'admin', 
    NOW()
)
ALTER TABLE affectations
ADD CONSTRAINT fk_professeur
FOREIGN KEY (professor_id) REFERENCES professeurs(id);

ALTER TABLE affectations
ADD CONSTRAINT fk_ue
FOREIGN KEY (specialite_id) REFERENCES unites_enseignement(id);
ALTER TABLE professeurs 
ADD COLUMN heures_max INT DEFAULT 192 COMMENT 'Heures maximales annuelles pour les permanents',
ADD COLUMN heures_vacataire INT DEFAULT 64 COMMENT 'Heures maximales si vacataire';