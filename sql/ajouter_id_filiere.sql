-- Ajouter une colonne id_filiere à la table utilisateurs
ALTER TABLE utilisateurs ADD COLUMN id_filiere INT;

-- Ajouter une contrainte de clé étrangère pour lier utilisateurs.id_filiere à filiere.id_filiere
ALTER TABLE utilisateurs ADD CONSTRAINT fk_utilisateur_filiere FOREIGN KEY (id_filiere) REFERENCES filiere(id_filiere) ON DELETE SET NULL;

-- Mettre à jour les utilisateurs existants de type coordinateur avec une filière par défaut (exemple: id_filiere = 1)
-- Vous devrez ajuster cette requête en fonction de vos besoins spécifiques
UPDATE utilisateurs SET id_filiere = 1 WHERE type_utilisateur = 'coordinateur' AND id_filiere IS NULL;

-- Créer un index pour améliorer les performances des requêtes
CREATE INDEX idx_utilisateurs_id_filiere ON utilisateurs(id_filiere);
