<?php
// Connexion à la base de données
$conn = new mysqli("localhost", "root", "", "gestion_coordinteur");

// Définir des variables pour les messages d'erreur et de succès
$error_message = "";
$success_message = "";

// Vérifier la connexion
if ($conn->connect_error) {
    $error_message = "Erreur de connexion à la base de données : " . $conn->connect_error;
}

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $id_ue = $_POST['id_ue'];
        $session = $_POST['session'];
        $notes = $_POST['notes']; // tableau associatif : [id_etudiant => note]

        // Vérifier si la table notes_etudiants existe
        $tables_check = $conn->query("SHOW TABLES LIKE 'notes_etudiants'");
        $notes_table_exists = $tables_check->num_rows > 0;

        if (!$notes_table_exists) {
            // Créer la table si elle n'existe pas
            $conn->query("CREATE TABLE IF NOT EXISTS notes_etudiants (
                id_note INT AUTO_INCREMENT PRIMARY KEY,
                id_etudiant INT NOT NULL,
                id_ue INT NOT NULL,
                session VARCHAR(20) NOT NULL,
                note DECIMAL(5,2),
                date_saisie DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        }

        // Enregistrer les notes dans la base de données
        $success = true;
        foreach ($notes as $id_etudiant => $note) {
            $note = floatval($note);
            $stmt = $conn->prepare("INSERT INTO notes_etudiants (id_etudiant, id_ue, session, note, date_saisie) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("iisd", $id_etudiant, $id_ue, $session, $note);
            if (!$stmt->execute()) {
                $success = false;
                $error_message = "Erreur lors de l'enregistrement des notes: " . $stmt->error;
            }
            $stmt->close();
        }

        if ($success) {
            $success_message = "Les notes ont été enregistrées avec succès.";
        }
    } catch (Exception $e) {
        $error_message = "Une erreur est survenue: " . $e->getMessage();
    }

    // Générer le PDF directement en HTML et JavaScript (pas besoin de bibliothèque externe)
    try {
        // Récupérer les informations sur l'UE
        $nom_ue = "UE #".$id_ue;

        // Essayer de récupérer le nom de l'UE si la table existe
        $tables_check = $conn->query("SHOW TABLES LIKE 'unites_enseignements'");
        if ($tables_check->num_rows > 0) {
            // Vérifier si la colonne nom_ue existe
            $columns_check = $conn->query("SHOW COLUMNS FROM unites_enseignements LIKE 'nom_ue'");
            if ($columns_check->num_rows > 0) {
                $stmt_ue = $conn->prepare("SELECT nom_ue FROM unites_enseignements WHERE id_ue = ?");
                $stmt_ue->bind_param("i", $id_ue);
                $stmt_ue->execute();
                $result_ue = $stmt_ue->get_result();
                $ue_info = $result_ue->fetch_assoc();
                if ($ue_info) {
                    $nom_ue = $ue_info['nom_ue'];
                }
                $stmt_ue->close();
            } else {
                // Essayer avec une jointure
                $tables_check = $conn->query("SHOW TABLES LIKE 'matieres'");
                if ($tables_check->num_rows > 0) {
                    $stmt_ue = $conn->prepare("SELECT m.nom AS nom_ue
                                              FROM unites_enseignements ue
                                              JOIN matieres m ON ue.id_matiere = m.id_matiere
                                              WHERE ue.id_ue = ?");
                    $stmt_ue->bind_param("i", $id_ue);
                    $stmt_ue->execute();
                    $result_ue = $stmt_ue->get_result();
                    $ue_info = $result_ue->fetch_assoc();
                    if ($ue_info) {
                        $nom_ue = $ue_info['nom_ue'];
                    }
                    $stmt_ue->close();
                }
            }
        }

        // Récupérer les notes des étudiants
        $notes_data = [];

        // Vérifier si la table notes_etudiants existe
        $tables_check = $conn->query("SHOW TABLES LIKE 'notes_etudiants'");
        if ($tables_check->num_rows > 0) {
            // Vérifier si la table etudiants existe
            $tables_check = $conn->query("SHOW TABLES LIKE 'etudiants'");
            if ($tables_check->num_rows > 0) {
                // Vérifier si la colonne nom_etudiant existe
                $columns_check = $conn->query("SHOW COLUMNS FROM etudiants LIKE 'nom_etudiant'");
                if ($columns_check->num_rows > 0) {
                    $sql = "SELECT e.id_etudiant, e.nom_etudiant, n.note
                            FROM notes_etudiants n
                            JOIN etudiants e ON n.id_etudiant = e.id_etudiant
                            WHERE n.session = ? AND n.id_ue = ?";
                } else {
                    // Vérifier si les colonnes nom et prenom existent
                    $columns_check_nom = $conn->query("SHOW COLUMNS FROM etudiants LIKE 'nom'");
                    $columns_check_prenom = $conn->query("SHOW COLUMNS FROM etudiants LIKE 'prenom'");
                    if ($columns_check_nom->num_rows > 0 && $columns_check_prenom->num_rows > 0) {
                        $sql = "SELECT e.id_etudiant, CONCAT(e.nom, ' ', e.prenom) AS nom_etudiant, n.note
                                FROM notes_etudiants n
                                JOIN etudiants e ON n.id_etudiant = e.id_etudiant
                                WHERE n.session = ? AND n.id_ue = ?";
                    } else {
                        $sql = "SELECT n.id_etudiant, CONCAT('Étudiant #', n.id_etudiant) AS nom_etudiant, n.note
                                FROM notes_etudiants n
                                WHERE n.session = ? AND n.id_ue = ?";
                    }
                }

                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $session, $id_ue);
                $stmt->execute();
                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {
                    $notes_data[] = $row;
                }

                $stmt->close();
            }
        }

        // Générer le HTML pour le PDF
        $css = "
            :root {
                --primary-blue: #1E90FF;
                --primary-magenta: magenta;
                --blue-transparent: rgba(30, 144, 255, 0.3);
            }
            body {
                font-family: Segoe UI, Tahoma, Geneva, Verdana, sans-serif;
                margin: 20px;
                color: #333;
                background-color: #f9f9f9;
            }
            h1, h2 {
                text-align: center;
                color: var(--primary-blue);
            }
            h1 {
                font-size: 28px;
                margin-bottom: 5px;
                text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
            }
            h2 {
                font-size: 20px;
                margin-top: 5px;
                color: #555;
            }
            .header {
                margin-bottom: 30px;
                padding-bottom: 15px;
                border-bottom: 2px solid var(--primary-magenta);
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                border-radius: 5px;
                overflow: hidden;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 12px;
                text-align: center;
            }
            th {
                background-color: var(--primary-blue);
                color: white;
                font-weight: bold;
            }
            tr:nth-child(even) {
                background-color: #f2f2f2;
            }
            tr:hover {
                background-color: #e9f5ff;
            }
            .footer {
                margin-top: 50px;
                text-align: right;
                color: #555;
                font-style: italic;
                border-top: 1px solid #ddd;
                padding-top: 15px;
            }
            .signature-line {
                display: inline-block;
                width: 200px;
                border-bottom: 1px solid #555;
                margin-left: 10px;
            }
            @media print {
                body {
                    margin: 0;
                    background-color: white;
                }
                button { display: none; }
                .header {
                    border-bottom-color: #333;
                }
            }
        ";

        $session_title = htmlspecialchars(ucfirst($session));
        $ue_title = htmlspecialchars($nom_ue);

        // Début du HTML
        $html_content = "<!DOCTYPE html>
        <html>
        <head>
            <meta charset=\"UTF-8\">
            <title>Relevé de Notes - Session {$session_title}</title>
            <style>{$css}</style>
        </head>
        <body>
            <div class=\"header\">
                <h1>Relevé de Notes</h1>
                <h2>Session: {$session_title}</h2>
                <h2>Unité d'Enseignement: {$ue_title}</h2>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>ID Étudiant</th>
                        <th>Nom Étudiant</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>";

        // Ajouter les données des étudiants
        foreach ($notes_data as $note) {
            $html_content .= '
                    <tr>
                        <td>' . htmlspecialchars($note['id_etudiant']) . '</td>
                        <td>' . htmlspecialchars($note['nom_etudiant']) . '</td>
                        <td>' . htmlspecialchars($note['note']) . '</td>
                    </tr>';
        }

        // Si aucune donnée, ajouter un message
        if (empty($notes_data)) {
            $html_content .= '
                    <tr>
                        <td colspan=\"3\">Aucune note enregistrée pour cette session et cette UE.</td>
                    </tr>';
        }

        // Fermeture du tableau et ajout du pied de page
        $date_generation = date('d/m/Y');
        $html_content .= "
                </tbody>
            </table>

            <div class=\"footer\">
                <p>Document généré le {$date_generation}</p>
                <p>Signature de l'enseignant: <span class=\"signature-line\"></span></p>
            </div>

            <div style=\"text-align: center; margin-top: 30px;\">
                <button onclick=\"window.print()\">Imprimer ce document</button>
            </div>

            <script>
                // Imprimer automatiquement
                window.onload = function() {
                    window.print();
                };
            </script>
        </body>
        </html>";

        // Enregistrer le HTML dans un fichier temporaire
        $temp_file = 'notes_session_' . $session . '_ue' . $id_ue . '_' . time() . '.html';
        file_put_contents($temp_file, $html_content);

        // Rediriger vers le fichier HTML
        header('Location: ' . $temp_file);
        exit;

    } catch (Exception $e) {
        $error_message = "Erreur lors de la génération du PDF: " . $e->getMessage();
    }
}

// Vérifier si les tables existent
$tables_check = $conn->query("SHOW TABLES LIKE 'unites_enseignements'");
$ue_table_exists = $tables_check->num_rows > 0;

$tables_check = $conn->query("SHOW TABLES LIKE 'etudiants'");
$etudiants_table_exists = $tables_check->num_rows > 0;

// Récupérer les UE avec plus d'informations
if ($ue_table_exists) {
    // Vérifier si la colonne nom_ue existe
    $columns_check = $conn->query("SHOW COLUMNS FROM unites_enseignements LIKE 'nom_ue'");
    if ($columns_check->num_rows > 0) {
        $res_ue = $conn->query("SELECT id_ue, nom_ue FROM unites_enseignements ORDER BY nom_ue");
    } else {
        // Si la colonne nom_ue n'existe pas, utiliser une jointure avec la table matieres
        $res_ue = $conn->query("SELECT ue.id_ue, m.nom AS nom_ue
                               FROM unites_enseignements ue
                               JOIN matieres m ON ue.id_matiere = m.id_matiere
                               ORDER BY m.nom");
    }
} else {
    // Créer des données fictives pour permettre de tester le formulaire
    $res_ue = $conn->query("SELECT 1 as id_ue, 'Exemple UE 1' as nom_ue FROM DUAL UNION SELECT 2 as id_ue, 'Exemple UE 2' as nom_ue FROM DUAL");
    $error_message = "Attention: Aucune unité d'enseignement trouvée dans la base de données. Des exemples sont affichés pour tester le formulaire.";
}

// Récupérer les étudiants avec plus d'informations
if ($etudiants_table_exists) {
    // Vérifier si la colonne nom_etudiant existe
    $columns_check = $conn->query("SHOW COLUMNS FROM etudiants LIKE 'nom_etudiant'");
    if ($columns_check->num_rows > 0) {
        $res_etudiants = $conn->query("SELECT id_etudiant, nom_etudiant FROM etudiants ORDER BY nom_etudiant");
    } else {
        // Si la colonne nom_etudiant n'existe pas, utiliser les colonnes nom et prenom
        $res_etudiants = $conn->query("SELECT id_etudiant, CONCAT(nom, ' ', prenom) AS nom_etudiant FROM etudiants ORDER BY nom, prenom");
    }
} else {
    // Créer des données fictives pour permettre de tester le formulaire
    $res_etudiants = $conn->query("SELECT 1 as id_etudiant, 'Jean Dupont' as nom_etudiant FROM DUAL
                                  UNION SELECT 2 as id_etudiant, 'Marie Martin' as nom_etudiant FROM DUAL
                                  UNION SELECT 3 as id_etudiant, 'Pierre Durand' as nom_etudiant FROM DUAL");
    if (empty($error_message)) {
        $error_message = "Attention: Aucun étudiant trouvé dans la base de données. Des exemples sont affichés pour tester le formulaire.";
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Uploader les Notes</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
        --primary-color: #6a0dad;
        --secondary-color: #8a2be2;
        --light-purple: #e6e6fa;
        --dark-purple: #4b0082;
        --accent-color: #00bfff;
        --success-green: #28a745;
        --warning-orange: #ffc107;
        --danger-red: #dc3545;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f0f0f5;
        height: 100vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    /* Header */
    .header {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
        color: white;
        padding: 15px 25px;
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

    .header img {
        width: 45px;
        height: 45px;
        filter: drop-shadow(0 0 5px rgba(255, 255, 255, 0.5));
    }

    .header h1 {
        font-size: 1.5rem;
        font-weight: 600;
        margin: 0;
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
        width: 250px;
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

    /* Contenu principal */
    .main-content {
        flex: 1;
        padding: 25px;
        overflow-y: auto;
        background-color: #f8f9fc;
    }

    /* Section de gestion */
    .dashboard-section {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        margin-bottom: 30px;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .section-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--dark-purple);
        margin: 0;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th {
        text-align: left;
        padding: 12px 15px;
        background: #f8f9fc;
        color: #555;
        font-weight: 600;
        border-bottom: 2px solid #eee;
    }

    td {
        padding: 12px 15px;
        border-bottom: 1px solid #eee;
        color: #333;
    }

    tr:hover td {
        background: #f8f9ff;
    }

    .badge {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }

    .badge-danger {
        background: #ffebee;
        color: var(--danger-red);
    }

    .badge-success {
        background: #e6f7ee;
        color: var(--success-green);
    }

    .no-data {
        text-align: center;
        padding: 3rem 2rem;
        color: #666;
        font-style: italic;
        background: #f9f9f9;
        border-radius: 12px;
        margin: 2rem 0;
    }

    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        text-align: center;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        border-left: 4px solid var(--primary-color);
    }

    .stat-card.ok {
        border-left-color: var(--success-green);
    }

    .stat-card.danger {
        border-left-color: var(--danger-red);
    }

    .stat-card h3 {
        margin: 0 0 0.5rem 0;
        font-size: 1rem;
        color: #666;
    }

    .stat-card .value {
        font-size: 2rem;
        font-weight: bold;
        margin: 0;
        color: var(--dark-purple);
    }
    
    .profile-circle {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        border: 3px solid white;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .initials {
        color: white;
        font-size: 28px;
        font-weight: bold;
        text-transform: uppercase;
    }

    /* Styles spécifiques au formulaire */
    form {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        margin-bottom: 30px;
    }

    input, select {
        width: 100%;
        padding: 10px;
        margin: 8px 0;
        background: #f8f9fc;
        border: 1px solid #ddd;
        border-radius: 5px;
        color: #333;
    }

    input[type="number"] {
        width: 80px;
        text-align: center;
    }

    .form-section {
        margin-bottom: 20px;
    }

    .info-box {
        background-color: rgba(30, 144, 255, 0.1);
        padding: 15px;
        border-radius: 5px;
        margin: 15px 0;
        border-left: 4px solid var(--primary-blue);
    }

    .error-message {
        background-color: rgba(211, 47, 47, 0.2);
        color: #ff6b6b;
        padding: 15px;
        border-radius: 5px;
        margin: 15px auto;
        max-width: 800px;
        border-left: 4px solid #d32f2f;
    }

    .success-message {
        background-color: rgba(56, 142, 60, 0.2);
        color: #69f0ae;
        padding: 15px;
        border-radius: 5px;
        margin: 15px auto;
        max-width: 800px;
        border-left: 4px solid #388e3c;
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
        
        .header-left, .header-right {
            width: 100%;
            justify-content: center;
        }
        
        .main-container {
            flex-direction: column;
        }
        
        .sidebar {
            width: 100%;
            height: auto;
            padding: 15px 0;
        }
        
        .sidebar-menu {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
        }
        
        .nav-item {
            margin-bottom: 0;
        }
        
        .nav-link {
            padding: 8px 12px;
        }
    }
  </style>
</head>
<body>

<header class="header">
    <div class="header-left">
        <img src="images/logo.png" alt="Logo">
        <h1>Uploader les Notes</h1>
    </div>
    <div class="header-right">
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <div>
                <div class="user-info-label">Enseignant</div>
                <div class="user-info-value">Fatima Zohra El hamdani</div>
            </div>
        </div>
    </div>
</header>

<div class="main-container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="profile-circle">
                <div class="initials">EN</div>
            </div>
            <h3> Enseignant</h3>
        </div>
        <nav class="sidebar-menu">
            <ul class="nav flex-column">
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
                    <a href="Uploader_notes.php" class="nav-link active">
                        <i class="fas fa-upload"></i>
                        <span>Upload notes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="historique.php" class="nav-link">
                        <i class="fas fa-history"></i>
                        <span>Historique</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?logout=true" class="nav-link logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Déconnexion</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <div class="dashboard-section">
            <div class="section-header">
                <h2 class="section-title">Saisie des Notes des Étudiants</h2>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="success-message">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-section">
                    <label for="id_ue">Unité d'Enseignement :</label>
                    <select name="id_ue" required>
                        <?php
                        while ($row = $res_ue->fetch_assoc()) {
                            echo "<option value='{$row['id_ue']}'>{$row['nom_ue']} (UE n°{$row['id_ue']})</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-section">
                    <label for="session">Session :</label>
                    <select name="session" required>
                        <option value="normale">Normale</option>
                        <option value="rattrapage">Rattrapage</option>
                    </select>
                </div>

                <div class="info-box">
                    <p><i>Remplissez les notes pour chaque étudiant. Après avoir cliqué sur "Enregistrer les notes", un PDF sera automatiquement généré et téléchargé.</i></p>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>ID Étudiant</th>
                            <th>Nom Étudiant</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        while ($row = $res_etudiants->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>{$row['id_etudiant']}</td>";
                            echo "<td>{$row['nom_etudiant']}</td>";
                            echo "<td><input type='number' step='0.01' min='0' max='20' name='notes[{$row['id_etudiant']}]' required></td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>

                <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                    <button type="submit" name="action" value="enregistrer" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer les notes
                    </button>
                    <button type="button" id="test-pdf-btn" class="btn btn-secondary">
                        <i class="fas fa-file-pdf"></i> Tester le PDF (sans enregistrer)
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Script pour gérer le téléchargement du PDF
    document.querySelector('form').addEventListener('submit', function(e) {
        // Le formulaire sera soumis normalement, le serveur générera le PDF
    });

    // Script pour tester le PDF sans enregistrer les données
    document.getElementById('test-pdf-btn').addEventListener('click', function() {
        const ueSelect = document.querySelector('select[name="id_ue"]');
        const sessionSelect = document.querySelector('select[name="session"]');
        const ueText = ueSelect.options[ueSelect.selectedIndex].text;
        const sessionText = sessionSelect.options[sessionSelect.selectedIndex].text;

        const css = `
            :root {
                --primary-blue: #1E90FF;
                --primary-magenta: magenta;
                --blue-transparent: rgba(30, 144, 255, 0.3);
            }
            body {
                font-family: Segoe UI, Tahoma, Geneva, Verdana, sans-serif;
                margin: 20px;
                color: #333;
                background-color: #f9f9f9;
            }
            h1, h2 {
                text-align: center;
                color: var(--primary-blue);
            }
            h1 {
                font-size: 28px;
                margin-bottom: 5px;
                text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
            }
            h2 {
                font-size: 20px;
                margin-top: 5px;
                color: #555;
            }
            .header {
                margin-bottom: 30px;
                padding-bottom: 15px;
                border-bottom: 2px solid var(--primary-magenta);
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                border-radius: 5px;
                overflow: hidden;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 12px;
                text-align: center;
            }
            th {
                background-color: var(--primary-blue);
                color: white;
                font-weight: bold;
            }
            tr:nth-child(even) {
                background-color: #f2f2f2;
            }
            tr:hover {
                background-color: #e9f5ff;
            }
            .footer {
                margin-top: 50px;
                text-align: right;
                color: #555;
                font-style: italic;
                border-top: 1px solid #ddd;
                padding-top: 15px;
            }
            .signature-line {
                display: inline-block;
                width: 200px;
                border-bottom: 1px solid #555;
                margin-left: 10px;
            }
            .watermark {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-45deg);
                font-size: 100px;
                color: rgba(30, 144, 255, 0.1);
                z-index: -1;
                font-weight: bold;
            }
            button {
                background-color: var(--primary-blue);
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-weight: bold;
            }
            button:hover {
                background-color: #0056b3;
            }
            @media print {
                body {
                    margin: 0;
                    background-color: white;
                }
                button { display: none; }
                .header {
                    border-bottom-color: #333;
                }
            }
        `;

        let htmlContent = `
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Relevé de Notes - Test</title>
                <style>${css}</style>
            </head>
            <body>
                <div class="header">
                    <h1>Relevé de Notes (Aperçu)</h1>
                    <h2>Session: ${sessionText}</h2>
                    <h2>Unité d'Enseignement: ${ueText}</h2>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>ID Étudiant</th>
                            <th>Nom Étudiant</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        const rows = document.querySelectorAll('tbody tr');
        let hasData = false;

        rows.forEach(row => {
            const id = row.cells[0].textContent.trim();
            const name = row.cells[1].textContent.trim();
            const noteInput = row.querySelector('input[type="number"]');
            const note = noteInput && noteInput.value ? noteInput.value : '-';

            if (note !== '-') {
                hasData = true;
            }

            htmlContent += `
                        <tr>
                            <td>${id}</td>
                            <td>${name}</td>
                            <td>${note}</td>
                        </tr>
            `;
        });

        if (!hasData) {
            htmlContent += `
                        <tr>
                            <td colspan="3" style="font-style: italic; color: #777;">
                                Aucune note n'a été saisie. Ceci est un aperçu du document.
                            </td>
                        </tr>
            `;
        }

       htmlContent += `
                    </tbody>
                </table>

                <div class="footer">
                    <p>Document généré le ${new Date().toLocaleDateString('fr-FR')}</p>
                    <p>Signature de l'enseignant: <span class="signature-line"></span></p>
                </div>

                <div style="text-align: center; margin-top: 30px;">
                    <button onclick="window.print()" style="padding: 8px 16px; background: #1E90FF; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        Imprimer cet aperçu
                    </button>
                </div>
            </body>
            </html>
        `;

        try {
            const win = window.open('', '_blank');
            if (win) {
                win.document.open();
                win.document.write(htmlContent);
                win.document.close();
                
                // Focus sur la nouvelle fenêtre
                win.focus();
            } else {
                throw new Error("Popup blocked");
            }
        } catch (e) {
            alert("Le pop-up a été bloqué. Veuillez autoriser les pop-ups pour ce site.");
            // Fallback : afficher le contenu dans la même fenêtre
            document.write(htmlContent);
        }
    });

    // Animation des liens de la sidebar - version améliorée
    document.querySelectorAll('.nav-link').forEach(link => {
        // Appliquer la transition une seule fois
        link.style.transition = 'transform 0.3s ease';
        
        link.addEventListener('mouseenter', () => {
            link.style.transform = 'translateX(5px)';
        });

        link.addEventListener('mouseleave', () => {
            link.style.transform = 'translateX(0)';
        });
    });
</script>
</body>
</html>