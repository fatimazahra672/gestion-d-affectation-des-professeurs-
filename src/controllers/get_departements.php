<?php
// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Définir le type de contenu comme JSON
header('Content-Type: application/json');

// Fonction pour journaliser les messages de débogage
function debug_log($message) {
    error_log($message);
    // Afficher les messages de débogage dans la réponse JSON pour le débogage
    global $debug_messages;
    $debug_messages[] = $message;
}

// Fonction pour afficher une réponse JSON et terminer le script
function json_response($data) {
    echo json_encode($data);
    exit;
}

// Initialiser le tableau des messages de débogage
$debug_messages = [];

try {
    // Inclure le fichier de configuration
    require_once 'config.php';

    // Afficher les paramètres de connexion (à supprimer en production)
    debug_log("Paramètres de connexion: " . DB_HOST . ", " . DB_NAME . ", " . DB_USER);

    // Connexion à la base de données
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
        DB_USER,
        DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    debug_log("Connexion à la base de données réussie");

    // Vérifier si la table departement existe
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    debug_log("Tables disponibles: " . implode(", ", $tables));

    if (!in_array('departement', $tables)) {
        debug_log("La table 'departement' n'existe pas");
        json_response([
            'error' => 'La table departement n\'existe pas',
            'success' => false,
            'departements' => [],
            'tables_disponibles' => $tables
        ]);
    }

    // Vérifier la structure de la table departement
    $columns = $pdo->query("DESCRIBE departement")->fetchAll();
    debug_log("Structure de la table departement: " . print_r($columns, true));

    // Vérifier si les colonnes nécessaires existent
    $has_id_departement = false;
    $has_nom_departement = false;

    foreach ($columns as $column) {
        if ($column['Field'] === 'id_departement') $has_id_departement = true;
        if ($column['Field'] === 'nom_departement') $has_nom_departement = true;
    }

    if (!$has_id_departement || !$has_nom_departement) {
        debug_log("La table departement n'a pas toutes les colonnes nécessaires");
        json_response([
            'error' => 'Structure de table incorrecte',
            'success' => false,
            'departements' => [],
            'has_id_departement' => $has_id_departement,
            'has_nom_departement' => $has_nom_departement,
            'columns' => array_column($columns, 'Field')
        ]);
    }

    // Récupérer les départements
    $query = "SELECT id_departement as id, nom_departement as nom FROM departement ORDER BY nom_departement";
    debug_log("Exécution de la requête: " . $query);

    $stmt = $pdo->query($query);
    $departements = $stmt->fetchAll();

    debug_log("Départements trouvés: " . count($departements));
    debug_log("Données des départements: " . print_r($departements, true));

    // Retourner les départements au format JSON
    $response = [
        'success' => true,
        'departements' => $departements,
        'debug' => $debug_messages,
        'query' => $query,
        'tables' => $tables,
        'columns' => array_column($columns, 'Field')
    ];
    debug_log("Réponse: " . json_encode($response));
    json_response($response);

} catch (PDOException $e) {
    // En cas d'erreur, retourner un message d'erreur
    $error_message = 'Erreur de base de données: ' . $e->getMessage();
    debug_log($error_message);
    json_response([
        'error' => $error_message,
        'success' => false,
        'departements' => [],
        'debug' => $debug_messages,
        'trace' => $e->getTraceAsString()
    ]);
} catch (Exception $e) {
    // En cas d'erreur générale, retourner un message d'erreur
    $error_message = 'Erreur: ' . $e->getMessage();
    debug_log($error_message);
    json_response([
        'error' => $error_message,
        'success' => false,
        'departements' => [],
        'debug' => $debug_messages,
        'trace' => $e->getTraceAsString()
    ]);
}
