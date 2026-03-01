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

// Variable pour l'année scolaire (à adapter selon votre système)
$annee_scolaire = "2023-2024";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Export Excel</title>
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Style pour la carte contenant le tableau */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .card-header h2 {
            margin: 0;
            font-size: 1.4rem;
            color: var(--dark-purple);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .search-box {
            position: relative;
            min-width: 250px;
        }
        
        .search-box input {
            padding: 8px 15px 8px 35px;
            border: 1px solid #ddd;
            border-radius: 30px;
            width: 100%;
            transition: all 0.3s;
        }
        
        .search-box input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(106, 13, 173, 0.1);
        }
        
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #777;
        }
        
        /* Style pour le tableau */
        .table-responsive {
            overflow-x: auto;
            padding: 0 20px;
        }
        
        .styled-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 0.95rem;
        }
        
        .styled-table thead tr {
            background-color: var(--primary-color);
            color: white;
        }
        
        .styled-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .styled-table th i {
            margin-right: 8px;
        }
        
        .styled-table tbody tr {
            border-bottom: 1px solid #eee;
            transition: all 0.2s;
        }
        
        .styled-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .styled-table tbody tr:last-child {
            border-bottom: 2px solid var(--primary-color);
        }
        
        .styled-table tbody tr:hover {
            background-color: #f0e6ff;
        }
        
        .styled-table td {
            padding: 15px;
            color: #555;
        }
        
        .styled-table td:first-child {
            font-weight: 500;
            color: #333;
        }
        
        /* Style pour les badges de comptage */
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            background-color: #e6e6fa;
            color: var(--primary-color);
            font-weight: 600;
            font-size: 0.85rem;
            min-width: 40px;
            text-align: center;
        }
        
        /* Style pour les boutons d'export */
        .btn-exporter {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .btn-exporter:hover {
            background-color: var(--dark-purple);
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        /* Pied de tableau */
        .table-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 25px;
            border-top: 1px solid #eee;
            background-color: #fafafa;
        }
        
        .btn-export-all {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            background-color: var(--secondary-color);
            color: white;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .btn-export-all:hover {
            background-color: var(--dark-purple);
        }
        
        .table-info {
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-box {
                width: 100%;
            }
            
            .table-footer {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .styled-table {
                font-size: 0.85rem;
            }
            
            .styled-table th, 
            .styled-table td {
                padding: 10px 8px;
            }
        }
    </style>
    <!-- Lien Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <!-- Header / Sidebar -->
    <div class="header">
        <div class="header-left">
            <div class="header-logo">
                <img src="https://upload.wikimedia.org/wikipedia/fr/thumb/5/5f/Logo_ENSAH.svg/1200px-Logo_ENSAH.svg.png" alt="Logo ENSAH">
            </div>
            <h1>Tableau de Bord Coordinateur</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-calendar-alt"></i>
                <span class="user-info-label">Année :</span>
                <span class="user-info-value"><?= htmlspecialchars($annee_scolaire) ?></span>
            </div>
        </div>
    </div>

    <!-- Conteneur principal -->
    <div class="main-container">
        <!-- Sidebar Moderne avec sections dépliables -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <img src="https://ui-avatars.com/api/?name=Coordinateur&background=8a2be2&color=fff" alt="Coordinateur">
                <h3>Coordinateur & Enseignant</h3>
            </div>
            
            <div class="sidebar-menu">
                <!-- Tableau de bord -->
                <div class="nav-item">
                    <a href="#" class="nav-link active">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Tableau de Bord</span>
                    </a>
                </div>
                
                <!-- Section Coordinateur -->
                <div class="section-title coordinateur" id="coord-section">
                    <i class="fas fa-user-tie"></i>
                    <span>Coordinateur</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </div>

                <div class="submenu" id="coord-menu">
                    <div class="nav-item">
                        <a href="gerer_groupes.php" class="nav-link">
                            <i class="fas fa-users"></i>
                            <span>Gérer les groupes</span>
                        </a>
                    </div>

                    <div class="nav-item">
                        <a href="gestion_unites_enseignements.php" class="nav-link">
                            <i class="fas fa-book"></i>
                            <span>Unités d'enseignement</span>
                        </a>
                    </div>

                    <div class="nav-item">
                        <a href="affectation_vacataire.php" class="nav-link">
                            <i class="fas fa-user-tie"></i>
                            <span>Affectation vacataires</span>
                        </a>
                    </div>

                    <div class="nav-item">
                        <a href="creer_vacataire.php" class="nav-link">
                            <i class="fas fa-book"></i>
                            <span>créer compte vacataire</span>
                        </a>
                    </div>

                    <div class="nav-item">
                        <a href="historique.php" class="nav-link">
                            <i class="fas fa-file-excel"></i>
                            <span>historique des années passées</span>
                        </a>
                    </div>

                    <div class="nav-item">
                        <a href="Export_Exel.php" class="nav-link active">
                            <i class="fas fa-file-excel"></i>
                            <span>Extraire en Excel</span>
                        </a>
                    </div>

                    <div class="nav-item">
                        <a href="gerer_emplois_temps.php" class="nav-link">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Emplois du temps</span>
                        </a>
                    </div>
                </div>
                
                <!-- Section Enseignant -->
                <div class="section-title enseignant" id="teacher-section">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Enseignant</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </div>
                
                <div class="submenu" id="teacher-menu">
                    <div class="nav-item">
                        <a href="Affichage_liste_UE.php" class="nav-link">
                            <i class="fas fa-book-reader"></i>
                            <span>Listes UE</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="souhaits_enseignants.php" class="nav-link">
                            <i class="fas fa-calendar-check"></i>
                            <span>Souhaits enseignants</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="Calcul_automatique_charge_horaire.php" class="nav-link">
                            <i class="fas fa-file-signature"></i>
                            <span>Charge horaire</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="Notification.php" class="nav-link">
                            <i class="fas fa-tasks"></i>
                            <span>Notifications</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="Consulter_modules.php" class="nav-link">
                            <i class="fas fa-comments"></i>
                            <span>Modules assurés</span>
                        </a>
                    </div>

                    <div class="nav-item">
                        <a href="Uploader_notes.php" class="nav-link">
                            <i class="fas fa-comments"></i>
                            <span>Upload notes</span>
                        </a>
                    </div>

                    <div class="nav-item">
                        <a href="historique.php" class="nav-link">
                            <i class="fas fa-comments"></i>
                            <span>Historique</span>
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
            <div class="container">
                <h1><i class="fas fa-file-export"></i> Export des données en Excel</h1>
                
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-table"></i> Sélectionnez la table à exporter</h2>
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="tableSearch" placeholder="Rechercher une table...">
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="styled-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-table"></i> Table</th>
                                    <th><i class="fas fa-info-circle"></i> Description</th>
                                    <th><i class="fas fa-list-ol"></i> Enregistrements</th>
                                    <th><i class="fas fa-cogs"></i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><i class="fas fa-users-class"></i> Groupes</td>
                                    <td>Liste des groupes TP/TD</td>
                                    <td><span class="badge"><?php echo getTableCount($conn, 'groupes'); ?></span></td>
                                    <td>
                                        <a href="?export=1&table=groupes" class="btn-exporter">
                                            <i class="fas fa-file-export"></i> Exporter
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-user-graduate"></i> Étudiants</td>
                                    <td>Liste des étudiants</td>
                                    <td><span class="badge"><?php echo getTableCount($conn, 'etudiants'); ?></span></td>
                                    <td>
                                        <a href="?export=1&table=etudiants" class="btn-exporter">
                                            <i class="fas fa-file-export"></i> Exporter
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-chalkboard-teacher"></i> Enseignants</td>
                                    <td>Liste des enseignants</td>
                                    <td><span class="badge"><?php echo getTableCount($conn, 'enseignants'); ?></span></td>
                                    <td>
                                        <a href="?export=1&table=enseignants" class="btn-exporter">
                                            <i class="fas fa-file-export"></i> Exporter
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-user-tie"></i> Vacataires</td>
                                    <td>Liste des vacataires</td>
                                    <td><span class="badge"><?php echo getTableCount($conn, 'vacataires'); ?></span></td>
                                    <td>
                                        <a href="?export=1&table=vacataires" class="btn-exporter">
                                            <i class="fas fa-file-export"></i> Exporter
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-book"></i> Matières</td>
                                    <td>Liste des matières</td>
                                    <td><span class="badge"><?php echo getTableCount($conn, 'matieres'); ?></span></td>
                                    <td>
                                        <a href="?export=1&table=matieres" class="btn-exporter">
                                            <i class="fas fa-file-export"></i> Exporter
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-graduation-cap"></i> Unités d'enseignement</td>
                                    <td>Liste des UE</td>
                                    <td><span class="badge"><?php echo getTableCount($conn, 'unites_enseignements'); ?></span></td>
                                    <td>
                                        <a href="?export=1&table=unites_enseignements" class="btn-exporter">
                                            <i class="fas fa-file-export"></i> Exporter
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-link"></i> Affectations vacataires</td>
                                    <td>Affectations des vacataires</td>
                                    <td><span class="badge"><?php echo getTableCount($conn, 'affectations_vacataires'); ?></span></td>
                                    <td>
                                        <a href="?export=1&table=affectations_vacataires" class="btn-exporter">
                                            <i class="fas fa-file-export"></i> Exporter
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-calendar-alt"></i> Créneaux</td>
                                    <td>Créneaux horaires</td>
                                    <td><span class="badge"><?php echo getTableCount($conn, 'creneaux'); ?></span></td>
                                    <td>
                                        <a href="?export=1&table=creneaux" class="btn-exporter">
                                            <i class="fas fa-file-export"></i> Exporter
                                        </a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="table-footer">
                        <div class="export-all">
                            <a href="#" class="btn-export-all">
                                <i class="fas fa-file-archive"></i> Exporter toutes les tables
                            </a>
                        </div>
                        <div class="table-info">
                            Affichage de <span id="rowCount">8</span> tables
                        </div>
                    </div>
                </div>
            </div>
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

        // Fonctionnalité de recherche dans le tableau
        document.getElementById('tableSearch').addEventListener('input', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('.styled-table tbody tr');
            let visibleRows = 0;
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchValue)) {
                    row.style.display = '';
                    visibleRows++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            document.getElementById('rowCount').textContent = visibleRows;
        });

        // Gestion des menus dépliables dans le sidebar
        document.getElementById('coord-section').addEventListener('click', function() {
            this.querySelector('.arrow').classList.toggle('rotated');
            document.getElementById('coord-menu').classList.toggle('open');
        });

        document.getElementById('teacher-section').addEventListener('click', function() {
            this.querySelector('.arrow').classList.toggle('rotated');
            document.getElementById('teacher-menu').classList.toggle('open');
        });

        // Bouton pour exporter toutes les tables (fonctionnalité à implémenter)
        document.querySelector('.btn-export-all').addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Voulez-vous vraiment exporter toutes les tables ? Cette opération peut prendre quelques secondes.')) {
                // Implémenter ici la logique pour exporter toutes les tables
                alert('Export de toutes les tables en cours...');
            }
        });
    </script>
</body>
</html>