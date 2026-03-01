<?php
// Ce script modifie la page gestion_coordinateur.php pour ajouter la sélection de filière
// lors de l'ajout ou de la modification d'un coordinateur

// Connexion à la base de données
require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Vérifier si la colonne id_filiere existe déjà dans la table utilisateurs
    $columns = $pdo->query("SHOW COLUMNS FROM utilisateurs LIKE 'id_filiere'")->fetchAll();
    if (count($columns) === 0) {
        // La colonne n'existe pas, on l'ajoute
        $pdo->exec("ALTER TABLE utilisateurs ADD COLUMN id_filiere INT");
        $pdo->exec("ALTER TABLE utilisateurs ADD CONSTRAINT fk_utilisateur_filiere FOREIGN KEY (id_filiere) REFERENCES filiere(id_filiere) ON DELETE SET NULL");
        $pdo->exec("CREATE INDEX idx_utilisateurs_id_filiere ON utilisateurs(id_filiere)");
        
        echo "La colonne id_filiere a été ajoutée à la table utilisateurs.<br>";
    } else {
        echo "La colonne id_filiere existe déjà dans la table utilisateurs.<br>";
    }

    // Mettre à jour les coordinateurs existants pour leur attribuer une filière par défaut
    // basée sur leur département
    $pdo->exec("
        UPDATE utilisateurs u
        JOIN departement d ON u.id_departement = d.id_departement
        LEFT JOIN filiere f ON d.id_departement = f.id_departement
        SET u.id_filiere = f.id_filiere
        WHERE u.type_utilisateur = 'coordinateur' 
        AND u.id_filiere IS NULL 
        AND f.id_filiere IS NOT NULL
    ");
    
    echo "Les coordinateurs existants ont été mis à jour avec leur filière correspondante.<br>";
    
    echo "Modification terminée avec succès. Vous pouvez maintenant utiliser la page gestion_coordinateur.php pour associer des filières aux coordinateurs.";

} catch (PDOException $e) {
    die("Erreur de base de données : " . htmlspecialchars($e->getMessage()));
}
?>
