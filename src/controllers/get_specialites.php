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
    // Écrire également dans un fichier de log pour le débogage
    file_put_contents('specialites_debug.log', date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
}

// Fonction pour afficher une réponse JSON et terminer le script
function json_response($data) {
    echo json_encode($data);
    exit;
}

try {
    // Inclure le fichier de configuration
    require_once 'config.php';

    // Afficher les paramètres de connexion (à supprimer en production)
    debug_log("Paramètres de connexion: " . DB_HOST . ", " . DB_NAME . ", " . DB_USER);

    // Vérifier si l'ID du département est fourni
    if (!isset($_GET['departement_id']) || empty($_GET['departement_id'])) {
        debug_log("ID de département non fourni");
        json_response(['error' => 'ID de département non fourni', 'success' => false, 'specialites' => []]);
    }

    $departement_id = (int)$_GET['departement_id'];
    debug_log("Recherche des spécialités pour le département ID: " . $departement_id);

    // Données statiques pour les spécialités
    $specialites_statiques = [
        1 => [ // Informatique/Mathématiques
            ['id_specialite' => 1, 'nom_specialite' => 'Développement logiciel'],
            ['id_specialite' => 2, 'nom_specialite' => 'Intelligence Artificielle'],
            ['id_specialite' => 3, 'nom_specialite' => 'Mathématiques Appliquées'],
            ['id_specialite' => 4, 'nom_specialite' => 'Développement Web'],
            ['id_specialite' => 5, 'nom_specialite' => 'Base de Données'],
            ['id_specialite' => 6, 'nom_specialite' => 'Réseaux']
        ],
        2 => [ // Physique
            ['id_specialite' => 7, 'nom_specialite' => 'Physique Fondamentale'],
            ['id_specialite' => 8, 'nom_specialite' => 'Physique Appliquée'],
            ['id_specialite' => 9, 'nom_specialite' => 'Électronique'],
            ['id_specialite' => 10, 'nom_specialite' => 'Physique Nucléaire'],
            ['id_specialite' => 11, 'nom_specialite' => 'Optique et Photonique'],
            ['id_specialite' => 12, 'nom_specialite' => 'Physique des Matériaux']
        ],
        3 => [ // Chimie
            ['id_specialite' => 13, 'nom_specialite' => 'Énergie et Environnement'],
            ['id_specialite' => 14, 'nom_specialite' => 'Chimie Organique'],
            ['id_specialite' => 15, 'nom_specialite' => 'Chimie Inorganique'],
            ['id_specialite' => 16, 'nom_specialite' => 'Biochimie']
        ],
        4 => [ // Biologie
            ['id_specialite' => 17, 'nom_specialite' => 'Microbiologie'],
            ['id_specialite' => 18, 'nom_specialite' => 'Génétique'],
            ['id_specialite' => 19, 'nom_specialite' => 'Écologie']
        ]
    ];

    // Retourner directement les données statiques
    if (isset($specialites_statiques[$departement_id])) {
        debug_log("Utilisation des données statiques pour le département ID: " . $departement_id);
        json_response([
            'success' => true,
            'specialites' => $specialites_statiques[$departement_id],
            'departement_id' => $departement_id,
            'source' => 'static_data'
        ]);
    }

    // Connexion à la base de données
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    debug_log("Connexion à la base de données réussie");

    // Vérifier si la table specialite existe
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    debug_log("Tables disponibles: " . implode(", ", $tables));

    // Si la table specialite n'existe pas, essayer avec d'autres noms possibles
    $table_name = 'specialite';
    if (!in_array('specialite', $tables)) {
        debug_log("La table 'specialite' n'existe pas, recherche d'alternatives");

        $alternatives = ['specialites', 'specialite', 'specialités', 'spécialité', 'spécialités'];
        foreach ($alternatives as $alt) {
            if (in_array($alt, $tables)) {
                $table_name = $alt;
                debug_log("Table alternative trouvée: " . $alt);
                break;
            }
        }

        // Si aucune alternative n'est trouvée, utiliser les données statiques
        if ($table_name === 'specialite' && !in_array($table_name, $tables)) {
            debug_log("Aucune table de spécialités trouvée, utilisation des données statiques");

            // Données statiques pour les spécialités
            $specialites_statiques = [
                1 => [ // Informatique/Mathématiques
                    ['id_specialite' => 1, 'nom_specialite' => 'Développement logiciel'],
                    ['id_specialite' => 2, 'nom_specialite' => 'Intelligence Artificielle'],
                    ['id_specialite' => 3, 'nom_specialite' => 'Mathématiques Appliquées'],
                    ['id_specialite' => 4, 'nom_specialite' => 'Développement Web'],
                    ['id_specialite' => 5, 'nom_specialite' => 'Base de Données'],
                    ['id_specialite' => 6, 'nom_specialite' => 'Réseaux']
                ],
                2 => [ // Physique
                    ['id_specialite' => 7, 'nom_specialite' => 'Physique Fondamentale'],
                    ['id_specialite' => 8, 'nom_specialite' => 'Physique Appliquée'],
                    ['id_specialite' => 9, 'nom_specialite' => 'Électronique'],
                    ['id_specialite' => 10, 'nom_specialite' => 'Physique Nucléaire']
                ],
                3 => [ // Chimie
                    ['id_specialite' => 11, 'nom_specialite' => 'Chimie Organique'],
                    ['id_specialite' => 12, 'nom_specialite' => 'Chimie Inorganique'],
                    ['id_specialite' => 13, 'nom_specialite' => 'Biochimie']
                ],
                4 => [ // Biologie
                    ['id_specialite' => 14, 'nom_specialite' => 'Microbiologie'],
                    ['id_specialite' => 15, 'nom_specialite' => 'Génétique'],
                    ['id_specialite' => 16, 'nom_specialite' => 'Écologie']
                ]
            ];

            // Retourner les spécialités statiques pour le département demandé
            if (isset($specialites_statiques[$departement_id])) {
                $response = [
                    'success' => true,
                    'specialites' => $specialites_statiques[$departement_id],
                    'departement_id' => $departement_id,
                    'source' => 'static_data',
                    'message' => 'Données statiques utilisées car aucune table de spécialités n\'a été trouvée'
                ];
                debug_log("Réponse avec données statiques: " . json_encode($response));
                json_response($response);
            } else {
                debug_log("Aucune spécialité statique trouvée pour le département ID: " . $departement_id);
                json_response([
                    'error' => 'Aucune spécialité trouvée pour ce département',
                    'success' => false,
                    'specialites' => [],
                    'departement_id' => $departement_id,
                    'tables_disponibles' => $tables
                ]);
            }
        }
    }

    // Vérifier la structure de la table specialite
    try {
        $columns = $pdo->query("DESCRIBE $table_name")->fetchAll();
        debug_log("Structure de la table $table_name: " . print_r($columns, true));

        // Définir les colonnes par défaut
        $id_column = 'id_specialite';
        $nom_column = 'nom_specialite';
        $dept_column = 'id_departement';

        // Vérifier si les colonnes par défaut existent
        $id_exists = false;
        $nom_exists = false;
        $dept_exists = false;

        foreach ($columns as $column) {
            $field = $column['Field'];
            if ($field === $id_column) $id_exists = true;
            if ($field === $nom_column) $nom_exists = true;
            if ($field === $dept_column) $dept_exists = true;
        }

        debug_log("Vérification des colonnes par défaut - ID: " . ($id_exists ? "existe" : "n'existe pas") .
                 ", Nom: " . ($nom_exists ? "existe" : "n'existe pas") .
                 ", Département: " . ($dept_exists ? "existe" : "n'existe pas"));

        // Si les colonnes par défaut n'existent pas, chercher des alternatives
        if (!$id_exists || !$nom_exists || !$dept_exists) {
            debug_log("Recherche de colonnes alternatives...");
            $id_column = null;
            $nom_column = null;
            $dept_column = null;

            // Rechercher les colonnes qui pourraient contenir les informations nécessaires
            foreach ($columns as $column) {
                $field = strtolower($column['Field']);

                // Colonne ID
                if ($field === 'id_specialite' || $field === 'id' || strpos($field, 'id_') === 0) {
                    $id_column = $column['Field'];
                    debug_log("Colonne ID alternative trouvée: " . $id_column);
                }

                // Colonne Nom
                if ($field === 'nom_specialite' || $field === 'nom' || strpos($field, 'nom') !== false || strpos($field, 'libelle') !== false) {
                    $nom_column = $column['Field'];
                    debug_log("Colonne Nom alternative trouvée: " . $nom_column);
                }

                // Colonne Département
                if ($field === 'id_departement' || $field === 'departement_id' || $field === 'id_dept' || $field === 'dept_id' || $field === 'id_unite' || $field === 'unite_id') {
                    $dept_column = $column['Field'];
                    debug_log("Colonne Département alternative trouvée: " . $dept_column);
                }
            }
        }

        if (!$id_column || !$nom_column || !$dept_column) {
            debug_log("La table $table_name n'a pas toutes les colonnes nécessaires");
            debug_log("Colonnes trouvées - ID: " . ($id_column ?? 'non trouvée') . ", Nom: " . ($nom_column ?? 'non trouvée') . ", Département: " . ($dept_column ?? 'non trouvée'));

            // Utiliser les données statiques comme solution de secours
            $specialites_statiques = [
                1 => [ // Informatique/Mathématiques
                    ['id_specialite' => 1, 'nom_specialite' => 'Développement logiciel'],
                    ['id_specialite' => 2, 'nom_specialite' => 'Intelligence Artificielle'],
                    ['id_specialite' => 3, 'nom_specialite' => 'Mathématiques Appliquées'],
                    ['id_specialite' => 4, 'nom_specialite' => 'Développement Web'],
                    ['id_specialite' => 5, 'nom_specialite' => 'Base de Données'],
                    ['id_specialite' => 6, 'nom_specialite' => 'Réseaux']
                ],
                2 => [ // Physique
                    ['id_specialite' => 7, 'nom_specialite' => 'Physique Fondamentale'],
                    ['id_specialite' => 8, 'nom_specialite' => 'Physique Appliquée'],
                    ['id_specialite' => 9, 'nom_specialite' => 'Électronique'],
                    ['id_specialite' => 10, 'nom_specialite' => 'Physique Nucléaire']
                ],
                3 => [ // Chimie
                    ['id_specialite' => 11, 'nom_specialite' => 'Chimie Organique'],
                    ['id_specialite' => 12, 'nom_specialite' => 'Chimie Inorganique'],
                    ['id_specialite' => 13, 'nom_specialite' => 'Biochimie']
                ],
                4 => [ // Biologie
                    ['id_specialite' => 14, 'nom_specialite' => 'Microbiologie'],
                    ['id_specialite' => 15, 'nom_specialite' => 'Génétique'],
                    ['id_specialite' => 16, 'nom_specialite' => 'Écologie']
                ]
            ];

            if (isset($specialites_statiques[$departement_id])) {
                $response = [
                    'success' => true,
                    'specialites' => $specialites_statiques[$departement_id],
                    'departement_id' => $departement_id,
                    'source' => 'static_data',
                    'message' => 'Données statiques utilisées car la structure de la table est incorrecte',
                    'columns_found' => [
                        'id' => $id_column,
                        'nom' => $nom_column,
                        'departement' => $dept_column
                    ],
                    'all_columns' => array_column($columns, 'Field')
                ];
                debug_log("Réponse avec données statiques: " . json_encode($response));
                json_response($response);
            } else {
                debug_log("Aucune spécialité statique trouvée pour le département ID: " . $departement_id);
                json_response([
                    'error' => 'Structure de table incorrecte et aucune donnée statique disponible',
                    'success' => false,
                    'specialites' => [],
                    'departement_id' => $departement_id,
                    'columns_found' => [
                        'id' => $id_column,
                        'nom' => $nom_column,
                        'departement' => $dept_column
                    ],
                    'all_columns' => array_column($columns, 'Field')
                ]);
            }
        }

        // Récupérer les spécialités pour le département spécifié
        $query = "SELECT $id_column as id_specialite, $nom_column as nom_specialite FROM $table_name WHERE $dept_column = ? ORDER BY $nom_column";
        debug_log("Exécution de la requête: " . $query . " avec ID: " . $departement_id);

        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute([$departement_id]);
            $specialites = $stmt->fetchAll();
            debug_log("Nombre de spécialités trouvées: " . count($specialites));

            // Si aucune spécialité n'est trouvée, essayer une requête sans condition de département
            if (count($specialites) === 0) {
                debug_log("Aucune spécialité trouvée avec le département ID: $departement_id. Essai sans filtre...");
                $query_all = "SELECT $id_column as id_specialite, $nom_column as nom_specialite FROM $table_name ORDER BY $nom_column";
                debug_log("Exécution de la requête sans filtre: " . $query_all);

                $stmt_all = $pdo->query($query_all);
                $all_specialites = $stmt_all->fetchAll();
                debug_log("Nombre total de spécialités dans la table: " . count($all_specialites));

                // Vérifier si la colonne de département existe réellement dans les données
                if (count($all_specialites) > 0) {
                    $check_dept_query = "SELECT DISTINCT $dept_column FROM $table_name";
                    $dept_values = $pdo->query($check_dept_query)->fetchAll(PDO::FETCH_COLUMN);
                    debug_log("Valeurs distinctes de la colonne département: " . implode(", ", $dept_values));
                }
            }
        } catch (PDOException $e) {
            debug_log("Erreur lors de l'exécution de la requête: " . $e->getMessage());
            throw $e;
        }

    } catch (PDOException $e) {
        debug_log("Erreur lors de la vérification de la structure de la table: " . $e->getMessage());

        // Utiliser les données statiques comme solution de secours
        $specialites_statiques = [
            1 => [ // Informatique/Mathématiques
                ['id_specialite' => 1, 'nom_specialite' => 'Développement logiciel'],
                ['id_specialite' => 2, 'nom_specialite' => 'Intelligence Artificielle'],
                ['id_specialite' => 3, 'nom_specialite' => 'Mathématiques Appliquées'],
                ['id_specialite' => 4, 'nom_specialite' => 'Développement Web'],
                ['id_specialite' => 5, 'nom_specialite' => 'Base de Données'],
                ['id_specialite' => 6, 'nom_specialite' => 'Réseaux']
            ],
            2 => [ // Physique
                ['id_specialite' => 7, 'nom_specialite' => 'Physique Fondamentale'],
                ['id_specialite' => 8, 'nom_specialite' => 'Physique Appliquée'],
                ['id_specialite' => 9, 'nom_specialite' => 'Électronique'],
                ['id_specialite' => 10, 'nom_specialite' => 'Physique Nucléaire']
            ],
            3 => [ // Chimie
                ['id_specialite' => 11, 'nom_specialite' => 'Chimie Organique'],
                ['id_specialite' => 12, 'nom_specialite' => 'Chimie Inorganique'],
                ['id_specialite' => 13, 'nom_specialite' => 'Biochimie']
            ],
            4 => [ // Biologie
                ['id_specialite' => 14, 'nom_specialite' => 'Microbiologie'],
                ['id_specialite' => 15, 'nom_specialite' => 'Génétique'],
                ['id_specialite' => 16, 'nom_specialite' => 'Écologie']
            ]
        ];

        if (isset($specialites_statiques[$departement_id])) {
            $response = [
                'success' => true,
                'specialites' => $specialites_statiques[$departement_id],
                'departement_id' => $departement_id,
                'source' => 'static_data',
                'message' => 'Données statiques utilisées suite à une erreur: ' . $e->getMessage()
            ];
            debug_log("Réponse avec données statiques après erreur: " . json_encode($response));
            json_response($response);
        } else {
            debug_log("Aucune spécialité statique trouvée pour le département ID: " . $departement_id);
            json_response([
                'error' => 'Erreur lors de la récupération des spécialités: ' . $e->getMessage(),
                'success' => false,
                'specialites' => [],
                'departement_id' => $departement_id
            ]);
        }
    }

    debug_log("Spécialités trouvées: " . count($specialites));
    debug_log("Données des spécialités: " . print_r($specialites, true));

    // Retourner les spécialités au format JSON
    $response = ['success' => true, 'specialites' => $specialites, 'departement_id' => $departement_id];
    debug_log("Réponse: " . json_encode($response));
    json_response($response);

} catch (PDOException $e) {
    // En cas d'erreur, retourner un message d'erreur
    $error_message = 'Erreur de base de données: ' . $e->getMessage();
    debug_log($error_message);
    json_response([
        'error' => $error_message,
        'success' => false,
        'specialites' => [],
        'trace' => $e->getTraceAsString()
    ]);
} catch (Exception $e) {
    // En cas d'erreur générale, retourner un message d'erreur
    $error_message = 'Erreur: ' . $e->getMessage();
    debug_log($error_message);
    json_response([
        'error' => $error_message,
        'success' => false,
        'specialites' => [],
        'trace' => $e->getTraceAsString()
    ]);
}
