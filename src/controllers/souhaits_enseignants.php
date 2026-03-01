<?php
// Activation du rapport d'erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configuration de la session
session_start();

// Connexion à la base de données
$mysqli = new mysqli("localhost", "root", "", "gestion_coordinteur");
if ($mysqli->connect_error) {
    die("Erreur de connexion : " . $mysqli->connect_error);
}

// Vérification d'authentification
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['enseignant', 'chef_departement', 'admin'])) {
    header("Location: login_coordinateur.php");
    exit();
}

$id_enseignant = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$annee_courante = "2024-2025";
$annee_suivante = "2025-2026";

// Traitement du formulaire de soumission des souhaits
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['souhaits']) && $user_type === 'enseignant') {
    $souhaits = $_POST['souhaits'];
    $resultats = ['success' => 0, 'errors' => []];

    // Commencer une transaction
    $mysqli->begin_transaction();

    try {
        foreach ($souhaits as $id_ue) {
            // Vérification de l'existence de l'UE
            $check_ue = $mysqli->prepare("SELECT id_ue FROM unites_enseignements WHERE id_ue = ?");
            if ($check_ue === false) {
                throw new Exception("Erreur de préparation (vérif UE): " . $mysqli->error);
            }
            
            if (!$check_ue->bind_param("i", $id_ue)) {
                throw new Exception("Erreur de liaison (vérif UE): " . $check_ue->error);
            }
            
            if (!$check_ue->execute()) {
                throw new Exception("Erreur d'exécution (vérif UE): " . $check_ue->error);
            }

            if (!$check_ue->get_result()->num_rows) {
                $resultats['errors'][] = "UE $id_ue introuvable";
                continue;
            }

            // Vérifier si le souhait existe déjà
            $check = $mysqli->prepare("SELECT id_souhait FROM souhaits_enseignants WHERE id_enseignant = ? AND id_ue = ?");
            if ($check === false) {
                throw new Exception("Erreur de préparation (vérif souhait): " . $mysqli->error);
            }
            
            if (!$check->bind_param("ii", $id_enseignant, $id_ue)) {
                throw new Exception("Erreur de liaison (vérif souhait): " . $check->error);
            }
            
            if (!$check->execute()) {
                throw new Exception("Erreur d'exécution (vérif souhait): " . $check->error);
            }

            if ($check->get_result()->num_rows > 0) {
                $resultats['errors'][] = "UE $id_ue déjà souhaitée";
                continue;
            }

            // Insertion du souhait
            $stmt = $mysqli->prepare("INSERT INTO souhaits_enseignants (id_enseignant, id_ue, annee_scolaire, date_souhait) VALUES (?, ?, ?, NOW())");
            if ($stmt === false) {
                throw new Exception("Erreur de préparation (insertion souhait): " . $mysqli->error);
            }
            
            if (!$stmt->bind_param("iis", $id_enseignant, $id_ue, $annee_suivante)) {
                throw new Exception("Erreur de liaison (insertion souhait): " . $stmt->error);
            }
            
            if (!$stmt->execute()) {
                $resultats['errors'][] = "Erreur UE $id_ue : " . $stmt->error;
            } else {
                $resultats['success']++;
            }
        }

        $mysqli->commit();
    } catch (Exception $e) {
        $mysqli->rollback();
        $resultats['errors'][] = "Erreur système : " . $e->getMessage();
    }

    $_SESSION['form_result'] = $resultats;
    header("Location: souhaits_enseignants.php");
    exit();
}

// Récupérer TOUTES les unités d'enseignement avec pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Compter le nombre total d'UE
$count_query = "SELECT COUNT(*) as total FROM unites_enseignements";
$count_result = $mysqli->query($count_query);
if (!$count_result) {
    die("Erreur de comptage des UE: " . $mysqli->error);
}
$total_ue = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_ue / $limit);

// Requête principale avec pagination
$sql = "
SELECT 
    ue.id_ue, 
    ue.filiere, 
    ue.niveau, 
    ue.annee_scolaire, 
    ue.type_enseignement, 
    m.nom AS nom_matiere,
    CASE WHEN se.id_enseignant IS NOT NULL THEN 1 ELSE 0 END AS deja_souhaite
FROM 
    unites_enseignements ue
JOIN 
    matieres m ON ue.id_matiere = m.id_matiere
LEFT JOIN 
    souhaits_enseignants se ON ue.id_ue = se.id_ue AND se.id_enseignant = ?
ORDER BY 
    ue.annee_scolaire DESC, ue.filiere, ue.niveau, m.nom
LIMIT ? OFFSET ?
";

$stmt = $mysqli->prepare($sql);
if ($stmt === false) {
    die("Erreur de préparation de la requête: " . $mysqli->error);
}

if (!$stmt->bind_param("iii", $id_enseignant, $limit, $offset)) {
    die("Erreur de liaison des paramètres: " . $stmt->error);
}

if (!$stmt->execute()) {
    die("Erreur d'exécution de la requête: " . $stmt->error);
}

$result = $stmt->get_result();
if (!$result) {
    die("Erreur de récupération des résultats: " . $stmt->error);
}

if ($result->num_rows === 0) {
    $no_ue_message = "Aucune unité d'enseignement disponible dans la base de données.";
}

// Récupération du nom de l'enseignant pour le header
$nom_enseignant = $_SESSION['nom_enseignant'] ?? 'Enseignant';

// Déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login_coordinateur.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exprimer les souhaits - Enseignant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6a0dad;
            --secondary-color: #8a2be2;
            --light-purple: #e6e6fa;
            --dark-purple: #4b0082;
            --accent-color: #00bfff;
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

        .profile-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
            margin: 0 auto 10px;
            border: 3px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            color: white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
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

        .badge-souhaite {
            background: #e6f7ee;
            color: #10b981;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-non-souhaite {
            background: #fff4e6;
            color: #f59e0b;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            width: 320px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border: none;
        }

        .pagination {
            justify-content: center;
            margin-top: 20px;
        }

        .table-responsive {
            max-height: 600px;
            overflow-y: auto;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 10px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: var(--dark-purple);
            border-color: var(--dark-purple);
            transform: translateY(-2px);
        }

        .btn-outline-secondary {
            padding: 10px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-secondary:hover {
            transform: translateY(-2px);
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-input:focus {
            box-shadow: 0 0 0 0.25rem rgba(106, 13, 173, 0.25);
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
            <h1>Gestion des Souhaits d'Enseignement</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <div>
                    <div class="user-info-label">Enseignant</div>
                    <div class="user-info-value"><?= htmlspecialchars($nom_enseignant) ?></div>
                </div>
            </div>
        </div>
    </header>

    <div class="main-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="profile-circle">EN</div>
                <h3>Enseignant</h3>
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
                        <a href="souhaits_enseignants.php" class="nav-link active">
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
                        <a href="consulter_modules.php" class="nav-link">
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
            <div class="dashboard-section">
                <h2 class="section-title">Exprimer mes souhaits d'enseignement</h2>
                
                <?php if (isset($_SESSION['form_result'])): ?>
                    <?php if ($_SESSION['form_result']['success'] > 0): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= $_SESSION['form_result']['success'] ?> souhaits enregistrés avec succès.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($_SESSION['form_result']['errors'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Erreurs :</strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($_SESSION['form_result']['errors'] as $error): ?>
                                    <li><?= $error ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php unset($_SESSION['form_result']); ?>
                <?php endif; ?>
                
                <!-- Formulaire pour soumettre les souhaits -->
                <form method="post">
                    <?php if (isset($no_ue_message)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?= $no_ue_message ?>
                        </div>
                    <?php else: ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="toggleCheckboxes" style="width: 3em; height: 1.5em;">
                                <label class="form-check-label fw-medium" for="toggleCheckboxes">Sélectionner/Désélectionner tout</label>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <span class="badge bg-primary rounded-pill">Total UE: <?= $total_ue ?></span>
                                </div>
                                <div>
                                    <span class="badge bg-success rounded-pill">Sélectionnés: <span id="selectedCount">0</span></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th width="50px">Sélection</th>
                                        <th>Matière</th>
                                        <th>Filière</th>
                                        <th>Niveau</th>
                                        <th>Type</th>
                                        <th>Année scolaire</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" 
                                                       name="souhaits[]" 
                                                       value="<?= $row['id_ue'] ?>"
                                                       class="ue-checkbox form-check-input"
                                                       <?= $row['deja_souhaite'] ? 'disabled' : '' ?>
                                                       data-ue-id="<?= $row['id_ue'] ?>">
                                            </td>
                                            <td><?= htmlspecialchars($row['nom_matiere']) ?></td>
                                            <td><?= htmlspecialchars($row['filiere']) ?></td>
                                            <td><?= htmlspecialchars($row['niveau']) ?></td>
                                            <td><?= htmlspecialchars($row['type_enseignement']) ?></td>
                                            <td><?= htmlspecialchars($row['annee_scolaire']) ?></td>
                                            <td>
                                                <?php if ($row['deja_souhaite']): ?>
                                                    <span class="badge badge-souhaite">
                                                        <i class="fas fa-check me-1"></i>Déjà souhaité
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-non-souhaite">
                                                        <i class="fas fa-clock me-1"></i>Disponible
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation" class="mt-4">
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($user_type === 'enseignant'): ?>
                        <div class="d-flex justify-content-between mt-4">
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-2"></i>Réinitialiser
                            </button>
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-paper-plane me-2"></i>Soumettre mes souhaits
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // Fonction pour mettre à jour le compteur de sélection
        function updateSelectedCount() {
            const selectedCount = $('.ue-checkbox:checked').length;
            $('#selectedCount').text(selectedCount);
            
            // Désactiver le bouton si aucun UE n'est sélectionné
            $('#submitBtn').prop('disabled', selectedCount === 0);
            
            // Mettre à jour le badge
            if (selectedCount > 0) {
                $('#submitBtn').removeClass('btn-primary').addClass('btn-success');
                $('#submitBtn i').removeClass('fa-paper-plane').addClass('fa-check');
            } else {
                $('#submitBtn').removeClass('btn-success').addClass('btn-primary');
                $('#submitBtn i').removeClass('fa-check').addClass('fa-paper-plane');
            }
        }

        // Initialiser le compteur
        updateSelectedCount();

        // Toggle toutes les cases à cocher
        $('#toggleCheckboxes').change(function() {
            const isChecked = this.checked;
            $('.ue-checkbox:not(:disabled)').prop('checked', isChecked).trigger('change');
        });

        // Mettre à jour le compteur quand une case est cochée/décochée
        $('.ue-checkbox').change(function() {
            updateSelectedCount();
        });

        // Validation client
        $('form').submit(function(e) {
            const checkboxes = $('input[name="souhaits[]"]:checked');
            if (checkboxes.length === 0) {
                e.preventDefault();
                alert('Veuillez sélectionner au moins un UE !');
                return false;
            }
            
            // Confirmation avant soumission
            if (!confirm(`Vous êtes sur le point d'enregistrer ${checkboxes.length} souhait(s). Confirmer ?`)) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });

        // Stocker les sélections dans localStorage
        $('.ue-checkbox').change(function() {
            const ueId = $(this).data('ue-id');
            if (this.checked) {
                localStorage.setItem('ue_' + ueId, 'selected');
            } else {
                localStorage.removeItem('ue_' + ueId);
            }
        });

        // Restaurer les sélections au chargement de la page
        $('.ue-checkbox').each(function() {
            const ueId = $(this).data('ue-id');
            if (localStorage.getItem('ue_' + ueId)) {
                $(this).prop('checked', true);
            }
        });
        updateSelectedCount();
    });
    </script>
</body>
</html>