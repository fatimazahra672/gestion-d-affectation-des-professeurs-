<?php
// 1. Configuration et initialisation
if (!file_exists(__DIR__ . '/config.php')) {
    die("Fichier de configuration manquant");
}
require __DIR__ . '/config.php';

// 2. Gestion de session
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login_coordinateur.php');
    exit;
}

// 3. Protection CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// 4. Variables initiales
$error = null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['success']);
$affectation = null;
$professeurs = [];
$unites_enseignement = [];

// 5. Vérification de l'ID dans l'URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: affectation_ue.php');
    exit;
}

$affectation_id = (int)$_GET['id'];

try {
    $pdo = new PDO(
        'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Vérification des colonnes existantes dans la table affectations
    $columns = $pdo->query("SHOW COLUMNS FROM affectations")->fetchAll();
    $columnNames = array_column($columns, 'Field');
    
    // Construction dynamique de la requête SELECT
    $selectFields = ['a.id', 'a.professeur_id'];
    
    // Gestion des différentes colonnes possibles pour l'UE
    if (in_array('ue_id', $columnNames)) {
        $selectFields[] = 'a.ue_id';
    } elseif (in_array('id_ue', $columnNames)) {
        $selectFields[] = 'a.id_ue as ue_id';
    } else {
        // Si aucune colonne UE n'existe, créer la colonne
        $pdo->exec("ALTER TABLE affectations ADD COLUMN ue_id INT NOT NULL AFTER professeur_id");
        $selectFields[] = 'a.ue_id';
    }
    
    // Ajout des autres colonnes si elles existent
    if (in_array('annee', $columnNames)) {
        $selectFields[] = 'a.annee';
    }
    
    if (in_array('semestre', $columnNames)) {
        $selectFields[] = 'a.semestre';
    }
    
    if (in_array('date_debut', $columnNames)) {
        $selectFields[] = 'a.date_debut';
    }
    
    if (in_array('date_fin', $columnNames)) {
        $selectFields[] = 'a.date_fin';
    }
    
    if (in_array('heures', $columnNames)) {
        $selectFields[] = 'a.heures';
    }
    
    if (in_array('specialite_id', $columnNames)) {
        $selectFields[] = 'a.specialite_id';
    }

    // Récupération de l'affectation à éditer
    $stmt = $pdo->prepare("
        SELECT " . implode(', ', $selectFields) . "
        FROM affectations a
        WHERE a.id = ?
    ");
    $stmt->execute([$affectation_id]);
    $affectation = $stmt->fetch();

    if (!$affectation) {
        header('Location: affectation_ue.php');
        exit;
    }

    // Récupération des professeurs
    // Vérifier si la table utilisateurs existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'utilisateurs'");
    $utilisateursExists = $stmt->rowCount() > 0;

    if ($utilisateursExists) {
        $professeurs = $pdo->query("
            SELECT id, CONCAT(nom, ' ', prenom) as nom_complet
            FROM utilisateurs
            WHERE type_utilisateur = 'enseignant'
            ORDER BY nom, prenom
        ")->fetchAll();
    } else {
        $professeurs = $pdo->query("
            SELECT id, CONCAT(nom, ' ', prenom) as nom_complet
            FROM professeurs
            ORDER BY nom, prenom
        ")->fetchAll();
    }

    // Récupération des unités d'enseignement
    // Vérifier quelle table UE existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'unites_enseignement'");
    $ueTableExists = $stmt->rowCount() > 0;

    if ($ueTableExists) {
        $ueTableName = 'unites_enseignement';
    } else {
        $stmt = $pdo->query("SHOW TABLES LIKE 'unites_enseignements'");
        $uesTableExists = $stmt->rowCount() > 0;
        $ueTableName = $uesTableExists ? 'unites_enseignements' : 'unites_enseignement';
    }

    // Requête de base ultra-simple
    $query = "
        SELECT id_ue as id, 
               CONCAT('UE', id_ue, ' - ', filiere) as nom_complet,
               CONCAT('UE', id_ue) as code_ue
        FROM $ueTableName
        ORDER BY id_ue
    ";

    try {
        // Vérifier si la table matieres existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'matieres'");
        $matieresExists = $stmt->rowCount() > 0;

        // Vérifier si la colonne id_matiere existe dans la table UE
        $stmt = $pdo->query("SHOW COLUMNS FROM $ueTableName LIKE 'id_matiere'");
        $idMatiereExists = $stmt->rowCount() > 0;

        if ($matieresExists && $idMatiereExists) {
            $query = "
                SELECT ue.id_ue as id,
                       CONCAT(m.code, ' - ', m.nom, ' (', ue.filiere, ')') as nom_complet,
                       m.code as code_ue
                FROM $ueTableName ue
                JOIN matieres m ON ue.id_matiere = m.id_matiere
                ORDER BY m.code
            ";
        }
        
        $unites_enseignement = $pdo->query($query)->fetchAll();
    } catch (PDOException $e) {
        // Fallback si la requête échoue
        $query = "SELECT id_ue as id, filiere as nom_complet FROM $ueTableName ORDER BY id_ue";
        $unites_enseignement = $pdo->query($query)->fetchAll();
        $error = "Erreur lors du chargement des UE, affichage simplifié";
    }

    // Traitement du formulaire de mise à jour
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_affectation'])) {
        try {
            // Validation CSRF
            if (empty($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
                throw new Exception("Erreur de sécurité: Token invalide");
            }

            // Validation des données
            $required = [
                'professeur_id' => 'Professeur',
                'ue_id' => 'Unité d\'enseignement'
            ];
            
            $errors = [];
            foreach ($required as $field => $name) {
                if (empty($_POST[$field])) {
                    $errors[] = "Le champ '$name' est requis";
                }
            }

            if (!empty($errors)) {
                throw new Exception(implode("<br>", $errors));
            }

            // Construction dynamique de la requête UPDATE
            $updateFields = [
                'professeur_id = :professeur_id',
                'ue_id = :ue_id'
            ];
            
            $data = [
                'id' => $affectation_id,
                'professeur_id' => (int)$_POST['professeur_id'],
                'ue_id' => (int)$_POST['ue_id']
            ];

            // Ajout des autres champs s'ils existent dans la table
            if (in_array('annee', $columnNames)) {
                $updateFields[] = 'annee = :annee';
                $data['annee'] = (int)($_POST['annee'] ?? date('Y'));
            }
            
            if (in_array('semestre', $columnNames)) {
                $updateFields[] = 'semestre = :semestre';
                $data['semestre'] = (int)($_POST['semestre'] ?? 1);
            }
            
            if (in_array('date_debut', $columnNames)) {
                $updateFields[] = 'date_debut = :date_debut';
                $data['date_debut'] = $_POST['date_debut'] ?? date('Y-m-d');
            }
            
            if (in_array('date_fin', $columnNames)) {
                $updateFields[] = 'date_fin = :date_fin';
                $data['date_fin'] = $_POST['date_fin'] ?? null;
            }
            
            if (in_array('heures', $columnNames)) {
                $updateFields[] = 'heures = :heures';
                $data['heures'] = (int)($_POST['heures'] ?? 30);
            }
            
            if (in_array('specialite_id', $columnNames)) {
                $updateFields[] = 'specialite_id = :specialite_id';
                // Récupérer la première spécialité disponible
                $stmt = $pdo->query("SELECT id_specialite FROM specialite LIMIT 1");
                $defaultSpecialiteId = $stmt->fetchColumn();
                $data['specialite_id'] = $defaultSpecialiteId ?? 1;
            }

            $stmt = $pdo->prepare("
                UPDATE affectations 
                SET " . implode(', ', $updateFields) . "
                WHERE id = :id
            ");
            
            if ($stmt->execute($data)) {
                $_SESSION['success'] = "Affectation modifiée avec succès!";
                header("Location: affectation_ue.php");
                exit;
            }

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

} catch (PDOException $e) {
    $error = "Erreur de base de données: " . $e->getMessage();
    error_log("DB Error: " . $e->getMessage());
}

function sanitize($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Éditer Affectation UE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1E90FF;
            --primary-magenta: #FF00FF;
            --blue-transparent: rgba(30, 144, 255, 0.3);
            --dark-bg: #0a192f;
        }

        body {
            background: linear-gradient(rgba(10, 25, 47, 0.85), rgba(108, 27, 145, 0.85));
            color: white;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .card {
            background: rgba(10, 25, 47, 0.9);
            border: 2px solid var(--primary-blue);
            border-radius: 10px;
            box-shadow: 0 5px 15px var(--blue-transparent);
        }

        .card-header {
            background: linear-gradient(90deg, var(--primary-blue) 0%, var(--dark-bg) 100%);
            border-bottom: 2px solid var(--primary-magenta);
        }

        .form-control, .form-select {
            background-color: rgba(10, 25, 47, 0.7);
            color: white;
            border: 1px solid var(--primary-blue);
        }

        .form-control:focus, .form-select:focus {
            background-color: rgba(10, 25, 47, 0.9);
            color: white;
            border-color: var(--primary-magenta);
            box-shadow: 0 0 0 0.25rem rgba(255, 0, 255, 0.25);
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="m-0"><i class="fas fa-edit me-2"></i> Éditer Affectation UE</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= sanitize($error) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= sanitize($success) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf_token) ?>">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Professeur *</label>
                                    <select class="form-select" name="professeur_id" required>
                                        <option value="">Sélectionner...</option>
                                        <?php foreach ($professeurs as $prof): ?>
                                            <option value="<?= $prof['id'] ?>" <?= ($prof['id'] == $affectation['professeur_id']) ? 'selected' : '' ?>>
                                                <?= sanitize($prof['nom_complet']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Unité d'enseignement *</label>
                                    <select class="form-select" name="ue_id" required>
                                        <option value="">Sélectionner...</option>
                                        <?php foreach ($unites_enseignement as $ue): ?>
                                            <option value="<?= $ue['id'] ?>" <?= ($ue['id'] == $affectation['ue_id']) ? 'selected' : '' ?>>
                                                <?= sanitize($ue['nom_complet']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <?php if (in_array('annee', $columnNames)): ?>
                                <div class="col-md-4">
                                    <label class="form-label">Année</label>
                                    <input type="number" class="form-control" name="annee" 
                                           value="<?= sanitize($affectation['annee'] ?? date('Y')) ?>">
                                </div>
                                <?php endif; ?>
                                
                                <?php if (in_array('semestre', $columnNames)): ?>
                                <div class="col-md-4">
                                    <label class="form-label">Semestre</label>
                                    <select class="form-select" name="semestre">
                                        <option value="1" <?= ($affectation['semestre'] ?? 1) == 1 ? 'selected' : '' ?>>Semestre 1</option>
                                        <option value="2" <?= ($affectation['semestre'] ?? 1) == 2 ? 'selected' : '' ?>>Semestre 2</option>
                                    </select>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (in_array('heures', $columnNames)): ?>
                                <div class="col-md-4">
                                    <label class="form-label">Heures</label>
                                    <input type="number" class="form-control" name="heures" 
                                           value="<?= sanitize($affectation['heures'] ?? 30) ?>">
                                </div>
                                <?php endif; ?>
                                
                                <?php if (in_array('date_debut', $columnNames)): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Date de début</label>
                                    <input type="date" class="form-control" name="date_debut" 
                                           value="<?= !empty($affectation['date_debut']) ? sanitize(date('Y-m-d', strtotime($affectation['date_debut']))) : '' ?>">
                                </div>
                                <?php endif; ?>
                                
                                <?php if (in_array('date_fin', $columnNames)): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Date de fin</label>
                                    <input type="date" class="form-control" name="date_fin" 
                                           value="<?= !empty($affectation['date_fin']) ? sanitize(date('Y-m-d', strtotime($affectation['date_fin']))) : '' ?>">
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mt-4 d-flex justify-content-between">
                                <a href="affectation_ue.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i> Retour
                                </a>
                                <button type="submit" name="modifier_affectation" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Enregistrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>