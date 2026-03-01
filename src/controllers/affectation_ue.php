<?php
// 1. Configuration et initialisation
if (!file_exists(__DIR__ . '/config.php')) {
    die("Fichier de configuration manquant");
}
require __DIR__ . '/config.php';

// 2. Gestion de session
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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

$affectations = [];
$professeurs = [];
$unites_enseignement = [];

// 5. Connexion et requêtes adaptées à votre structure
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

    // REQUÊTE PRINCIPALE SIMPLIFIÉE
    try {
        // Vérifier si la colonne annee existe dans la table affectations
        $stmt = $pdo->query("SHOW COLUMNS FROM affectations LIKE 'annee'");
        $anneeExists = $stmt->rowCount() > 0;

        // Vérifier si la colonne semestre existe dans la table affectations
        $stmt = $pdo->query("SHOW COLUMNS FROM affectations LIKE 'semestre'");
        $semestreExists = $stmt->rowCount() > 0;

        // Vérifier si la colonne date_debut existe dans la table affectations
        $stmt = $pdo->query("SHOW COLUMNS FROM affectations LIKE 'date_debut'");
        $dateDebutExists = $stmt->rowCount() > 0;

        // Vérifier si la colonne date_fin existe dans la table affectations
        $stmt = $pdo->query("SHOW COLUMNS FROM affectations LIKE 'date_fin'");
        $dateFinExists = $stmt->rowCount() > 0;

        // Vérifier si la colonne heures existe dans la table affectations
        $stmt = $pdo->query("SHOW COLUMNS FROM affectations LIKE 'heures'");
        $heuresExists = $stmt->rowCount() > 0;

        // Vérifier si la colonne code_ue existe dans la table unites_enseignements
        $stmt = $pdo->query("SHOW COLUMNS FROM unites_enseignements LIKE 'code_ue'");
        $codeUeExists = $stmt->rowCount() > 0;

        // Construire la requête en fonction des colonnes existantes
        $query = "
            SELECT a.id,
                   p.id as prof_id,
                   CONCAT(p.nom, ' ', p.prenom) as professeur,
                   ue.id_ue as ue_id,
                   ue.filiere as unite_enseignement";

        if ($codeUeExists) {
            $query .= ",\n                   ue.code_ue as code_ue";
        } else {
            $query .= ",\n                   CONCAT('UE', ue.id_ue) as code_ue";
        }

        if ($anneeExists) {
            $query .= ",\n                   a.annee";
        } else {
            $query .= ",\n                   " . date('Y') . " as annee";
        }

        if ($semestreExists) {
            $query .= ",\n                   a.semestre";
        } else {
            $query .= ",\n                   1 as semestre";
        }

        if ($dateDebutExists) {
            $query .= ",\n                   DATE_FORMAT(a.date_debut, '%d/%m/%Y') as date_debut_format";
        } else {
            $query .= ",\n                   DATE_FORMAT(NOW(), '%d/%m/%Y') as date_debut_format";
        }

        if ($dateFinExists) {
            $query .= ",\n                   IFNULL(DATE_FORMAT(a.date_fin, '%d/%m/%Y'), '-') as date_fin_format";
        } else {
            $query .= ",\n                   '-' as date_fin_format";
        }

        if ($heuresExists) {
            $query .= ",\n                   a.heures";
        } else {
            $query .= ",\n                   30 as heures";
        }

        // Vérifier si la colonne ue_id existe dans la table affectations
        $stmt = $pdo->query("SHOW COLUMNS FROM affectations LIKE 'ue_id'");
        $ueIdExists = $stmt->rowCount() > 0;

        // Vérifier si la colonne id_ue existe dans la table affectations
        $stmt = $pdo->query("SHOW COLUMNS FROM affectations LIKE 'id_ue'");
        $idUeExists = $stmt->rowCount() > 0;

        // Vérifier si la table utilisateurs existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'utilisateurs'");
        $utilisateursExists = $stmt->rowCount() > 0;

        if ($utilisateursExists) {
            // Utiliser la table utilisateurs
            if ($ueIdExists) {
                $query .= "\n            FROM affectations a
                JOIN utilisateurs p ON a.professeur_id = p.id AND p.type_utilisateur = 'enseignant'
                JOIN unites_enseignements ue ON a.ue_id = ue.id_ue";
            } else if ($idUeExists) {
                $query .= "\n            FROM affectations a
                JOIN utilisateurs p ON a.professeur_id = p.id AND p.type_utilisateur = 'enseignant'
                JOIN unites_enseignements ue ON a.id_ue = ue.id_ue";
            } else {
                // Si aucune des colonnes n'existe, utiliser une jointure simplifiée
                $query .= "\n            FROM affectations a
                JOIN utilisateurs p ON a.professeur_id = p.id AND p.type_utilisateur = 'enseignant'
                JOIN unites_enseignements ue ON 1=1";
            }
        } else {
            // Fallback sur la table professeurs
            if ($ueIdExists) {
                $query .= "\n            FROM affectations a
                JOIN professeurs p ON a.professeur_id = p.id
                JOIN unites_enseignements ue ON a.ue_id = ue.id_ue";
            } else if ($idUeExists) {
                $query .= "\n            FROM affectations a
                JOIN professeurs p ON a.professeur_id = p.id
                JOIN unites_enseignements ue ON a.id_ue = ue.id_ue";
            } else {
                // Si aucune des colonnes n'existe, utiliser une jointure simplifiée
                $query .= "\n            FROM affectations a
                JOIN professeurs p ON a.professeur_id = p.id
                JOIN unites_enseignements ue ON 1=1";
            }
        }

        if ($dateDebutExists) {
            $query .= "\n            ORDER BY a.date_debut DESC";
        } else {
            $query .= "\n            ORDER BY a.id DESC";
        }

        $stmt = $pdo->prepare($query);

    } catch (PDOException $e) {
        // En cas d'erreur, utiliser une requête simplifiée sans les colonnes problématiques
        // Vérifier si la colonne ue_id existe dans la table affectations
        $stmt = $pdo->query("SHOW COLUMNS FROM affectations LIKE 'ue_id'");
        $ueIdExists = $stmt->rowCount() > 0;

        // Vérifier si la colonne id_ue existe dans la table affectations
        $stmt = $pdo->query("SHOW COLUMNS FROM affectations LIKE 'id_ue'");
        $idUeExists = $stmt->rowCount() > 0;

        // Vérifier si la table utilisateurs existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'utilisateurs'");
        $utilisateursExists = $stmt->rowCount() > 0;

        if ($utilisateursExists) {
            // Utiliser la table utilisateurs
            if ($ueIdExists) {
                $stmt = $pdo->prepare("
                    SELECT a.id,
                           p.id as prof_id,
                           CONCAT(p.nom, ' ', p.prenom) as professeur,
                           ue.id_ue as ue_id,
                           ue.filiere as unite_enseignement,
                           CONCAT('UE', ue.id_ue) as code_ue,
                           " . date('Y') . " as annee,
                           1 as semestre,
                           DATE_FORMAT(NOW(), '%d/%m/%Y') as date_debut_format,
                           '-' as date_fin_format,
                           30 as heures
                    FROM affectations a
                    JOIN utilisateurs p ON a.professeur_id = p.id AND p.type_utilisateur = 'enseignant'
                    JOIN unites_enseignements ue ON a.ue_id = ue.id_ue
                    ORDER BY a.id DESC
                ");
            } else if ($idUeExists) {
                $stmt = $pdo->prepare("
                    SELECT a.id,
                           p.id as prof_id,
                           CONCAT(p.nom, ' ', p.prenom) as professeur,
                           ue.id_ue as ue_id,
                           ue.filiere as unite_enseignement,
                           CONCAT('UE', ue.id_ue) as code_ue,
                           " . date('Y') . " as annee,
                           1 as semestre,
                           DATE_FORMAT(NOW(), '%d/%m/%Y') as date_debut_format,
                           '-' as date_fin_format,
                           30 as heures
                    FROM affectations a
                    JOIN utilisateurs p ON a.professeur_id = p.id AND p.type_utilisateur = 'enseignant'
                    JOIN unites_enseignements ue ON a.id_ue = ue.id_ue
                    ORDER BY a.id DESC
                ");
            } else {
                // Si aucune des colonnes n'existe, utiliser une requête simplifiée
                $stmt = $pdo->prepare("
                    SELECT a.id,
                           p.id as prof_id,
                           CONCAT(p.nom, ' ', p.prenom) as professeur,
                           ue.id_ue as ue_id,
                           ue.filiere as unite_enseignement,
                           CONCAT('UE', ue.id_ue) as code_ue,
                           " . date('Y') . " as annee,
                           1 as semestre,
                           DATE_FORMAT(NOW(), '%d/%m/%Y') as date_debut_format,
                           '-' as date_fin_format,
                           30 as heures
                    FROM affectations a
                    JOIN utilisateurs p ON a.professeur_id = p.id AND p.type_utilisateur = 'enseignant'
                    CROSS JOIN unites_enseignements ue
                    ORDER BY a.id DESC
                    LIMIT 10
                ");
            }
        } else {
            // Fallback sur la table professeurs
            if ($ueIdExists) {
                $stmt = $pdo->prepare("
                    SELECT a.id,
                           p.id as prof_id,
                           CONCAT(p.nom, ' ', p.prenom) as professeur,
                           ue.id_ue as ue_id,
                           ue.filiere as unite_enseignement,
                           CONCAT('UE', ue.id_ue) as code_ue,
                           " . date('Y') . " as annee,
                           1 as semestre,
                           DATE_FORMAT(NOW(), '%d/%m/%Y') as date_debut_format,
                           '-' as date_fin_format,
                           30 as heures
                    FROM affectations a
                    JOIN professeurs p ON a.professeur_id = p.id
                    JOIN unites_enseignements ue ON a.ue_id = ue.id_ue
                    ORDER BY a.id DESC
                ");
            } else if ($idUeExists) {
                $stmt = $pdo->prepare("
                    SELECT a.id,
                           p.id as prof_id,
                           CONCAT(p.nom, ' ', p.prenom) as professeur,
                           ue.id_ue as ue_id,
                           ue.filiere as unite_enseignement,
                           CONCAT('UE', ue.id_ue) as code_ue,
                           " . date('Y') . " as annee,
                           1 as semestre,
                           DATE_FORMAT(NOW(), '%d/%m/%Y') as date_debut_format,
                           '-' as date_fin_format,
                           30 as heures
                    FROM affectations a
                    JOIN professeurs p ON a.professeur_id = p.id
                    JOIN unites_enseignements ue ON a.id_ue = ue.id_ue
                    ORDER BY a.id DESC
                ");
            } else {
                // Si aucune des colonnes n'existe, utiliser une requête simplifiée
                $stmt = $pdo->prepare("
                    SELECT a.id,
                           p.id as prof_id,
                           CONCAT(p.nom, ' ', p.prenom) as professeur,
                           ue.id_ue as ue_id,
                           ue.filiere as unite_enseignement,
                           CONCAT('UE', ue.id_ue) as code_ue,
                           " . date('Y') . " as annee,
                           1 as semestre,
                           DATE_FORMAT(NOW(), '%d/%m/%Y') as date_debut_format,
                           '-' as date_fin_format,
                           30 as heures
                    FROM affectations a
                    JOIN professeurs p ON a.professeur_id = p.id
                    CROSS JOIN unites_enseignements ue
                    ORDER BY a.id DESC
                    LIMIT 10
                ");
            }
        }
    }
    $stmt->execute();
    $affectations = $stmt->fetchAll();

    // Récupération des professeurs depuis la table utilisateurs
    try {
        // Vérifier si la table utilisateurs existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'utilisateurs'");
        $utilisateursExists = $stmt->rowCount() > 0;

        if ($utilisateursExists) {
            // Récupérer les enseignants depuis la table utilisateurs
            $professeurs = $pdo->query("
                SELECT id, CONCAT(nom, ' ', prenom) as nom_complet
                FROM utilisateurs
                WHERE type_utilisateur = 'enseignant'
                ORDER BY nom, prenom
            ")->fetchAll();
        } else {
            // Fallback sur la table professeurs si utilisateurs n'existe pas
            $professeurs = $pdo->query("
                SELECT id, CONCAT(nom, ' ', prenom) as nom_complet
                FROM professeurs
                ORDER BY nom, prenom
            ")->fetchAll();
        }
    } catch (PDOException $e) {
        // En cas d'erreur, utiliser un tableau vide
        $professeurs = [];
        $error = "Erreur lors de la récupération des professeurs: " . $e->getMessage();
    }

    // Récupération des UE (adaptée à votre structure)
    try {
        // Vérifier quelle table existe : unites_enseignement ou unites_enseignements
        $stmt = $pdo->query("SHOW TABLES LIKE 'unites_enseignement'");
        $ueTableExists = $stmt->rowCount() > 0;

        if ($ueTableExists) {
            $ueTableName = 'unites_enseignement';
            echo "<!-- Table unites_enseignement existe -->";
        } else {
            $stmt = $pdo->query("SHOW TABLES LIKE 'unites_enseignements'");
            $uesTableExists = $stmt->rowCount() > 0;

            if ($uesTableExists) {
                $ueTableName = 'unites_enseignements';
                echo "<!-- Table unites_enseignements existe -->";
            } else {
                // Aucune des tables n'existe, créer la table unites_enseignement
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS unites_enseignement (
                        id_ue INT AUTO_INCREMENT PRIMARY KEY,
                        code_ue VARCHAR(20) NOT NULL,
                        intitule VARCHAR(255) NOT NULL,
                        filiere VARCHAR(100) NOT NULL,
                        credit INT DEFAULT 3,
                        semestre INT DEFAULT 1
                    )
                ");
                $ueTableName = 'unites_enseignement';
                echo "<!-- Table unites_enseignement créée -->";

                // Ajouter une UE par défaut
                $pdo->exec("INSERT INTO unites_enseignement (code_ue, intitule, filiere, credit, semestre) VALUES ('UE001', 'Unité d''enseignement par défaut', 'Général', 3, 1)");
            }
        }

        // Vérifier si la table matieres existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'matieres'");
        $matieresExists = $stmt->rowCount() > 0;

        // Vérifier si la colonne id_matiere existe dans la table
        $stmt = $pdo->query("SHOW COLUMNS FROM $ueTableName LIKE 'id_matiere'");
        $idMatiereExists = $stmt->rowCount() > 0;

        // Vérifier si la colonne niveau existe dans la table
        $stmt = $pdo->query("SHOW COLUMNS FROM $ueTableName LIKE 'niveau'");
        $niveauExists = $stmt->rowCount() > 0;

        if ($matieresExists && $idMatiereExists) {
            // Si la table matieres existe et la colonne id_matiere existe
            $query = "
                SELECT ue.id_ue as id,
                       CONCAT(m.code, ' - ', m.nom, ' (', ue.filiere";

            if ($niveauExists) {
                $query .= ", ' ', ue.niveau";
            }

            $query .= ")') as nom_complet,
                       m.code as code_ue
                FROM $ueTableName ue
                JOIN matieres m ON ue.id_matiere = m.id_matiere
                ORDER BY m.code
            ";
        } else {
            // Requête simplifiée sans la table matieres
            $query = "
                SELECT ue.id_ue as id,
                       CONCAT('UE', ue.id_ue, ' - ', ue.filiere";

            if ($niveauExists) {
                $query .= ", ' (', ue.niveau, ')'";
            } else {
                $query .= "')";
            }

            $query .= " as nom_complet,
                       CONCAT('UE', ue.id_ue) as code_ue
                FROM $ueTableName ue
                ORDER BY ue.id_ue
            ";
        }

        $unites_enseignement = $pdo->query($query)->fetchAll();
    } catch (PDOException $e) {
        // En cas d'erreur, utiliser une requête très simplifiée
        $unites_enseignement = $pdo->query("
            SELECT ue.id_ue as id,
                   CONCAT('UE', ue.id_ue, ' - ', ue.filiere) as nom_complet,
                   CONCAT('UE', ue.id_ue) as code_ue
            FROM $ueTableName ue
            ORDER BY ue.id_ue
        ")->fetchAll();
    }

} catch (PDOException $e) {
    $error = "Erreur de base de données: " . $e->getMessage();
    error_log("DB Error: " . $e->getMessage());
}

// 6. Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_affectation'])) {
    try {
        // Validation CSRF
        if (empty($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
            throw new Exception("Erreur de sécurité: Token invalide");
        }

        // Validation des données
        $required = [
            'professeur_id' => 'Professeur',
            'ue_id' => 'Unité d\'enseignement',
            'annee' => 'Année',
            'semestre' => 'Semestre',
            'date_debut' => 'Date de début',
            'heures' => 'Nombre d\'heures'
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

        // Vérifier si la table utilisateurs existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'utilisateurs'");
        $utilisateursExists = $stmt->rowCount() > 0;

        // Vérifier si la colonne ue_id existe dans la table affectations
        $stmt = $pdo->query("SHOW COLUMNS FROM affectations LIKE 'ue_id'");
        $ueIdExists = $stmt->rowCount() > 0;

        // Vérifier si la colonne id_ue existe dans la table affectations
        $stmt = $pdo->query("SHOW COLUMNS FROM affectations LIKE 'id_ue'");
        $idUeExists = $stmt->rowCount() > 0;

        // Vérifier si le professeur existe dans la table utilisateurs
        if ($utilisateursExists) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE id = ? AND type_utilisateur = 'enseignant'");
            $stmt->execute([(int)$_POST['professeur_id']]);
            $professorExists = $stmt->fetchColumn() > 0;

            if (!$professorExists) {
                throw new Exception("Le professeur sélectionné n'existe pas ou n'est pas un enseignant.");
            }

            // Vérifier s'il y a des contraintes de clé étrangère sur la table affectations
            try {
                // Récupérer les informations sur les contraintes de clé étrangère
                $stmt = $pdo->query("
                    SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'affectations'
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");

                $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // S'il y a des contraintes, les supprimer
                if (!empty($constraints)) {
                    foreach ($constraints as $constraint) {
                        $pdo->exec("ALTER TABLE affectations DROP FOREIGN KEY `{$constraint['CONSTRAINT_NAME']}`");
                        echo "<!-- Contrainte supprimée: {$constraint['CONSTRAINT_NAME']} (colonne: {$constraint['COLUMN_NAME']}, référence: {$constraint['REFERENCED_TABLE_NAME']}) -->";
                    }
                }

                // Vérifier si le professeur existe dans la table professeurs
                // Si non, l'ajouter pour maintenir l'intégrité référentielle
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM professeurs WHERE id = ?");
                $stmt->execute([(int)$_POST['professeur_id']]);
                $profExistsInProfTable = $stmt->fetchColumn() > 0;

                if (!$profExistsInProfTable) {
                    // Récupérer les informations du professeur depuis la table utilisateurs
                    $stmt = $pdo->prepare("
                        SELECT id, nom, prenom, email
                        FROM utilisateurs
                        WHERE id = ? AND type_utilisateur = 'enseignant'
                    ");
                    $stmt->execute([(int)$_POST['professeur_id']]);
                    $prof = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($prof) {
                        // Insérer le professeur dans la table professeurs
                        $stmt = $pdo->prepare("
                            INSERT INTO professeurs (id, nom, prenom, email)
                            VALUES (:id, :nom, :prenom, :email)
                            ON DUPLICATE KEY UPDATE
                            nom = :nom, prenom = :prenom, email = :email
                        ");
                        $stmt->execute([
                            'id' => $prof['id'],
                            'nom' => $prof['nom'],
                            'prenom' => $prof['prenom'],
                            'email' => $prof['email']
                        ]);
                        echo "<!-- Professeur ajouté à la table professeurs: ID " . $prof['id'] . " -->";
                    }
                }
            } catch (PDOException $e) {
                // Ignorer les erreurs liées à la structure de la base de données
                echo "<!-- Erreur lors de la gestion des contraintes: " . htmlspecialchars($e->getMessage()) . " -->";
            }
        }

        // Vérifier si les colonnes existent dans la table affectations
        $stmt = $pdo->query("SHOW COLUMNS FROM affectations LIKE 'annee'");
        $anneeExists = $stmt->rowCount() > 0;

        $stmt = $pdo->query("SHOW COLUMNS FROM affectations LIKE 'semestre'");
        $semestreExists = $stmt->rowCount() > 0;

        $stmt = $pdo->query("SHOW COLUMNS FROM affectations LIKE 'date_debut'");
        $dateDebutExists = $stmt->rowCount() > 0;

        $stmt = $pdo->query("SHOW COLUMNS FROM affectations LIKE 'date_fin'");
        $dateFinExists = $stmt->rowCount() > 0;

        $stmt = $pdo->query("SHOW COLUMNS FROM affectations LIKE 'heures'");
        $heuresExists = $stmt->rowCount() > 0;

        $stmt = $pdo->query("SHOW COLUMNS FROM affectations LIKE 'utilisateur_id'");
        $utilisateurIdExists = $stmt->rowCount() > 0;

        // Vérifier si la colonne specialite_id existe
        $stmt = $pdo->query("SHOW COLUMNS FROM affectations LIKE 'specialite_id'");
        $specialiteIdExists = $stmt->rowCount() > 0;

        // Si la colonne specialite_id existe, vérifier si la table specialite existe
        if ($specialiteIdExists) {
            $stmt = $pdo->query("SHOW TABLES LIKE 'specialite'");
            $specialiteTableExists = $stmt->rowCount() > 0;

            if ($specialiteTableExists) {
                // Récupérer la première spécialité disponible
                $stmt = $pdo->query("SELECT id_specialite FROM specialite LIMIT 1");
                $defaultSpecialiteId = $stmt->fetchColumn();

                if (!$defaultSpecialiteId) {
                    // Si aucune spécialité n'existe, créer une spécialité par défaut
                    $pdo->exec("INSERT INTO specialite (nom_specialite) VALUES ('Spécialité par défaut')");
                    $defaultSpecialiteId = $pdo->lastInsertId();
                    echo "<!-- Spécialité par défaut créée avec ID: $defaultSpecialiteId -->";
                }
            } else {
                // Si la table specialite n'existe pas, créer la table
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS specialite (
                        id_specialite INT AUTO_INCREMENT PRIMARY KEY,
                        nom_specialite VARCHAR(255) NOT NULL
                    )
                ");

                // Insérer une spécialité par défaut
                $pdo->exec("INSERT INTO specialite (nom_specialite) VALUES ('Spécialité par défaut')");
                $defaultSpecialiteId = $pdo->lastInsertId();
                echo "<!-- Table specialite créée avec une spécialité par défaut, ID: $defaultSpecialiteId -->";
            }
        }

        // Construire la requête d'insertion en fonction des colonnes existantes
        $insertColumns = ["professeur_id"];
        $insertValues = [":professeur_id"];

        // Ajouter la colonne ue_id ou id_ue selon ce qui existe
        if ($ueIdExists) {
            $insertColumns[] = "ue_id";
            $insertValues[] = ":ue_id";
        } else if ($idUeExists) {
            $insertColumns[] = "id_ue";
            $insertValues[] = ":ue_id";
        } else {
            // Si aucune des colonnes n'existe, créer la colonne ue_id
            try {
                $pdo->exec("ALTER TABLE affectations ADD COLUMN ue_id INT NOT NULL AFTER professeur_id");
                $insertColumns[] = "ue_id";
                $insertValues[] = ":ue_id";
            } catch (PDOException $e) {
                throw new Exception("Erreur lors de la modification de la table: " . $e->getMessage());
            }
        }

        // Ajouter les autres colonnes si elles existent
        if ($anneeExists) {
            $insertColumns[] = "annee";
            $insertValues[] = ":annee";
        }

        if ($semestreExists) {
            $insertColumns[] = "semestre";
            $insertValues[] = ":semestre";
        }

        if ($dateDebutExists) {
            $insertColumns[] = "date_debut";
            $insertValues[] = ":date_debut";
        }

        if ($dateFinExists) {
            $insertColumns[] = "date_fin";
            $insertValues[] = ":date_fin";
        }

        if ($heuresExists) {
            $insertColumns[] = "heures";
            $insertValues[] = ":heures";
        }

        if ($utilisateurIdExists) {
            $insertColumns[] = "utilisateur_id";
            $insertValues[] = ":utilisateur_id";
        }

        // Ajouter la colonne specialite_id si elle existe
        if ($specialiteIdExists) {
            $insertColumns[] = "specialite_id";
            $insertValues[] = ":specialite_id";
        }

        // Construire la requête SQL
        $sql = "INSERT INTO affectations (" . implode(", ", $insertColumns) . ") VALUES (" . implode(", ", $insertValues) . ")";
        $stmt = $pdo->prepare($sql);

        // Préparer les données en fonction des colonnes existantes
        $data = [
            'professeur_id' => (int)$_POST['professeur_id'],
            'ue_id' => (int)$_POST['ue_id']
        ];

        // Ajouter les autres données si les colonnes existent
        if ($anneeExists) {
            $data['annee'] = (int)$_POST['annee'];
        }

        if ($semestreExists) {
            $data['semestre'] = (int)$_POST['semestre'];
        }

        if ($dateDebutExists) {
            $data['date_debut'] = $_POST['date_debut'];
        }

        if ($dateFinExists) {
            $data['date_fin'] = $_POST['date_fin'] ?? null;
        }

        if ($heuresExists) {
            $data['heures'] = (int)$_POST['heures'];
        }

        if ($utilisateurIdExists) {
            $data['utilisateur_id'] = $_SESSION['user_id'];
        }

        // Ajouter la valeur de specialite_id si la colonne existe
        if ($specialiteIdExists) {
            $data['specialite_id'] = $defaultSpecialiteId ?? 1; // Utiliser la spécialité par défaut ou 1 si non définie
        }

        if ($stmt->execute($data)) {
            $_SESSION['success'] = "Affectation ajoutée avec succès!";
            header("Location: affectation_ue.php");
            exit;
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// 7. Gestion de la suppression
if (isset($_GET['supprimer'])) {
    try {
        if (empty($_GET['csrf_token']) || !hash_equals($csrf_token, $_GET['csrf_token'])) {
            throw new Exception("Erreur de sécurité: Token invalide");
        }

        $id = (int)$_GET['supprimer'];
        $stmt = $pdo->prepare("DELETE FROM affectations WHERE id = ?");

        if ($stmt->execute([$id])) {
            $_SESSION['success'] = "Affectation supprimée avec succès!";
            header("Location: affectation_ue.php");
            exit;
        }

    } catch (Exception $e) {
        $error = "Erreur lors de la suppression: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Affectations UE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6a0dad;
            --secondary-color: #8a2be2;
            --light-purple: #e6e6fa;
            --dark-purple: #4b0082;
            --accent-color: #00bfff;
            --teacher-color: #3498db;
            --teacher-dark: #2980b9;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
            color: white;
            padding: 10px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-logo {
            height: 50px;
            display: flex;
            align-items: center;
        }

        .header-logo img {
            height: 100%;
            filter: drop-shadow(0 0 5px rgba(255, 255, 255, 0.5));
        }

        .header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            padding-left: 15px;
            border-left: 1px solid rgba(255, 255, 255, 0.3);
        }

        .header-right {
            display: flex;
            gap: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.15);
            padding: 8px 15px;
            border-radius: 30px;
            backdrop-filter: blur(5px);
        }

        .user-info i {
            color: rgba(255, 255, 255, 0.9);
        }

        .user-info-label {
            font-weight: 600;
            color: rgba(255, 255, 255, 0.8);
        }

        .user-info-value {
            color: white;
            font-weight: 500;
        }

        /* Conteneur principal */
        .main-container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        /* ================ SIDEBAR MODERNE ================ */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--dark-purple) 0%, var(--primary-color) 100%);
            height: 100%;
            padding: 20px 0;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }

        .sidebar-header {
            padding: 0 20px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 15px;
        }

        .sidebar-header img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: 10px;
            border: 2px solid white;
            padding: 5px;
        }

        .sidebar-header h3 {
            color: white;
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }

        .sidebar-menu {
            flex: 1;
            overflow-y: auto;
            padding: 0 15px;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            color: white;
            padding: 12px 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            text-decoration: none;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .nav-link:hover::before {
            left: 100%;
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            border-left: 4px solid var(--accent-color);
        }

        .nav-link i {
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
        }

        .nav-link span {
            flex: 1;
        }

        .logout-btn {
            background: rgba(255, 71, 87, 0.2);
            margin-top: 10px;
        }

        .logout-btn:hover {
            background: rgba(255, 71, 87, 0.3);
        }

        /* Section titles */
        .section-title {
            color: white;
            padding: 12px 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 15px;
            margin-bottom: 5px;
            background: rgba(255, 255, 255, 0.1);
        }

        .section-title:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .section-title i {
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
        }

        .section-title span {
            flex: 1;
        }

        .section-title .arrow {
            transition: transform 0.3s ease;
            font-size: 0.9rem;
        }

        .section-title.active .arrow {
            transform: rotate(180deg);
        }

        /* Submenu */
        .submenu {
            overflow: hidden;
            transition: max-height 0.3s ease;
            max-height: 0;
        }

        .submenu.show {
            max-height: 1000px;
        }

        /* Contenu principal */
        .main-content {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            background-color: #f8f9fc;
        }

        /* Cartes */
        .card {
            margin-bottom: 20px;
            border-radius: 12px;
            background: white;
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
            color: white;
            padding: 15px;
            border-radius: 12px 12px 0 0 !important;
            border-bottom: none;
        }

        /* Table */
        #affectationsTable {
            background: white;
            border: 1px solid #e0e0e0;
            color: #333;
        }

        #affectationsTable thead {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
            color: white;
            border-bottom: none;
        }

        #affectationsTable tbody tr:hover {
            background-color: rgba(106, 13, 173, 0.1) !important;
        }

        /* Formulaires */
        .form-control, .form-select {
            background-color: white;
            color: #333;
            border: 1px solid #ced4da;
        }

        .form-control:focus, .form-select:focus {
            background-color: white;
            color: #333;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(0, 191, 255, 0.25);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--dark-purple);
            border-color: var(--dark-purple);
        }

        .alert {
            border-radius: 12px;
        }

        /* Boutons d'export */
        .export-buttons {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .export-btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .export-btn i {
            font-size: 1.1rem;
        }

        .export-btn-excel {
            background-color: #1D6F42;
            color: white;
            border: none;
        }

        .export-btn-excel:hover {
            background-color: #155a35;
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0,0,0,0.15);
        }

        .export-btn-print {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }

        .export-btn-print:hover {
            background-color: var(--dark-purple);
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0,0,0,0.15);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 220px;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header-left {
                flex-direction: column;
                width: 100%;
                justify-content: center;
            }
            
            .header h1 {
                border-left: none;
                border-top: 1px solid rgba(255, 255, 255, 0.3);
                padding-left: 0;
                padding-top: 10px;
            }
            
            .main-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                padding: 15px 0;
            }
        }
    </style>
</head>
<body>
    <!-- En-tête avec logo -->
    <div class="header">
        <div class="header-left">
            <div class="header-logo">
                <img src="images/logo.png" alt="Logo">
            </div>
            <h1>Gestion des Affectations UE</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-user"></i>
                <span class="user-info-value">chef_departement</span>
            </div>
        </div>
    </div>

    <!-- Conteneur principal -->
    <div class="main-container">
        <!-- Sidebar Moderne -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <img src="https://ui-avatars.com/api/?name=Admin&background=8a2be2&color=fff" alt="Administrateur">
                <h3>departement informatique</h3>
            </div>
            <div class="sidebar-menu">
                <!-- Tableau de bord -->
                <div class="nav-item">
                    <a href="chef_dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Tableau de Bord</span>
                    </a>
                </div>
                
                <!-- Section Chef Département (active par défaut) -->
                <div class="section-title coordinateur active" id="chef-section">
                    <i class="fas fa-user-tie"></i>
                    <span>Chef Département</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </div>
                
                <div class="submenu show" id="chef-menu">
                    <div class="nav-item">
                        <a href="gestion_modules.php" class="nav-link">
                            <i class="fas fa-book"></i>
                            <span>Unités d'enseignement</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="gestion_professeurs.php" class="nav-link">
                            <i class="fas fa-users-cog"></i>
                            <span>Gestion Professeurs</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="affectation_ue.php" class="nav-link active">
                            <i class="fas fa-tasks"></i>
                            <span>Affectation UE</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="gestion_choix.php" class="nav-link">
                            <i class="fas fa-check-double"></i>
                            <span>Validation Choix</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="ajouter_charge_horaire.php" class="nav-link">
                            <i class="fas fa-chart-pie"></i>
                            <span>Charge Horaire</span>
                        </a>
                    </div>
                     <div class="nav-item">
                        <a href="ue_vacantes.php" class="nav-link">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>UE Vacantes</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="historique.php" class="nav-link">
                            <i class="fas fa-archive"></i>
                            <span>Historique</span>
                        </a>
                    </div>
                </div>
                
                <!-- Section Enseignant (fermée par défaut) -->
                <div class="section-title enseignant" id="enseignant-section">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Enseignant</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </div>
                
                <div class="submenu" id="enseignant-menu">
                   
                    
                    <li class="nav-item">
                        <a href="dashboard_enseignant.php" class="nav-link active">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Tableau de bord</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="Affichage_liste_UE.php" class="nav-link">
                            <i class="fas fa-list-ul"></i>
                            <span>Liste des UE</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="souhaits_enseignants.php" class="nav-link">
                            <i class="fas fa-hand-paper"></i>
                            <span>Souhaits enseignants</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="Calcul_automatique_charge_horaire.php" class="nav-link">
                            <i class="fas fa-calculator"></i>
                            <span>Charge horaire</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="Notification.php" class="nav-link">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="Consulter_modules.php" class="nav-link">
                            <i class="fas fa-book-open"></i>
                            <span>Modules assurés</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="Uploader_notes.php" class="nav-link">
                            <i class="fas fa-upload"></i>
                            <span>Upload notes</span>
                        </a>
                    </li>
                </div>
                
                <!-- Déconnexion -->
                <div class="nav-item">
                    <a href="logout.php" class="nav-link logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Déconnexion</span>
                    </a>
                </div>
            </div>
        </nav>

        <!-- Contenu principal -->
        <div class="main-content">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="m-0"><i class="fas fa-plus-circle me-2"></i> Nouvelle Affectation</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Professeur *</label>
                                <select class="form-select" name="professeur_id" required>
                                    <option value="">Sélectionner...</option>
                                    <?php foreach ($professeurs as $prof): ?>
                                        <option value="<?= $prof['id'] ?>"><?= htmlspecialchars($prof['nom_complet']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Unité d'Enseignement *</label>
                                <select class="form-select" name="ue_id" required>
                                    <option value="">Sélectionner...</option>
                                    <?php foreach ($unites_enseignement as $ue): ?>
                                        <option value="<?= $ue['id'] ?>"><?= htmlspecialchars($ue['nom_complet']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Année *</label>
                                <select class="form-select" name="annee" required>
                                    <?php $currentYear = date('Y'); ?>
                                    <?php for ($i = $currentYear; $i <= $currentYear + 2; $i++): ?>
                                        <option value="<?= $i ?>" <?= $i == $currentYear ? 'selected' : '' ?>><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Semestre *</label>
                                <select class="form-select" name="semestre" required>
                                    <option value="1">Semestre 1</option>
                                    <option value="2">Semestre 2</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Date début *</label>
                                <input type="date" class="form-control" name="date_debut" required value="<?= date('Y-m-d') ?>">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Date fin</label>
                                <input type="date" class="form-control" name="date_fin">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Heures *</label>
                                <input type="number" class="form-control" name="heures" required min="1" value="30">
                            </div>
                        </div>

                        <button type="submit" name="ajouter_affectation" class="btn btn-primary mt-3">
                            <i class="fas fa-save me-2"></i> Enregistrer
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="m-0"><i class="fas fa-list me-2"></i> Liste des Affectations</h5>
                </div>
                <div class="card-body">
                    <div class="export-buttons mb-4">
                        <button id="export-excel" class="btn export-btn export-btn-excel">
                            <i class="fas fa-file-excel"></i> Exporter vers Excel
                        </button>
                        <button id="export-print" class="btn export-btn export-btn-print">
                            <i class="fas fa-print"></i> Imprimer
                        </button>
                    </div>
                    <table class="table table-hover" id="affectationsTable">
                        <thead>
                            <tr>
                                <th>Professeur</th>
                                <th>Unité d'Enseignement</th>
                                <th>Code UE</th>
                                <th>Année</th>
                                <th>Sem.</th>
                                <th>Date début</th>
                                <th>Date fin</th>
                                <th>Heures</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($affectations as $aff): ?>
                            <tr>
                                <td><?= htmlspecialchars($aff['professeur']) ?></td>
                                <td><?= htmlspecialchars($aff['unite_enseignement'] ?? '') ?></td>
                                <td><?= htmlspecialchars($aff['code_ue'] ?? '') ?></td>
                                <td><?= $aff['annee'] ?? date('Y') ?></td>
                                <td>S<?= $aff['semestre'] ?? '1' ?></td>
                                <td><?= $aff['date_debut_format'] ?? '' ?></td>
                                <td><?= $aff['date_fin_format'] ?? '-' ?></td>
                                <td><?= $aff['heures'] ?></td>
                                <td>
                                    <a href="editer_affectation.php?id=<?= $aff['id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?supprimer=<?= $aff['id'] ?>&csrf_token=<?= htmlspecialchars($csrf_token) ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Confirmer la suppression?')">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script>
        $(document).ready(function() {
            // Gestion des menus déroulants
            $('#chef-section').on('click', function() {
                $(this).toggleClass('active');
                $('#chef-menu').toggleClass('show');
            });

            $('#enseignant-section').on('click', function() {
                $(this).toggleClass('active');
                $('#enseignant-menu').toggleClass('show');
            });

            // DataTable
            $('#affectationsTable').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                },
                order: [[5, 'desc']],
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        title: 'Liste des Affectations UE',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7]
                        },
                        className: 'btn-excel'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Imprimer',
                        title: 'Liste des Affectations UE',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7]
                        },
                        className: 'btn-print'
                    }
                ]
            });

            $('#export-excel').on('click', function() {
                $('#affectationsTable').DataTable().button('.buttons-excel').trigger();
            });

            $('#export-print').on('click', function() {
                $('#affectationsTable').DataTable().button('.buttons-print').trigger();
            });
        });
    </script>
</body>
</html>