<?php
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

// Récupération des résultats depuis la session
$resultats = $_SESSION['form_result'] ?? null;
$souhaits_soumis = $_SESSION['souhaits_soumis'] ?? [];
$user_type = $_SESSION['user_type'];
$id_utilisateur = $_SESSION['user_id'];

// Si pas de résultats, rediriger vers la page des souhaits
if (!$resultats) {
    header("Location: souhaits_enseignants.php");
    exit();
}

// Récupérer les détails des UE soumises
$ues_details = [];
if (!empty($souhaits_soumis)) {
    $ids_ues = implode(',', array_map('intval', $souhaits_soumis));
    $sql = "SELECT ue.*, m.nom AS matiere 
            FROM unites_enseignements ue
            LEFT JOIN matieres m ON ue.id_matiere = m.id_matiere
            WHERE ue.id_ue IN ($ids_ues)
            ORDER BY ue.filiere, ue.niveau, m.nom";
    $result = $mysqli->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $ues_details[] = $row;
        }
    }
}

// Récupérer tous les souhaits de l'enseignant pour affichage
$mes_souhaits = [];
$sql_mes_souhaits = "SELECT ue.*, m.nom AS matiere, se.date_souhait, se.annee_scolaire
                     FROM souhaits_enseignants se
                     JOIN unites_enseignements ue ON se.id_ue = ue.id_ue
                     LEFT JOIN matieres m ON ue.id_matiere = m.id_matiere
                     WHERE se.id_enseignant = ?
                     ORDER BY se.date_souhait DESC";
$stmt = $mysqli->prepare($sql_mes_souhaits);
$stmt->bind_param("i", $id_utilisateur);
$stmt->execute();
$result_mes_souhaits = $stmt->get_result();
while ($row = $result_mes_souhaits->fetch_assoc()) {
    $mes_souhaits[] = $row;
}

// Nettoyer la session
unset($_SESSION['form_result']);
unset($_SESSION['souhaits_soumis']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultat - Souhaits Enseignants</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --background-dark: #1a1a1a;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
        }

        body {
            background: var(--background-dark);
            color: #fff;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            width: 280px;
            background: var(--primary-color);
            position: fixed;
            height: 100vh;
            padding: 20px;
            overflow-y: auto;
        }

        .sidebar img {
            max-width: 150px;
            display: block;
            margin: 0 auto 20px auto;
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 12px 15px;
            margin: 5px 0;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
        }

        .result-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .success-icon {
            color: var(--success-color);
            font-size: 4rem;
            margin-bottom: 20px;
        }

        .error-icon {
            color: var(--danger-color);
            font-size: 4rem;
            margin-bottom: 20px;
        }

        .table-custom {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            overflow: hidden;
        }

        .table-custom th {
            background: var(--secondary-color) !important;
            color: white;
        }

        .table-custom td {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border-color: rgba(255, 255, 255, 0.1);
        }

        .btn-custom {
            background: var(--secondary-color);
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            color: white;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
            transition: all 0.3s;
        }

        .btn-custom:hover {
            background: #2980b9;
            transform: translateY(-2px);
            color: white;
        }

        .stats-card {
            background: linear-gradient(135deg, var(--secondary-color), #3498db);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            color: white;
            margin-bottom: 20px;
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <img src="image copy 5.png" alt="Logo">
    <a href="Affichage_liste_UE.php">Affichage la liste de UE</a>
    <a href="souhaits_enseignants.php">Souhaits Enseignants</a>
    <a href="Calcul_automatique_charge_horaire.php">Calcul automatique de la charge horaire</a>
    <a href="Notification_non-respect_charge_minimale.php">Notification en cas de non-respect de la charge minimale</a>
    <a href="Consulter_modules_assurés_assure.php">Consulter la liste des modules assurés et qu'il assure.</a>
    <a href="Uploader_notes_session_normale_rattrapage.php">Uploader les notes de la session normale et rattrapage.</a>
    <a href="Consulter_historique_années_passées.">Consulter l'historique des années passées.</a>
    <a href="?logout=true" class="btn btn-danger w-100 mt-3">Déconnexion</a>
</div>

<!-- Contenu principal -->
<div class="main-content">
    <div class="container-fluid">
        
        <!-- Résultat de la soumission -->
        <div class="result-card text-center">
            <?php if ($resultats['success'] > 0): ?>
                <i class="fas fa-check-circle success-icon"></i>
                <h1 class="text-success mb-4">✅ Souhaits enregistrés avec succès !</h1>
                <div class="stats-card">
                    <div class="stats-number"><?= $resultats['success'] ?></div>
                    <div>Souhait(s) enregistré(s)</div>
                </div>
            <?php else: ?>
                <i class="fas fa-exclamation-triangle error-icon"></i>
                <h1 class="text-danger mb-4">❌ Erreur lors de l'enregistrement</h1>
            <?php endif; ?>

            <?php if (!empty($resultats['errors'])): ?>
                <div class="alert alert-danger">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Erreurs rencontrées :</h5>
                    <ul class="mb-0">
                        <?php foreach ($resultats['errors'] as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <!-- Détails des UE soumises -->
        <?php if (!empty($ues_details)): ?>
        <div class="result-card">
            <h3><i class="fas fa-list me-2"></i>Détails des UE sélectionnées</h3>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Matière</th>
                            <th>Filière</th>
                            <th>Niveau</th>
                            <th>Type</th>
                            <th>Année Scolaire</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ues_details as $ue): ?>
                        <tr>
                            <td><?= htmlspecialchars($ue['matiere'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($ue['filiere']) ?></td>
                            <td><?= htmlspecialchars($ue['niveau']) ?></td>
                            <td><?= htmlspecialchars($ue['type_enseignement']) ?></td>
                            <td><?= htmlspecialchars($ue['annee_scolaire']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Mes souhaits actuels -->
        <?php if (!empty($mes_souhaits)): ?>
        <div class="result-card">
            <h3><i class="fas fa-heart me-2"></i>Tous mes souhaits enregistrés</h3>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Matière</th>
                            <th>Filière</th>
                            <th>Niveau</th>
                            <th>Type</th>
                            <th>Année UE</th>
                            <th>Date du souhait</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mes_souhaits as $souhait): ?>
                        <tr>
                            <td><?= htmlspecialchars($souhait['matiere'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($souhait['filiere']) ?></td>
                            <td><?= htmlspecialchars($souhait['niveau']) ?></td>
                            <td><?= htmlspecialchars($souhait['type_enseignement']) ?></td>
                            <td><?= htmlspecialchars($souhait['annee_scolaire']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($souhait['date_souhait'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="text-center">
            <a href="souhaits_enseignants.php" class="btn-custom">
                <i class="fas fa-plus me-2"></i>Ajouter d'autres souhaits
            </a>
            <a href="Affichage_liste_UE.php" class="btn-custom">
                <i class="fas fa-list me-2"></i>Voir toutes les UE
            </a>
            <a href="Calcul_automatique_charge_horaire.php" class="btn-custom">
                <i class="fas fa-calculator me-2"></i>Calcul de charge
            </a>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
