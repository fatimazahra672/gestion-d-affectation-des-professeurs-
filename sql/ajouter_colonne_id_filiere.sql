-- Ajouter une colonne id_filiere à la table utilisateurs si elle n'existe pas déjà
ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS id_filiere INT;

-- Ajouter une contrainte de clé étrangère pour lier utilisateurs.id_filiere à filiere.id_filiere
-- Vérifier d'abord si la contrainte existe déjà
SET @constraint_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'utilisateurs'
    AND CONSTRAINT_NAME = 'fk_utilisateur_filiere'
);

SET @sql = IF(@constraint_exists = 0,
    'ALTER TABLE utilisateurs ADD CONSTRAINT fk_utilisateur_filiere FOREIGN KEY (id_filiere) REFERENCES filiere(id_filiere) ON DELETE SET NULL',
    'SELECT "La contrainte existe déjà" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Créer un index pour améliorer les performances des requêtes si l'index n'existe pas déjà
SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'utilisateurs'
    AND INDEX_NAME = 'idx_utilisateurs_id_filiere'
);

SET @sql = IF(@index_exists = 0,
    'CREATE INDEX idx_utilisateurs_id_filiere ON utilisateurs(id_filiere)',
    'SELECT "L\'index existe déjà" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Mettre à jour les utilisateurs existants de type coordinateur avec une filière correspondant à leur département
UPDATE utilisateurs u
JOIN departement d ON u.id_departement = d.id_departement
LEFT JOIN filiere f ON d.id_departement = f.id_departement
SET u.id_filiere = f.id_filiere
WHERE u.type_utilisateur = 'coordinateur' 
AND u.id_filiere IS NULL 
AND f.id_filiere IS NOT NULL;
