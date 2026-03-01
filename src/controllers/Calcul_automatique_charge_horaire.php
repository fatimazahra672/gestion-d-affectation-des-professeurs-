<?php
session_start();

class DatabaseConfig {
    const HOST = "localhost";
    const DBNAME = "gestion_coordinteur";
    const USER = "root";
    const PASS = "";
}

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DatabaseConfig::HOST . ";dbname=" . DatabaseConfig::DBNAME . ";charset=utf8mb4",
                DatabaseConfig::USER,
                DatabaseConfig::PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Erreur de connexion à la base de données: " . $e->getMessage());
            throw new Exception("Erreur de connexion à la base de données");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPDO() {
        return $this->pdo;
    }
}

class ChargeHoraireManager {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getPDO();
    }

    public function getChargesHoraires($id_enseignant, $annee_scolaire) {
        $stmt = $this->pdo->prepare("
            SELECT 
                m.nom as nom_matiere,
                ue.filiere,
                ue.niveau,
                ue.type_enseignement,
                ue.volume_horaire,
                :annee_scolaire as annee_scolaire,
                a.date_affectation
            FROM affectations a
            INNER JOIN unites_enseignements ue ON a.ue_id = ue.id_ue
            INNER JOIN matieres m ON ue.id_matiere = m.id_matiere
            WHERE a.professeur_id = :id_enseignant
            ORDER BY ue.filiere, ue.niveau, ue.type_enseignement
        ");

        $stmt->execute([
            ':id_enseignant' => $id_enseignant,
            ':annee_scolaire' => $annee_scolaire
        ]);

        return $stmt->fetchAll();
    }

    public function calculerTotalCharge($charges_horaires) {
        return array_sum(array_column($charges_horaires, 'volume_horaire'));
    }

    public function getChargeMinimale($id_enseignant, $annee_scolaire) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT charge_min
                FROM charge_horaire_minimale
                WHERE id_utilisateur = :id_enseignant
                AND annee_scolaire = :annee_scolaire
            ");
            $stmt->execute([
                ':id_enseignant' => $id_enseignant,
                ':annee_scolaire' => $annee_scolaire
            ]);

            $result = $stmt->fetch();
            return $result ? (int)$result['charge_min'] : 192;
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération de la charge minimale: " . $e->getMessage());
            return 192;
        }
    }

    public function sauvegarderCharge($id_enseignant, $annee_scolaire, $total_charge) {
        try {
            $check_stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM charge_horaire_minimale
                WHERE id_utilisateur = ? AND annee_scolaire = ?
            ");
            $check_stmt->execute([$id_enseignant, $annee_scolaire]);
            $exists = $check_stmt->fetch()['count'] > 0;

            if ($exists) {
                $stmt = $this->pdo->prepare("
                    UPDATE charge_horaire_minimale
                    SET charge_min = ?, date_modification = NOW()
                    WHERE id_utilisateur = ? AND annee_scolaire = ?
                ");
                $result = $stmt->execute([$total_charge, $id_enseignant, $annee_scolaire]);

                if ($result && $stmt->rowCount() > 0) {
                    return [
                        'success' => true,
                        'message' => "✅ Charge horaire mise à jour avec succès ! ({$total_charge}h)"
                    ];
                }
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO charge_horaire_minimale
                    (id_utilisateur, annee_scolaire, charge_min, date_creation)
                    VALUES (?, ?, ?, NOW())
                ");
                $result = $stmt->execute([$id_enseignant, $annee_scolaire, $total_charge]);

                if ($result) {
                    return [
                        'success' => true,
                        'message' => "✅ Nouvelle charge horaire enregistrée avec succès ! ({$total_charge}h)"
                    ];
                }
            }
            
            return [
                'success' => false,
                'message' => "⚠ Aucune modification effectuée"
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur lors de la sauvegarde: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "❌ Erreur de base de données : " . $e->getMessage()
            ];
        }
    }
}

class Validator {
    public static function validateId($id) {
        return filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    }

    public static function validateAnnee($annee) {
        return preg_match('/^\d{4}-\d{4}$/', $annee);
    }

    public static function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

// Initialisation
$message = "";
$charges_horaires = [];
$total_charge = 0;
$charge_minimale = 192;
$mode = $_GET['mode'] ?? 'form';
$id_enseignant_connecte = $_GET['id_enseignant'] ?? 19;

try {
    $chargeManager = new ChargeHoraireManager();

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_POST['calculer'])) {
            $id_enseignant = Validator::validateId($_POST["id_enseignant"]);
            $annee_scolaire = $_POST["annee_scolaire"];

            if ($id_enseignant && Validator::validateAnnee($annee_scolaire)) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?mode=results&id_enseignant=" .
                       urlencode($id_enseignant) . "&annee_scolaire=" . urlencode($annee_scolaire));
                exit();
            } else {
                $message = "❌ Données d'entrée invalides";
            }
        }
        elseif (isset($_POST['sauvegarder'])) {
            $id_enseignant = Validator::validateId($_POST["id_enseignant"]);
            $annee_scolaire = $_POST["annee_scolaire"];
            $total_charge = filter_var($_POST["total_charge"], FILTER_VALIDATE_INT);

            if ($id_enseignant && Validator::validateAnnee($annee_scolaire) && $total_charge !== false) {
                $result = $chargeManager->sauvegarderCharge($id_enseignant, $annee_scolaire, $total_charge);
                $message = $result['message'];
                $mode = 'results';
                $_GET['id_enseignant'] = $id_enseignant;
                $_GET['annee_scolaire'] = $annee_scolaire;
            }
        }
    }

    if ($mode === 'results' && isset($_GET['id_enseignant']) && isset($_GET['annee_scolaire'])) {
        $id_enseignant = Validator::validateId($_GET['id_enseignant']);
        $annee_scolaire = $_GET['annee_scolaire'];

        if ($id_enseignant && Validator::validateAnnee($annee_scolaire)) {
            $charges_horaires = $chargeManager->getChargesHoraires($id_enseignant, $annee_scolaire);
            $total_charge = $chargeManager->calculerTotalCharge($charges_horaires);
            $charge_minimale = $chargeManager->getChargeMinimale($id_enseignant, $annee_scolaire);

            if (!empty($charges_horaires)) {
                $message = "✅ Charge horaire calculée avec succès.";
            } else {
                $message = "⚠ Aucune charge horaire trouvée pour cet enseignant.";
            }
        }
    }

} catch (Exception $e) {
    $message = "❌ Erreur système : " . $e->getMessage();
}

function generateYearOptions($selectedYear = '2024-2025') {
    $currentYear = date('Y');
    $options = '';

    for ($year = $currentYear - 2; $year <= $currentYear + 2; $year++) {
        $yearOption = $year . '-' . ($year + 1);
        $selected = ($yearOption === $selectedYear) ? 'selected' : '';
        $options .= "<option value=\"{$yearOption}\" {$selected}>{$yearOption}</option>";
    }

    return $options;
}
?>



<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($mode === 'results') ? 'Résultats - Charge Horaire' : 'Charge Horaire Enseignant'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #6a0dad;
            --secondary-color: #8a2be2;
            --light-purple: #e6e6fa;
            --dark-purple: #4b0082;
            --accent-color: #00bfff;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
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
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 10px;
            border: 3px solid white;
            object-fit: cover;
            display: block;
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

        .action-btn {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background: #e6f7ee;
            color: #10b981;
        }

        .status-inactive {
            background: #fff4e6;
            color: #f59e0b;
        }

        /* Styles pour le formulaire et résultats */
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 25px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #ddd;
            background-color: white;
            color: #333;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(106, 13, 173, 0.2);
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 1.1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(106, 13, 173, 0.4);
        }

        .btn-back {
            background: linear-gradient(45deg, #ffc107, #ff8c00);
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            display: inline-block;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 140, 0, 0.4);
        }

        .message {
            text-align: center;
            margin: 1.5rem 0;
            font-weight: 600;
            font-size: 1.1rem;
            padding: 15px 20px;
            border-radius: 10px;
            border-left: 5px solid;
        }

        .message.success {
            background-color: rgba(40, 167, 69, 0.2);
            border-left-color: var(--success-color);
            color: #155724;
        }

        .message.error {
            background-color: rgba(220, 53, 69, 0.2);
            border-left-color: var(--danger-color);
            color: #721c24;
        }

        .results-section {
            max-width: 1200px;
            margin: 2rem auto;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
            border: 2px solid;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card.total { border-color: var(--primary-color); }
        .card.minimal { border-color: var(--warning-color); }
        .card.status { border-color: var(--success-color); }
        .card.status.deficit { border-color: var(--danger-color); }

        .card h3 {
            margin: 0 0 1rem 0;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #555;
        }

        .card .value {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .save-form {
            margin-top: 2rem;
            padding: 25px;
            background: rgba(40, 167, 69, 0.1);
            border-radius: 12px;
            border: 2px solid var(--success-color);
        }

        .no-data {
            text-align: center;
            padding: 3rem 2rem;
            color: #6c757d;
            font-style: italic;
            background: white;
            border-radius: 12px;
            margin: 2rem 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
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
            <h1>Calcul Automatique de charge Horaire</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <div>
                    <div class="user-info-label">Enseignant</div>
                    <div class="user-info-value">Fatima Zahra El hamdani</div>
                </div>
            </div>
        </div>
    </header>

    <div class="main-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="https://via.placeholder.com/100" alt="">
                <h3>Enseignant</h3>
                <small class="text-white-50">Mathematique Appliques</small>
            </div>
            <nav class="sidebar-menu">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="dashboard_enseignant.php" class="nav-link">
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
                        <a href="Calcul_automatique_charge_horaire.php" class="nav-link active">
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
            <?php if ($mode === 'form'): ?>
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2 class="section-title">Calcul de la charge horaire</h2>
                    </div>
                    <div class="form-container">
                        <form method="post">
                            <div class="form-group">
                                <label for="id_enseignant">🆔 ID Enseignant</label>
                                <input type="number" name="id_enseignant" id="id_enseignant"
                                       value="<?php echo Validator::sanitizeInput($id_enseignant_connecte); ?>"
                                       required min="1" max="9999">
                            </div>

                            <div class="form-group">
                                <label for="annee_scolaire">📅 Année scolaire</label>
                                <select name="annee_scolaire" id="annee_scolaire" required>
                                    <?php echo generateYearOptions(); ?>
                                </select>
                            </div>

                            <button type="submit" name="calculer" class="btn-primary">
                                🔍 Calculer la charge horaire
                            </button>
                        </form>
                    </div>
                </div>

            <?php else: ?>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn-back">← Retour au formulaire</a>
                
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2 class="section-title">Résultats - Charge Horaire</h2>
                        <div>
                            <span class="status-badge status-active">
                                👨‍🏫 Enseignant ID: <?php echo Validator::sanitizeInput($_GET['id_enseignant']); ?>
                            </span>
                            <span class="status-badge status-active">
                                📅 Année: <?php echo Validator::sanitizeInput($_GET['annee_scolaire']); ?>
                            </span>
                        </div>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="message <?php echo (strpos($message, '✅') !== false) ? 'success' : 'error'; ?>">
                            <?php echo Validator::sanitizeInput($message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($charges_horaires)): ?>
                        <div class="results-section">
                            <div class="summary-cards">
                                <div class="card total">
                                    <h3>⏱ Charge Totale</h3>
                                    <p class="value"><?php echo $total_charge; ?>h</p>
                                </div>
                                <div class="card minimal">
                                    <h3>📏 Charge Minimale</h3>
                                    <p class="value"><?php echo $charge_minimale; ?>h</p>
                                </div>
                                <div class="card status <?php echo ($total_charge < $charge_minimale) ? 'deficit' : ''; ?>">
                                    <h3>📊 Statut</h3>
                                    <p class="value">
                                        <?php echo ($total_charge >= $charge_minimale) ? "✅ OK" : "⚠ Déficit"; ?>
                                    </p>
                                </div>
                                <div class="card">
                                    <h3>📈 Différence</h3>
                                    <p class="value" style="color: <?php echo ($total_charge - $charge_minimale >= 0) ? 'var(--success-color)' : 'var(--danger-color)'; ?>">
                                        <?php echo ($total_charge - $charge_minimale >= 0 ? '+' : '') . ($total_charge - $charge_minimale); ?>h
                                    </p>
                                </div>
                            </div>

                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>📚 Matière</th>
                                            <th>🎓 Filière</th>
                                            <th>📊 Niveau</th>
                                            <th>🏷 Type</th>
                                            <th>⏰ Volume Horaire</th>
                                            <th>📅 Année Scolaire</th>
                                            <th>📋 Date Affectation</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($charges_horaires as $charge): ?>
                                            <tr>
                                                <td><?php echo Validator::sanitizeInput($charge['nom_matiere']); ?></td>
                                                <td><?php echo Validator::sanitizeInput($charge['filiere']); ?></td>
                                                <td><?php echo Validator::sanitizeInput($charge['niveau']); ?></td>
                                                <td>
                                                    <?php echo Validator::sanitizeInput($charge['type_enseignement']); ?>
                                                </td>
                                                <td><strong><?php echo $charge['volume_horaire']; ?>h</strong></td>
                                                <td><?php echo Validator::sanitizeInput($charge['annee_scolaire']); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($charge['date_affectation'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4"><strong>Total Charge Horaire:</strong></td>
                                            <td colspan="3"><strong><?php echo $total_charge; ?>h</strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Formulaire de sauvegarde -->
                            <div class="save-form">
                                <h3 style="color: var(--success-color); margin-top: 0;">💾 Sauvegarder la charge horaire</h3>
                                <form method="post" style="display: flex; gap: 1rem; align-items: end;">
                                    <input type="hidden" name="id_enseignant" value="<?php echo htmlspecialchars($_GET['id_enseignant']); ?>">
                                    <input type="hidden" name="annee_scolaire" value="<?php echo htmlspecialchars($_GET['annee_scolaire']); ?>">
                                    <input type="hidden" name="total_charge" value="<?php echo $total_charge; ?>">

                                    <div style="flex: 1;">
                                        <label style="color: #555; margin-bottom: 5px; display: block;">Confirmer la charge totale:</label>
                                        <input type="number"
                                               value="<?php echo $total_charge; ?>"
                                               readonly
                                               style="background-color: #f8f9fc; color: #333; padding: 10px; border: 1px solid #ddd; border-radius: 5px; width: 100%;">
                                    </div>

                                    <button type="submit" name="sauvegarder" class="btn-primary" style="width: auto; padding: 10px 20px; margin: 0;">
                                        💾 Sauvegarder
                                    </button>
                                </form>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="no-data">
                            <h3>📭 Aucune donnée trouvée</h3>
                            <p>Aucune charge horaire n'a été trouvée pour l'enseignant ID <strong><?php echo Validator::sanitizeInput($_GET['id_enseignant']); ?></strong>
                               pour l'année scolaire <strong><?php echo Validator::sanitizeInput($_GET['annee_scolaire']); ?></strong>.</p>
                            <p>Vérifiez que l'enseignant a bien des affectations pour cette période.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animation des éléments
        document.addEventListener('DOMContentLoaded', function() {
            // Effet de survol pour les liens de la sidebar
            const sidebarLinks = document.querySelectorAll('.nav-link');
            sidebarLinks.forEach(link => {
                link.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(5px)';
                });

                link.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                });
            });

            // Animation des cartes
            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Confirmation avant sauvegarde
            const saveButton = document.querySelector('button[name="sauvegarder"]');
            if (saveButton) {
                saveButton.addEventListener('click', function(e) {
                    const totalCharge = this.form.querySelector('input[name="total_charge"]').value;
                    if (!confirm(`Êtes-vous sûr de vouloir sauvegarder la charge horaire de ${totalCharge}h ?`)) {
                        e.preventDefault();
                    }
                });
            }

            // Messages automatiques
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                setTimeout(() => {
                    message.style.opacity = '0';
                    message.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        message.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>