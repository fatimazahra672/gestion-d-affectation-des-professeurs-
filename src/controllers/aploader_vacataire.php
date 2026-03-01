<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli("localhost", "root", "", "gestion_coordinteur");
$error_message = "";
$success_message = "";

if ($conn->connect_error) {
    $error_message = "Erreur de connexion : " . $conn->connect_error;
}

$id_vacataire = $_SESSION['id_vacataire'] ?? 1;

// Récupérer les informations du vacataire
$sql_vacataire = "SELECT * FROM vacataires WHERE id_vacataire = ?";
$stmt_vacataire = $conn->prepare($sql_vacataire);
$stmt_vacataire->bind_param("i", $id_vacataire);
$stmt_vacataire->execute();
$result_vacataire = $stmt_vacataire->get_result();
$vacataire = $result_vacataire->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $id_ue = $_POST['id_ue'];
        $session = $_POST['session'];
        $notes = $_POST['notes'];

        $tables_check = $conn->query("SHOW TABLES LIKE 'notes_etudiants'");
        if (!$tables_check->num_rows) {
            $conn->query("CREATE TABLE IF NOT EXISTS notes_etudiants (
                id_note INT AUTO_INCREMENT PRIMARY KEY,
                id_etudiant INT NOT NULL,
                id_ue INT NOT NULL,
                session VARCHAR(20) NOT NULL,
                note DECIMAL(5,2),
                date_saisie DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        }

        $success = true;
        foreach ($notes as $id_etudiant => $note) {
            $note = floatval($note);
            $stmt = $conn->prepare("INSERT INTO notes_etudiants VALUES (NULL, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iisd", $id_etudiant, $id_ue, $session, $note);
            if (!$stmt->execute()) {
                $success = false;
                $error_message = "Erreur : " . $stmt->error;
            }
            $stmt->close();
        }

        if ($success) $success_message = "Notes enregistrées !";
        
    } catch (Exception $e) {
        $error_message = "Erreur : " . $e->getMessage();
    }
}

$ues_assignees = [];
$tables_check = $conn->query("SHOW TABLES LIKE 'vacataires_ues'");
if ($tables_check->num_rows) {
    $sql = "SELECT ue.id_ue, m.nom, ue.type_enseignement 
            FROM vacataires_ues vu
            JOIN unites_enseignements ue ON vu.id_ue = ue.id_ue
            JOIN matieres m ON ue.id_matiere = m.id_matiere
            WHERE vu.id_vacataire = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_vacataire);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $ues_assignees[] = $row;
    $stmt->close();
} else {
    $error_message = "Table vacataires_ues manquante !";
}

$etudiants = [];
$tables_check = $conn->query("SHOW TABLES LIKE 'etudiants'");
if ($tables_check->num_rows) {
    $sql = "SELECT id_etudiant, CONCAT(nom, ' ', prenom) AS nom 
            FROM etudiants 
            ORDER BY nom";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) $etudiants[] = $row;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saisie des notes - Vacataire</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #6a0dad;
            --secondary-color: #8a2be2;
            --light-purple: #e6e6fa;
            --dark-purple: #4b0082;
            --accent-color: #00bfff;
            --teacher-color: #3498db;
            --teacher-dark: #2980b9;
            --danger-color: #ff4757;
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

        /* Sidebar */
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
            margin-top: auto;
            color: white;
            border: none;
            padding: 12px 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            width: calc(100% - 30px);
            margin: 20px 15px 0;
            text-decoration: none;
        }

        .logout-btn:hover {
            background: rgba(255, 71, 87, 0.3);
            transform: translateX(5px);
        }

        /* Contenu principal */
        .main-content {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            background-color: #f8f9fc;
        }

        /* Section de saisie */
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .section-header {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-purple);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .section-header i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
        }

        .form-control {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(106, 13, 173, 0.25);
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table thead th {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 15px;
            text-align: left;
        }

        .table tbody td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }

        .table tbody tr:hover {
            background-color: #f5f5f5;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background-color: var(--dark-purple);
            border-color: var(--dark-purple);
            transform: translateY(-2px);
        }

        .alert {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
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
            
            .header-right {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
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
    <!-- En-tête avec logo -->
    <div class="header">
        <div class="header-left">
            <div class="header-logo">
                <img src="image copy 8.png" alt="Logo">
            </div>
            <h1>Saisie des notes</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-calendar-alt"></i>
                <span class="user-info-label">Année :</span>
                <span class="user-info-value">2023-2024</span>
            </div>
            <div class="user-info">
                <i class="fas fa-user"></i>
                <span class="user-info-label">Connecté en tant que :</span>
                <span class="user-info-value"><?= isset($vacataire) ? htmlspecialchars($vacataire['prenom'] . ' ' . htmlspecialchars($vacataire['nom']) ): 'Vacataire' ?></span>
            </div>
        </div>
    </div>

    <!-- Conteneur principal -->
    <div class="main-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <img src="https://ui-avatars.com/api/?name=<?= isset($vacataire) ? urlencode($vacataire['prenom'] . '+' . $vacataire['nom']) : 'Vacataire' ?>&background=8a2be2&color=fff" alt="Vacataire">
                <h3><?= isset($vacataire) ? htmlspecialchars($vacataire['prenom'] . ' ' . htmlspecialchars($vacataire['nom']) ): 'Vacataire' ?></h3>
            </div>
            
            <div class="sidebar-menu">
                <!-- Tableau de bord -->
                <div class="nav-item">
                    <a href="dashboard_vacataire.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Tableau de Bord</span>
                    </a>
                </div>
                
                <!-- Mes UEs -->
                <div class="nav-item">
                    <a href="mes_ues.php" class="nav-link">
                        <i class="fas fa-book"></i>
                        <span>Mes UEs</span>
                    </a>
                </div>
                
                <!-- Saisie notes -->
                <div class="nav-item">
                    <a href="notes_vacataire.php" class="nav-link active">
                        <i class="fas fa-edit"></i>
                        <span>Saisie des notes</span>
                    </a>
                </div>
                
                <!-- Emploi du temps -->
                
                <!-- Déconnexion -->
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </nav>

        <!-- Contenu principal -->
        <div class="main-content">
            <div class="form-section">
                <h2 class="section-header">
                    <i class="fas fa-edit"></i> Formulaire de saisie des notes
                </h2>
                
                <?php if($error_message): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                <?php endif; ?>
                
                <?php if($success_message): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">UE :</label>
                        <select name="id_ue" class="form-control" required>
                            <?php foreach($ues_assignees as $ue): ?>
                                <option value="<?= htmlspecialchars($ue['id_ue']) ?>">
                                    <?= htmlspecialchars($ue['nom']) ?> (<?= htmlspecialchars($ue['type_enseignement']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Session :</label>
                        <select name="session" class="form-control" required>
                            <option value="normale">Session normale</option>
                            <option value="rattrapage">Session de rattrapage</option>
                        </select>
                    </div>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Étudiant</th>
                                    <th>Note (/20)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($etudiants as $etudiant): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($etudiant['nom']) ?></td>
                                        <td>
                                            <input type="number" name="notes[<?= htmlspecialchars($etudiant['id_etudiant']) ?>]" 
                                                   step="0.01" min="0" max="20" 
                                                   class="form-control" required>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Enregistrer les notes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>