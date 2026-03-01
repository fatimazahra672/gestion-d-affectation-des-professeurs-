<?php
// Connexion à la base de données
$conn = new mysqli("localhost", "root", "", "gestion_coordinteur");

if ($conn->connect_error) {
    die("Échec de la connexion : " . $conn->connect_error);
}

// Fonction pour valider et nettoyer les entrées
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Fonction pour exporter en Excel avec sécurité améliorée
function exporterVersExcel($conn, $table) {
    // Liste des tables autorisées
    $tables_autorisees = ['groupes', 'etudiants', 'enseignants', 'vacataires', 'matieres', 'unites_enseignements', 'affectations_vacataires', 'creneaux'];

    // Vérifier si la table est autorisée
    if (!in_array($table, $tables_autorisees)) {
        die("Table non autorisée");
    }

    // Utiliser une requête préparée pour plus de sécurité
    $stmt = $conn->prepare("SELECT * FROM " . $table);
    if (!$stmt) {
        die("Erreur de préparation de la requête: " . $conn->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) {
        die("Erreur d'exécution de la requête: " . $stmt->error);
    }

    // Créer le contenu CSV
    $output = fopen('php://output', 'w');

    // En-têtes HTTP pour forcer le téléchargement
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="export_'.$table.'_'.date('Y-m-d').'.csv"');

    // Écrire les en-têtes de colonnes
    $fields = $result->fetch_fields();
    $headers = array();
    foreach ($fields as $field) {
        $headers[] = $field->name;
    }
    fputcsv($output, $headers, ";");

    // Écrire les données
    while ($row = $result->fetch_assoc()) {
        // Nettoyer les données pour éviter les injections
        $clean_row = array_map('sanitize_input', $row);
        fputcsv($output, $clean_row, ";");
    }

    fclose($output);
    $stmt->close();
    exit;
}

// Traitement de l'export
if (isset($_GET['export']) && isset($_GET['table'])) {
    // Journaliser l'export
    $user_ip = $_SERVER['REMOTE_ADDR'];
    $date = date('Y-m-d H:i:s');

    $table = sanitize_input($_GET['table']);
    $tables_autorisees = ['groupes', 'etudiants', 'enseignants', 'vacataires', 'matieres', 'unites_enseignements', 'affectations_vacataires', 'creneaux'];

    if (in_array($table, $tables_autorisees)) {
        $log_message = "Export de la table '$table' effectué le $date depuis l'IP $user_ip\n";
        error_log($log_message, 3, "exports.log");

        // Effectuer l'export
        exporterVersExcel($conn, $table);
    } else {
        echo "<script>alert('Table non autorisée'); window.location.href='Extraire_D_Excel.php';</script>";
        exit;
    }
}

// Fonction pour obtenir le nombre d'enregistrements dans une table
function getTableCount($conn, $table) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM " . $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'];
}


?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Export Excel</title>
    <style>
        /* Style général avec gradient de fond violet clair comme dans l'image */
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #e9d8fd, #c4b5fd);
            background-size: cover;
            margin: 0;
            padding: 0;
            color: #333;
            position: relative;
            min-height: 100vh;
        }

        /* Pas besoin d'overlay semi-transparent avec le gradient */

        /* Contenu principal */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            position: relative;
            z-index: 1;
            transition: margin-left 0.3s;
        }

        /* Style du header */
        header {
            width: 250px;
            background-color: #1e1a3a; /* Bleu foncé/violet foncé comme dans l'image */
            color: #fff;
            position: fixed;
            height: 100%;
            padding: 20px;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 8px rgba(0, 0, 0, 0.4);
            z-index: 2;
            border-right: 1px solid #6a11cb;
        }

        /* Style pour la sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            height: 100%;
        }

        /* Style du logo */
        .logo {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(30, 144, 255, 0.3);
        }

        .logo img {
            max-width: 80%;
            border-radius: 50%;
            border: 2px solid #6a11cb;
        }

        .logo h2 {
            margin-top: 15px;
            font-size: 1.3rem;
            color: #fff;
            text-shadow: 0 0 10px rgba(106, 17, 203, 0.5);
        }

        /* Style de la navigation */
        nav ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
            width: 100%;
        }

        nav ul li {
            margin: 10px 0;
            width: 100%;
        }

        nav ul li a {
            color: #ecf0f1;
            text-decoration: none;
            font-size: 16px;
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        nav ul li a i {
            margin-right: 10px;
            font-size: 18px;
        }

        /* Effet au survol des liens */
        nav ul li a:hover {
            background-color: rgba(106, 17, 203, 0.2);
            padding-left: 20px;
            color: #c4b5fd;
            text-shadow: 0 0 5px rgba(106, 17, 203, 0.5);
        }

        /* Style des titres */
        h1, h2 {
            color: #6a11cb;
            font-size: 2rem;
            margin-bottom: 30px;
            text-shadow: 0 0 10px rgba(106, 17, 203, 0.3);
            text-align: center;
        }

        /* Conteneur principal - style comme le formulaire de login */
        .container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            max-width: 800px;
            margin: 20px auto;
            color: #333;
        }

        /* Style pour le tableau */
        table {
            width: 70%;
            max-width: 800px;
            margin: 30px auto;
            border-collapse: collapse;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            border: 1px solid #e9d8fd;
        }

        th, td {
            text-align: left;
            padding: 10px 12px;
            border-bottom: 1px solid #e9d8fd;
        }

        th {
            background-color: #6a11cb;
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
        }

        td {
            font-size: 0.9rem;
            color: #333;
        }

        tr:hover {
            background-color: #f5f0ff;
        }



        /* Bouton d'export */
        .btn-exporter {
            display: inline-block;
            padding: 10px 20px;
            background-color: #6a11cb;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s ease;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(106, 17, 203, 0.3);
            border: none;
            cursor: pointer;
        }

        .btn-exporter:hover {
            background-color: #5a0cb2;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(106, 17, 203, 0.4);
        }

        .btn-exporter i {
            margin-right: 8px;
        }

        /* Animation des bordures */
        @keyframes borderPulse {
            0% { border-color: #6a11cb; }
            50% { border-color: #c4b5fd; }
            100% { border-color: #6a11cb; }
        }

        /* Responsive */
        @media (max-width: 768px) {
            header {
                width: 100%;
                height: auto;
                position: relative;
                padding: 15px;
            }

            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .container {
                padding: 20px;
                width: 95%;
                margin: 15px auto;
            }

            table {
                width: 95%;
                font-size: 0.85rem;
            }

            th, td {
                padding: 8px 10px;
            }
        }

        /* Style pour le menu actif */
        .active {
            background-color: rgba(106, 17, 203, 0.3) !important;
            color: #c4b5fd !important;
            font-weight: bold;
        }
    </style>
    <!-- Lien Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <!-- Header / Sidebar -->
    <header>
        <div class="sidebar">
            <div class="logo">
                <img src="image copy 9.png" alt="Logo">
                <h2>Gestion Coordinateur</h2>
            </div>

            <nav>
                <ul>
                    <li><a href="gerer_groupes.php"><i class="fas fa-users me-2"></i> Gérer groupes TP/TD</a></li>
                    <li><a href="gerer_emplois_temps.php"><i class="fas fa-calendar-alt me-2"></i> Gérer emplois temps</a></li>
                    <li><a href="affectation_vactaire.php?page=ajouter"><i class="fas fa-user-tie me-2"></i> Affectation des Vacataires</a></li>
                    <li><a href="Definir_UE.php"><i class="fas fa-book me-2"></i> Définir les UE</a></li>
                    <li><a href="Extraire_D_Excel.php" class="active"><i class="fas fa-file-excel me-2"></i> Extraire en Excel</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Déconnexion</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Contenu principal -->
    <div class="main-content">
        <div class="container">
            <h1><i class="fas fa-file-export"></i> Export des données en Excel</h1>

            <h2>Sélectionnez la table à exporter :</h2>

            <table>
                <tr>
                    <th>Table</th>
                    <th>Description</th>
                    <th>Nombre d'enregistrements</th>
                    <th>Action</th>
                </tr>
                <tr>
                    <td><i class="fas fa-users-class"></i> groupes</td>
                    <td>Liste des groupes TP/TD</td>
                    <td><?php echo getTableCount($conn, 'groupes'); ?></td>
                    <td><a href="?export=1&table=groupes" class="btn-exporter"><i class="fas fa-file-export"></i> Exporter</a></td>
                </tr>
                <tr>
                    <td><i class="fas fa-user-graduate"></i> etudiants</td>
                    <td>Liste des étudiants</td>
                    <td><?php echo getTableCount($conn, 'etudiants'); ?></td>
                    <td><a href="?export=1&table=etudiants" class="btn-exporter"><i class="fas fa-file-export"></i> Exporter</a></td>
                </tr>
                <tr>
                    <td><i class="fas fa-chalkboard-teacher"></i> enseignants</td>
                    <td>Liste des enseignants</td>
                    <td><?php echo getTableCount($conn, 'enseignants'); ?></td>
                    <td><a href="?export=1&table=enseignants" class="btn-exporter"><i class="fas fa-file-export"></i> Exporter</a></td>
                </tr>
                <tr>
                    <td><i class="fas fa-user-tie"></i> vacataires</td>
                    <td>Liste des vacataires</td>
                    <td><?php echo getTableCount($conn, 'vacataires'); ?></td>
                    <td><a href="?export=1&table=vacataires" class="btn-exporter"><i class="fas fa-file-export"></i> Exporter</a></td>
                </tr>
                <tr>
                    <td><i class="fas fa-book"></i> matieres</td>
                    <td>Liste des matières</td>
                    <td><?php echo getTableCount($conn, 'matieres'); ?></td>
                    <td><a href="?export=1&table=matieres" class="btn-exporter"><i class="fas fa-file-export"></i> Exporter</a></td>
                </tr>
                <tr>
                    <td><i class="fas fa-graduation-cap"></i> unites_enseignements</td>
                    <td>Liste des unités d'enseignement</td>
                    <td><?php echo getTableCount($conn, 'unites_enseignements'); ?></td>
                    <td><a href="?export=1&table=unites_enseignements" class="btn-exporter"><i class="fas fa-file-export"></i> Exporter</a></td>
                </tr>
                <tr>
                    <td><i class="fas fa-link"></i> affectations_vacataires</td>
                    <td>Liste des affectations de vacataires</td>
                    <td><?php echo getTableCount($conn, 'affectations_vacataires'); ?></td>
                    <td><a href="?export=1&table=affectations_vacataires" class="btn-exporter"><i class="fas fa-file-export"></i> Exporter</a></td>
                </tr>
                <tr>
                    <td><i class="fas fa-calendar-alt"></i> creneaux</td>
                    <td>Liste des créneaux horaires</td>
                    <td><?php echo getTableCount($conn, 'creneaux'); ?></td>
                    <td><a href="?export=1&table=creneaux" class="btn-exporter"><i class="fas fa-file-export"></i> Exporter</a></td>
                </tr>
            </table>
        </div>
    </div>

    <script>
        // Confirmation avant export
        document.querySelectorAll('.btn-exporter').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Êtes-vous sûr de vouloir exporter ces données ?')) {
                    e.preventDefault();
                }
            });
        });

        // Marquer la page active dans le menu
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const menuItems = document.querySelectorAll('nav ul li a');

            menuItems.forEach(item => {
                if (item.getAttribute('href') === currentPage) {
                    item.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>