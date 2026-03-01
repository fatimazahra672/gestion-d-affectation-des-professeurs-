# Liaison Coordinateur-Filière

Ce document explique comment lier un utilisateur de type "coordinateur" à une filière spécifique dans l'application de gestion des professeurs.

## Modifications effectuées

1. **Ajout de la colonne `id_filiere` à la table `utilisateurs`**
   - Une colonne `id_filiere` a été ajoutée à la table `utilisateurs`
   - Cette colonne est liée par une clé étrangère à la table `filiere`
   - Un index a été créé pour améliorer les performances des requêtes

2. **Mise à jour de la page `gestion_coordinateur.php`**
   - La requête de sélection des coordinateurs a été modifiée pour utiliser la colonne `id_filiere`
   - Les formulaires d'ajout et de modification ont été mis à jour pour inclure le champ `id_filiere`
   - Les requêtes d'insertion et de mise à jour ont été modifiées pour prendre en compte la colonne `id_filiere`

## Comment utiliser

1. Exécutez le script `modifier_gestion_coordinateur.php` pour ajouter la colonne `id_filiere` à la table `utilisateurs` si elle n'existe pas déjà.
2. Utilisez la page `gestion_coordinateur.php` pour ajouter ou modifier des coordinateurs en leur assignant une filière.

## Structure des tables

### Table `utilisateurs`
```sql
CREATE TABLE utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    type_utilisateur VARCHAR(50) NOT NULL,
    id_departement INT,
    id_specialite INT,
    id_filiere INT,
    FOREIGN KEY (id_departement) REFERENCES departement(id_departement),
    FOREIGN KEY (id_specialite) REFERENCES specialite(id_specialite),
    FOREIGN KEY (id_filiere) REFERENCES filiere(id_filiere)
);
```

### Table `filiere`
```sql
CREATE TABLE filiere (
    id_filiere INT AUTO_INCREMENT PRIMARY KEY,
    nom_filiere VARCHAR(255) NOT NULL,
    id_departement INT NOT NULL,
    FOREIGN KEY (id_departement) REFERENCES departement(id_departement)
);
```

## Requêtes SQL utiles

### Lister tous les coordinateurs avec leur filière
```sql
SELECT u.id, u.nom, u.prenom, u.email, f.nom_filiere, d.nom_departement
FROM utilisateurs u
JOIN departement d ON u.id_departement = d.id_departement
LEFT JOIN filiere f ON u.id_filiere = f.id_filiere
WHERE u.type_utilisateur = 'coordinateur'
ORDER BY u.nom, u.prenom;
```

### Mettre à jour la filière d'un coordinateur
```sql
UPDATE utilisateurs 
SET id_filiere = ? 
WHERE id = ? AND type_utilisateur = 'coordinateur';
```
