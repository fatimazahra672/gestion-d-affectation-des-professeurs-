<?php
require_once 'config.php';
session_start();

// Vérification de l'authentification et des droits d'administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Récupération du type d'utilisateur à gérer
$type_utilisateur = isset($_GET['type']) ? $_GET['type'] : 'all';

// Titres et descriptions selon le type
$titres = [
    'all' => 'Tous les utilisateurs',
    'chef_departement' => 'Chefs de département',
    'coordinateur' => 'Coordinateurs',
    'enseignant' => 'Enseignants'
];

$descriptions = [
    'all' => 'Gestion de tous les utilisateurs du système',
    'chef_departement' => 'Gestion des chefs de département',
    'coordinateur' => 'Gestion des coordinateurs de modules',
    'enseignant' => 'Gestion des enseignants et de leurs charges d\'enseignement'
];

$titre = isset($titres[$type_utilisateur]) ? $titres[$type_utilisateur] : $titres['all'];
$description = isset($descriptions[$type_utilisateur]) ? $descriptions[$type_utilisateur] : $descriptions['all'];

// Connexion à la base de données
try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Requête SQL pour récupérer les enseignants
    if ($type_utilisateur === 'enseignant') {
        $sql = "SELECT * FROM enseignants";
        $stmt = $pdo->query($sql);
        $utilisateurs = $stmt->fetchAll();
    } else {
        // Pour les autres types d'utilisateurs (chef_departement, coordinateur)
        // Vous devrez adapter cette partie selon votre structure de base de données
        $utilisateurs = [];
    }

} catch(PDOException $e) {
    die("Erreur de base de données : " . htmlspecialchars($e->getMessage()));
}

// Traitement des actions (ajout, modification, suppression)
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'ajouter':
                // Code pour ajouter un utilisateur
                try {
                    if ($_POST['type_utilisateur'] === 'enseignant') {
                        $sql = "INSERT INTO enseignants (email, mot_de_passe, nom, prenom, specialite, charge_minimale)
                                VALUES (:email, :mot_de_passe, :nom, :prenom, :specialite, :charge_minimale)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            'email' => $_POST['email'],
                            'mot_de_passe' => password_hash($_POST['mot_de_passe'], PASSWORD_DEFAULT),
                            'nom' => $_POST['nom'],
                            'prenom' => $_POST['prenom'],
                            'specialite' => $_POST['specialite'],
                            'charge_minimale' => $_POST['charge_minimale'] ?: null
                        ]);
                    } else {
                        // Pour les autres types d'utilisateurs
                        throw new Exception("Type d'utilisateur non pris en charge pour l'ajout");
                    }
                    $message = "Utilisateur ajouté avec succès.";
                    $messageType = "success";
                } catch(PDOException $e) {
                    $message = "Erreur lors de l'ajout : " . $e->getMessage();
                    $messageType = "danger";
                }
                break;

            case 'modifier':
                // Code pour modifier un utilisateur
                try {
                    if ($_POST['type_utilisateur'] === 'enseignant') {
                        $sql = "UPDATE enseignants SET
                                email = :email,
                                nom = :nom,
                                prenom = :prenom,
                                specialite = :specialite,
                                charge_minimale = :charge_minimale";

                        // Ajouter la mise à jour du mot de passe uniquement s'il est fourni
                        if (!empty($_POST['mot_de_passe'])) {
                            $sql .= ", mot_de_passe = :mot_de_passe";
                        }

                        $sql .= " WHERE id_enseignant = :id";

                        $stmt = $pdo->prepare($sql);

                        $params = [
                            'email' => $_POST['email'],
                            'nom' => $_POST['nom'],
                            'prenom' => $_POST['prenom'],
                            'specialite' => $_POST['specialite'],
                            'charge_minimale' => $_POST['charge_minimale'] ?: null,
                            'id' => $_POST['id']
                        ];

                        if (!empty($_POST['mot_de_passe'])) {
                            $params['mot_de_passe'] = password_hash($_POST['mot_de_passe'], PASSWORD_DEFAULT);
                        }
                    } else {
                        // Pour les autres types d'utilisateurs
                        throw new Exception("Type d'utilisateur non pris en charge pour la modification");
                    }

                    $stmt->execute($params);
                    $message = "Utilisateur modifié avec succès.";
                    $messageType = "success";
                } catch(PDOException $e) {
                    $message = "Erreur lors de la modification : " . $e->getMessage();
                    $messageType = "danger";
                }
                break;

            case 'supprimer':
                // Code pour supprimer un utilisateur
                try {
                    if ($_POST['type_utilisateur'] === 'enseignant') {
                        $sql = "DELETE FROM enseignants WHERE id_enseignant = :id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute(['id' => $_POST['id']]);
                    } else {
                        // Pour les autres types d'utilisateurs
                        throw new Exception("Type d'utilisateur non pris en charge pour la suppression");
                    }
                    $message = "Utilisateur supprimé avec succès.";
                    $messageType = "success";
                } catch(PDOException $e) {
                    $message = "Erreur lors de la suppression : " . $e->getMessage();
                    $messageType = "danger";
                }
                break;
        }

        // Rediriger pour éviter la resoumission du formulaire
        header("Location: gestion_utilisateurs.php?type=" . urlencode($type_utilisateur) . "&message=" . urlencode($message) . "&messageType=" . urlencode($messageType));
        exit;
    }
}

// Récupérer le message de la redirection
if (isset($_GET['message']) && isset($_GET['messageType'])) {
    $message = $_GET['message'];
    $messageType = $_GET['messageType'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titre) ?> - ENSAH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1E90FF;
            --primary-magenta: magenta;
            --blue-transparent: rgba(30, 144, 255, 0.3);
            --dark-bg: #0a192f;
        }

        body {
            background: linear-gradient(rgba(10, 25, 47, 0.85), rgba(108, 27, 145, 0.85)),
            url('images/background.jpg') center center/cover fixed;
            color: white;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            background: rgba(44, 62, 80, 0.95);
            backdrop-filter: blur(5px);
            border-right: 2px solid var(--primary-blue);
            box-shadow: 4px 0 15px var(--blue-transparent);
            animation: borderGlow 8s infinite alternate;
        }

        .card {
            background: rgba(10, 25, 47, 0.9);
            border: 2px solid var(--primary-blue);
            border-radius: 10px;
            animation: cardBorderPulse 10s infinite;
        }

        .table {
            color: white;
        }

        .table thead {
            background: linear-gradient(90deg, var(--primary-blue) 0%, var(--dark-bg) 100%);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-magenta) 100%);
            border: none;
        }

        .btn-outline-primary {
            border-color: var(--primary-blue);
            color: var(--primary-blue);
        }

        .btn-outline-primary:hover {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-magenta) 100%);
            border-color: transparent;
        }

        .modal-content {
            background: rgba(10, 25, 47, 0.95);
            border: 2px solid var(--primary-blue);
        }

        .form-control, .form-select {
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid var(--primary-blue);
            color: white;
        }

        .form-control:focus, .form-select:focus {
            background-color: rgba(255, 255, 255, 0.15);
            border-color: var(--primary-magenta);
            color: white;
            box-shadow: 0 0 0 0.25rem rgba(255, 0, 255, 0.25);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .form-select option {
            background-color: var(--dark-bg);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Inclure la sidebar -->
            <?php include 'sidebar_admin.php'; ?>

            <!-- Contenu principal -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1><?= htmlspecialchars($titre) ?></h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ajouterModal">
                        <i class="fas fa-plus-circle me-2"></i>Ajouter un <?= strtolower(substr($titre, 0, -1)) ?>
                    </button>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h5 class="m-0 text-white"><i class="fas fa-users me-2"></i><?= htmlspecialchars($description) ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="utilisateursTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Email</th>
                                        <th>Nom</th>
                                        <th>Prénom</th>
                                        <th>Spécialité</th>
                                        <th>Charge Minimale</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($utilisateurs as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['id_enseignant']) ?></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td><?= htmlspecialchars($user['nom'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($user['prenom'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($user['specialite'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($user['charge_minimale'] ?? 'N/A') ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary me-1 btn-modifier"
                                                    data-id="<?= $user['id_enseignant'] ?>"
                                                    data-email="<?= htmlspecialchars($user['email']) ?>"
                                                    data-type="enseignant"
                                                    data-nom="<?= htmlspecialchars($user['nom'] ?? '') ?>"
                                                    data-prenom="<?= htmlspecialchars($user['prenom'] ?? '') ?>"
                                                    data-specialite="<?= htmlspecialchars($user['specialite'] ?? '') ?>"
                                                    data-charge="<?= htmlspecialchars($user['charge_minimale'] ?? '') ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger btn-supprimer"
                                                    data-id="<?= $user['id_enseignant'] ?>"
                                                    data-type="enseignant"
                                                    data-nom="<?= htmlspecialchars(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Ajouter -->
    <div class="modal fade" id="ajouterModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un <?= strtolower(substr($titre, 0, -1)) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="ajouter">

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="mot_de_passe" class="form-label">Mot de passe</label>
                            <input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe" required>
                        </div>

                        <input type="hidden" name="type_utilisateur" value="enseignant">

                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                        </div>

                        <div class="mb-3">
                            <label for="prenom" class="form-label">Prénom</label>
                            <input type="text" class="form-control" id="prenom" name="prenom" required>
                        </div>

                        <div class="mb-3">
                            <label for="specialite" class="form-label">Spécialité</label>
                            <input type="text" class="form-control" id="specialite" name="specialite" required>
                        </div>

                        <div class="mb-3">
                            <label for="charge_minimale" class="form-label">Charge Minimale</label>
                            <input type="number" class="form-control" id="charge_minimale" name="charge_minimale" min="0" step="1">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Modifier -->
    <div class="modal fade" id="modifierModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier l'utilisateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="modifier">
                        <input type="hidden" name="id" id="modifier_id">

                        <div class="mb-3">
                            <label for="modifier_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="modifier_email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="modifier_mot_de_passe" class="form-label">Mot de passe (laisser vide pour ne pas changer)</label>
                            <input type="password" class="form-control" id="modifier_mot_de_passe" name="mot_de_passe">
                        </div>

                        <input type="hidden" name="type_utilisateur" value="enseignant">

                        <div class="mb-3">
                            <label for="modifier_nom" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="modifier_nom" name="nom" required>
                        </div>

                        <div class="mb-3">
                            <label for="modifier_prenom" class="form-label">Prénom</label>
                            <input type="text" class="form-control" id="modifier_prenom" name="prenom" required>
                        </div>

                        <div class="mb-3">
                            <label for="modifier_specialite" class="form-label">Spécialité</label>
                            <input type="text" class="form-control" id="modifier_specialite" name="specialite" required>
                        </div>

                        <div class="mb-3">
                            <label for="modifier_charge_minimale" class="form-label">Charge Minimale</label>
                            <input type="number" class="form-control" id="modifier_charge_minimale" name="charge_minimale" min="0" step="1">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Supprimer -->
    <div class="modal fade" id="supprimerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer l'utilisateur <span id="supprimer_nom"></span> ?</p>
                    <p class="text-danger">Cette action est irréversible.</p>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="supprimer">
                    <input type="hidden" name="id" id="supprimer_id">
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger">Supprimer</button>
                    </div>
                </form>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialisation de DataTables
            $('#utilisateursTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                },
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn btn-sm btn-outline-primary me-1'
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        className: 'btn btn-sm btn-outline-primary me-1'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Imprimer',
                        className: 'btn btn-sm btn-outline-primary'
                    }
                ]
            });

            // Gestion du modal de modification
            $('.btn-modifier').click(function() {
                const id = $(this).data('id');
                const email = $(this).data('email');
                const type = $(this).data('type');
                const nom = $(this).data('nom');
                const prenom = $(this).data('prenom');
                const specialite = $(this).data('specialite');
                const charge = $(this).data('charge');

                $('#modifier_id').val(id);
                $('#modifier_email').val(email);
                $('#modifier_nom').val(nom);
                $('#modifier_prenom').val(prenom);
                $('#modifier_specialite').val(specialite);
                $('#modifier_charge_minimale').val(charge);

                $('#modifierModal').modal('show');
            });

            // Gestion du modal de suppression
            $('.btn-supprimer').click(function() {
                const id = $(this).data('id');
                const nom = $(this).data('nom');
                const type = $(this).data('type');

                $('#supprimer_id').val(id);
                $('#supprimer_nom').text(nom);
                $('<input>').attr({
                    type: 'hidden',
                    name: 'type_utilisateur',
                    value: type
                }).appendTo('#supprimerModal form');

                $('#supprimerModal').modal('show');
            });
        });
    </script>
</body>
</html>
