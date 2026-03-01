<?php
// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Connexion à la base de données
$host = '127.0.0.1';
$dbname = 'gestion_coordinteur'; // Vérifiez si ce nom est correct
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Vérifier la connexion
    echo "<!-- Connexion à la base de données réussie -->";

    // Lister toutes les tables dans la base de données
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<!-- Tables dans la base de données: " . implode(", ", $tables) . " -->";

    // Vérifier si la table utilisateurs existe
    if (in_array('utilisateurs', $tables)) {
        echo "<!-- La table 'utilisateurs' existe dans la base de données -->";
    } else {
        echo "<!-- ATTENTION: La table 'utilisateurs' n'existe PAS dans la base de données -->";
    }
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer tous les enseignants depuis la table utilisateurs
function getEnseignants() {
    global $pdo;

    try {
        // Vérifier si la table utilisateurs existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'utilisateurs'");
        $tableExists = $stmt->rowCount() > 0;

        if ($tableExists) {
            echo "<!-- Table utilisateurs existe -->";

            // Vérifier si la colonne type_utilisateur existe
            $stmt = $pdo->query("SHOW COLUMNS FROM utilisateurs LIKE 'type_utilisateur'");
            $typeColExists = $stmt->rowCount() > 0;

            if ($typeColExists) {
                echo "<!-- Colonne type_utilisateur existe -->";

                // Récupérer tous les types d'utilisateurs disponibles pour le débogage
                $stmt = $pdo->query("SELECT DISTINCT type_utilisateur FROM utilisateurs");
                $types = $stmt->fetchAll(PDO::FETCH_COLUMN);
                echo "<!-- Types d'utilisateurs disponibles: " . implode(", ", $types) . " -->";

                // Compter le nombre d'enseignants
                $stmt = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE type_utilisateur = 'enseignant'");
                $count = $stmt->fetchColumn();
                echo "<!-- Nombre d'enseignants trouvés: " . $count . " -->";

                // Récupérer uniquement les utilisateurs de type "enseignant"
                $query = "
                    SELECT
                        *
                    FROM
                        utilisateurs
                    WHERE
                        type_utilisateur = 'enseignant'
                    ORDER BY
                        nom, prenom
                ";

                // Afficher également tous les utilisateurs pour le débogage
                echo "<!-- Tous les utilisateurs dans la table: -->";
                $debug_stmt = $pdo->query("SELECT * FROM utilisateurs LIMIT 5");
                $debug_users = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($debug_users as $user) {
                    echo "<!-- Utilisateur ID: " . $user['id'] . " -->";
                    foreach ($user as $key => $value) {
                        echo "<!-- " . $key . ": " . $value . " -->";
                    }
                }

                // Afficher la structure de la table pour le débogage
                echo "<!-- Structure de la table utilisateurs: -->";
                $stmt = $pdo->query("DESCRIBE utilisateurs");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($columns as $column) {
                    echo "<!-- Colonne: " . $column['Field'] . " - Type: " . $column['Type'] . " -->";
                }
                echo "<!-- Requête SQL: " . htmlspecialchars($query) . " -->";

                $stmt = $pdo->prepare($query);
                $stmt->execute();
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo "<!-- Nombre de résultats: " . count($result) . " -->";

                // Afficher les résultats pour le débogage
                foreach ($result as $index => $row) {
                    echo "<!-- Résultat " . $index . ": -->";
                    foreach ($row as $key => $value) {
                        echo "<!-- " . $key . ": " . $value . " -->";
                    }
                }

                // Si aucun résultat n'est trouvé, vérifier s'il y a des utilisateurs dans la table
                if (count($result) == 0) {
                    echo "<!-- Aucun enseignant trouvé, vérification des utilisateurs existants -->";

                    // Vérifier s'il y a des utilisateurs dans la table
                    $stmt = $pdo->query("SELECT COUNT(*) FROM utilisateurs");
                    $totalUsers = $stmt->fetchColumn();
                    echo "<!-- Nombre total d'utilisateurs: " . $totalUsers . " -->";

                    if ($totalUsers > 0) {
                        // Il y a des utilisateurs mais aucun enseignant
                        echo "<!-- Des utilisateurs existent mais aucun n'est de type 'enseignant' -->";
                    } else {
                        // Aucun utilisateur dans la table
                        echo "<!-- Aucun utilisateur dans la table -->";
                    }
                }

                return $result;
            } else {
                echo "<!-- Colonne type_utilisateur n'existe pas -->";
            }
        } else {
            echo "<!-- Table utilisateurs n'existe pas -->";
        }

        // Si la table utilisateurs n'existe pas ou la colonne type_utilisateur n'existe pas,
        // essayer de récupérer les données de la table professeurs
        $stmt = $pdo->query("SHOW TABLES LIKE 'professeurs'");
        $profsTableExists = $stmt->rowCount() > 0;

        if ($profsTableExists) {
            echo "<!-- Table professeurs existe, utilisation comme fallback -->";
            $stmt = $pdo->query("SELECT * FROM professeurs ORDER BY nom, prenom");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            echo "<!-- Table professeurs n'existe pas non plus -->";
        }

        // Si aucune table n'existe, retourner un tableau vide
        echo "<!-- Aucune table valide trouvée, retour tableau vide -->";
        return [];
    } catch (PDOException $e) {
        // En cas d'erreur, retourner un tableau vide
        echo "<!-- Erreur dans getEnseignants: " . htmlspecialchars($e->getMessage()) . " -->";
        error_log("Erreur dans getEnseignants: " . $e->getMessage());
        return [];
    }
}

$professeurs = getEnseignants();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Professeurs</title>
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fc;
            height: 100vh;
            overflow: hidden;
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

        /* Sections du sidebar */
        .section-title {
            padding: 12px 15px;
            font-weight: 600;
            color: white;
            background: rgba(0, 0, 0, 0.15);
            border-radius: 5px;
            margin: 15px 0 5px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .section-title:hover {
            background: rgba(0, 0, 0, 0.25);
        }

        .section-title.coordinateur {
            background: rgba(0, 0, 0, 0.2);
            border-left: 4px solid var(--accent-color);
        }

        .section-title.enseignant {
            background: rgba(0, 0, 0, 0.2);
            border-left: 4px solid var(--teacher-color);
        }

        .section-title .arrow {
            margin-left: auto;
            transition: transform 0.3s;
        }

        .section-title .arrow.rotated {
            transform: rotate(180deg);
        }

        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .submenu.open {
            max-height: 500px;
        }

        /* Contenu principal */
        .main-content {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            background-color: #f8f9fc;
        }
        
        /* Titre de page */
        .page-title {
            color: var(--dark-purple);
            padding-bottom: 15px;
            margin-bottom: 25px;
            border-bottom: 2px solid rgba(65, 105, 225, 0.3);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-title i {
            font-size: 2rem;
            color: var(--primary-color);
        }

        /* Export buttons */
        .export-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .export-btn {
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .export-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
        }
        
        .export-excel {
            background: #1D6F42;
            color: white;
            border: none;
        }
        
        .export-print {
            background: var(--primary-color);
            color: white;
            border: none;
        }

        /* Table container */
        .table-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .table-container:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }

        .table-professeurs {
            margin-bottom: 0;
            width: 100%;
        }

        .table-professeurs thead {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-purple));
            color: white;
        }

        .table-professeurs th {
            font-weight: 500;
            padding: 16px 25px;
            border: none;
            font-size: 0.95rem;
        }

        .table-professeurs td {
            padding: 14px 25px;
            border-top: 1px solid rgba(0,0,0,0.05);
            vertical-align: middle;
        }

        .table-professeurs tbody tr:hover {
            background-color: rgba(106, 13, 173, 0.08);
        }

        /* Badges */
        .badge-permanent {
            background-color: #2e8b57;
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            font-size: 0.85rem;
        }

        .badge-vacataire {
            background-color: #daa520;
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            font-size: 0.85rem;
        }

        /* Message vide */
        .no-data {
            padding: 50px;
            text-align: center;
            background-color: rgba(255,255,255,0.7);
            border-radius: 10px;
            margin: 20px 0;
        }

        .no-data i {
            color: var(--primary-color);
            font-size: 60px;
            margin-bottom: 20px;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <!-- En-tête avec logo ENSAH -->
    <div class="header">
        <div class="header-left">
            <div class="header-logo">
                <img src="images/logo.png" alt="Logo ENSAH">
            </div>
            <h1>Gestion des Enseignants</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-envelope"></i>
                <span class="user-info-value">haddadi.mohammed@ensah.ma</span>
            </div>
            <div class="user-info">
                <i class="fas fa-building"></i>
                <span class="user-info-value">Département Informatique</span>
            </div>
        </div>
    </div>

    <!-- Conteneur principal -->
    <div class="main-container">
        <!-- Sidebar Moderne avec sections dépliables -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <img src="https://ui-avatars.com/api/?name=Département+Info&background=8a2be2&color=fff" alt="Chef Département">
                <h3>Département Informatique</h3>
            </div>
            
            <div class="sidebar-menu">
                <!-- Tableau de bord -->
                <div class="nav-item">
                    <a href="chef_dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Tableau de Bord</span>
                    </a>
                </div>
                
                <!-- Section Chef Département -->
                <div class="section-title coordinateur" id="chef-section">
                    <i class="fas fa-user-tie"></i>
                    <span>Chef Département</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </div>
                
                <div class="submenu" id="chef-menu">
                    <div class="nav-item">
                        <a href="gestion_modules.php" class="nav-link">
                            <i class="fas fa-book"></i>
                            <span>Unités d'enseignement</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="gestion_professeurs.php" class="nav-link active">
                            <i class="fas fa-users-cog"></i>
                            <span>Gestion Professeurs</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="affectation_ue.php" class="nav-link">
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
                </div>
                
                <!-- Section Enseignant -->
                <div class="section-title enseignant" id="enseignant-section">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Enseignant</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </div>
                
                <div class="submenu" id="enseignant-menu">
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
                    
                    <div class="nav-item">
                        <a href="reporting.php" class="nav-link">
                            <i class="fas fa-file-alt"></i>
                            <span>Reporting</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="import_export.php" class="nav-link">
                            <i class="fas fa-file-excel"></i>
                            <span>Import/Export</span>
                        </a>
                    </div>
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
            <h1 class="page-title">
                <i class="fas fa-chalkboard-teacher"></i>
                Liste des Enseignants
            </h1>
            
            <div class="export-buttons">
                <button id="export-excel" class="btn export-btn export-excel">
                    <i class="fas fa-file-excel"></i> Exporter vers Excel
                </button>
                <button id="export-print" class="btn export-btn export-print">
                    <i class="fas fa-print"></i> Imprimer
                </button>
            </div>

            <div class="table-container">
                <?php if (count($professeurs) > 0): ?>
                    <table class="table table-professeurs">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Département</th>
                                <th>Type</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($professeurs as $prof): ?>
                                <tr>
                                    <td><?= htmlspecialchars($prof['id'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($prof['nom'] ?? $prof['name'] ?? 'Non spécifié') ?></td>
                                    <td><?= htmlspecialchars($prof['prenom'] ?? $prof['firstname'] ?? 'Non spécifié') ?></td>
                                    <td><?= htmlspecialchars($prof['id_departement'] ?? $prof['departement_id'] ?? 'Non spécifié') ?></td>
                                    <td>
                                        <span class="badge badge-permanent">
                                            Enseignant
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($prof['email'] ?? 'Non spécifié') ?></td>
                                    
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-user-slash"></i>
                        <h3>Aucun enseignant trouvé</h3>
                        <p class="text-muted">La liste des enseignants est vide</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <!-- Scripts pour l'export -->
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialisation de DataTables avec les boutons d'export
            var table = $('.table-professeurs').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                },
                responsive: true,
                dom: '<"top"f>rt<"bottom"ip><"clear">',
                pageLength: 10,
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        title: 'Liste des Enseignants',
                        exportOptions: {
                            columns: ':visible'
                        },
                        className: 'btn-excel'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Imprimer',
                        title: 'Liste des Enseignants',
                        exportOptions: {
                            columns: ':visible'
                        },
                        className: 'btn-print'
                    }
                ],
                initComplete: function() {
                    $('.dataTables_filter input').addClass('form-control');
                    $('.dataTables_length select').addClass('form-select');
                }
            });

            // Lier les boutons personnalisés aux fonctions d'export
            $('#export-excel').on('click', function() {
                table.button('.buttons-excel').trigger();
            });

            $('#export-print').on('click', function() {
                table.button('.buttons-print').trigger();
            });
            
            // Gestion des sections dépliables
            document.querySelectorAll('.section-title').forEach(section => {
                section.addEventListener('click', function() {
                    const sectionId = this.id;
                    const menuId = sectionId.replace('section', 'menu');
                    const menu = document.getElementById(menuId);
                    const arrow = this.querySelector('.arrow');
                    
                    // Toggle menu
                    menu.classList.toggle('open');
                    
                    // Toggle arrow rotation
                    arrow.classList.toggle('rotated');
                });
            });
            
            // Ouvrir la section Chef Département par défaut
            document.getElementById('chef-menu').classList.add('open');
            document.querySelector('#chef-section .arrow').classList.add('rotated');
            
            // Fermer la section Enseignant par défaut
            document.getElementById('enseignant-menu').classList.remove('open');
        });
    </script>
</body>
</html>