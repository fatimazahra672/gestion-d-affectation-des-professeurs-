<?php
session_start();

// Connexion à la base de données
$mysqli = new mysqli("localhost", "root", "", "gestion_coordinteur");
if ($mysqli->connect_error) {
    die("Erreur de connexion : " . $mysqli->connect_error);
}

// Vérification d'authentification
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['chef_departement', 'admin'])) {
    header("Location: login_coordinateur.php");
    exit();
}

$message = "";
$message_type = "";

// Traitement du formulaire d'ajout/modification
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['ajouter_charge'])) {
        $id_enseignant = $_POST['id_enseignant'];
        $annee_scolaire = $_POST['annee_scolaire'];
        $charge_min = $_POST['charge_min'];

        // Vérifier si l'enseignant existe
        $check_enseignant = $mysqli->prepare("SELECT id, nom, prenom FROM utilisateurs WHERE id = ? AND type_utilisateur = 'enseignant'");
        $check_enseignant->bind_param("i", $id_enseignant);
        $check_enseignant->execute();
        $result_enseignant = $check_enseignant->get_result();

        if ($result_enseignant->num_rows === 0) {
            $message = "❌ Enseignant introuvable avec l'ID $id_enseignant";
            $message_type = "error";
        } else {
            // Insérer ou mettre à jour la charge minimale
            $stmt = $mysqli->prepare("INSERT INTO charge_horaire_minimale (id_utilisateur, annee_scolaire, charge_min, date_creation)
                                     VALUES (?, ?, ?, NOW())
                                     ON DUPLICATE KEY UPDATE
                                     charge_min = VALUES(charge_min),
                                     date_modification = NOW()");
            $stmt->bind_param("isi", $id_enseignant, $annee_scolaire, $charge_min);

            if ($stmt->execute()) {
                $enseignant_info = $result_enseignant->fetch_assoc();
                $message = "✅ Charge minimale de {$charge_min}h définie pour {$enseignant_info['nom']} {$enseignant_info['prenom']} ({$annee_scolaire})";
                $message_type = "success";
            } else {
                $message = "❌ Erreur lors de l'enregistrement : " . $stmt->error;
                $message_type = "error";
            }
        }
    }

    if (isset($_POST['supprimer_charge'])) {
        $id = $_POST['id_charge'];
        $stmt = $mysqli->prepare("DELETE FROM charge_horaire_minimale WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $message = "✅ Charge minimale supprimée avec succès";
            $message_type = "success";
        } else {
            $message = "❌ Erreur lors de la suppression";
            $message_type = "error";
        }
    }
}

// Récupérer toutes les charges minimales
$charges_query = "SELECT cm.*, u.nom, u.prenom, u.email
                  FROM charge_horaire_minimale cm
                  LEFT JOIN utilisateurs u ON cm.id_utilisateur = u.id
                  ORDER BY cm.annee_scolaire DESC, u.nom, u.prenom";
$charges_result = $mysqli->query($charges_query);

// Récupérer la liste des enseignants
$enseignants_query = "SELECT id, nom, prenom, email FROM utilisateurs WHERE type_utilisateur = 'enseignant' ORDER BY nom, prenom";
$enseignants_result = $mysqli->query($enseignants_query);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Charges Minimales</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --background-dark: #1a1a1a;
        }

        body {
            background: var(--background-dark);
            color: #fff;
            min-height: 100vh;
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

        .card-custom {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
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

        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.2);
            border-color: var(--secondary-color);
            color: white;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .btn-primary {
            background: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .btn-primary:hover {
            background: #2980b9;
            border-color: #2980b9;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border-color: #28a745;
            color: #d4edda;
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            border-color: #dc3545;
            color: #f8d7da;
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
    <a href="gestion_charges_minimales.php">Gestion des charges minimales</a>
    <a href="Notification_non-respect_charge_minimale.php">Notification en cas de non-respect de la charge minimale</a>
    <a href="Consulter_modules_assurés_assure.php">Consulter la liste des modules assurés et qu'il assure.</a>
    <a href="Uploader_notes_session_normale_rattrapage.php">Uploader les notes de la session normale et rattrapage.</a>
    <a href="Consulter_historique_années_passées.">Consulter l'historique des années passées.</a>
    <a href="?logout=true" class="btn btn-danger w-100 mt-3">Déconnexion</a>
</div>

<!-- Contenu principal -->
<div class="main-content">
    <h1 class="mb-4"><i class="fas fa-clock me-2"></i>Gestion des Charges Minimales</h1>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Formulaire d'ajout -->
    <div class="card-custom">
        <h3><i class="fas fa-plus me-2"></i>Définir une charge minimale</h3>
        <form method="POST">
            <div class="row">
                <div class="col-md-4">
                    <label for="id_enseignant" class="form-label">Enseignant</label>
                    <select name="id_enseignant" id="id_enseignant" class="form-control" required>
                        <option value="">Sélectionner un enseignant</option>
                        <?php while ($enseignant = $enseignants_result->fetch_assoc()): ?>
                            <option value="<?= $enseignant['id'] ?>">
                                <?= htmlspecialchars($enseignant['nom'] . ' ' . $enseignant['prenom']) ?> (ID: <?= $enseignant['id'] ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="annee_scolaire" class="form-label">Année scolaire</label>
                    <select name="annee_scolaire" id="annee_scolaire" class="form-control" required>
                        <option value="2024-2025" selected>2024-2025</option>
                        <option value="2025-2026">2025-2026</option>
                        <option value="2023-2024">2023-2024</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="charge_min" class="form-label">Charge minimale (heures)</label>
                    <input type="number" name="charge_min" id="charge_min" class="form-control"
                           value="192" min="0" max="500" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" name="ajouter_charge" class="btn btn-primary w-100">
                        <i class="fas fa-save me-1"></i>Enregistrer
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Liste des charges minimales -->
    <div class="card-custom">
        <h3><i class="fas fa-list me-2"></i>Charges minimales définies</h3>

        <?php if ($charges_result && $charges_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Enseignant</th>
                            <th>Email</th>
                            <th>Année scolaire</th>
                            <th>Charge minimale</th>
                            <th>Date création</th>
                            <th>Dernière modification</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($charge = $charges_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($charge['nom'] . ' ' . $charge['prenom']) ?></strong>
                                    <br><small class="text-muted">ID: <?= $charge['id_enseignant'] ?></small>
                                </td>
                                <td><?= htmlspecialchars($charge['email']) ?></td>
                                <td><span class="badge bg-primary"><?= $charge['annee_scolaire'] ?></span></td>
                                <td><strong><?= $charge['charge_min'] ?>h</strong></td>
                                <td><?= date('d/m/Y H:i', strtotime($charge['date_creation'])) ?></td>
                                <td>
                                    <?= $charge['date_modification'] ? date('d/m/Y H:i', strtotime($charge['date_modification'])) : 'Jamais' ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;"
                                          onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette charge minimale ?')">
                                        <input type="hidden" name="id_charge" value="<?= $charge['id'] ?>">
                                        <button type="submit" name="supprimer_charge" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <p class="text-muted">Aucune charge minimale définie pour le moment.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Informations -->
    <div class="card-custom">
        <h4><i class="fas fa-info-circle me-2"></i>Informations</h4>
        <ul>
            <li><strong>Charge minimale par défaut :</strong> 192 heures par année scolaire</li>
            <li><strong>Modification :</strong> Si une charge existe déjà pour un enseignant/année, elle sera mise à jour</li>
            <li><strong>Calcul automatique :</strong> Ces valeurs sont utilisées dans le calcul de charge horaire</li>
        </ul>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
