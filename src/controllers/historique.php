<?php
// Connexion à la base de données
$pdo = new PDO("mysql:host=localhost;dbname=gestion_coordinteur;charset=utf8", "root", "");

// Récupérer les années scolaires distinctes
$annees = $pdo->query("SELECT DISTINCT annee_scolaire FROM groupes ORDER BY annee_scolaire DESC")->fetchAll(PDO::FETCH_COLUMN);

// Traitement de l'année sélectionnée
$annee_select = isset($_GET['annee']) ? $_GET['annee'] : $annees[0];

// Récupérer les groupes de l'année
$sql_groupes = $pdo->prepare("SELECT * FROM groupes WHERE annee_scolaire = ?");
$sql_groupes->execute([$annee_select]);
$groupes = $sql_groupes->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Historique des années passées</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        /* Contenu principal */
        .main-content {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            background-color: #f8f9fc;
        }

        /* Formulaires et tableaux */
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
            color: white;
            padding: 15px;
            border-radius: 12px 12px 0 0;
            margin: -25px -25px 20px -25px;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark-purple);
        }

        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(106, 13, 173, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        /* Tableaux */
        .table {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .table thead {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
            color: white;
        }

        .table th {
            border-bottom: none;
        }

        .table td, .table th {
            padding: 12px 15px;
            vertical-align: middle;
        }

        .table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .table tr:hover {
            background-color: #f0f0f5;
        }

        /* Titres de groupes */
        .groupe-title {
            margin-top: 40px;
            font-size: 1.4rem;
            color: var(--primary-color);
            padding-left: 10px;
            margin-bottom: 20px;
            border-left: 4px solid var(--accent-color);
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

<!-- Header -->
<div class="header">
    <div class="header-left">
        <div class="header-logo">
            <img src="images/logo.png" alt="Logo">
        </div>
        <h1>Historique des années passées</h1>
    </div>
    <div class="header-right">
        <div class="user-info">
            <i class="fas fa-user"></i>
            <div>
                <div class="user-info-label">Coordinateur</div>
                
            </div>
        </div>
    </div>
</div>

<div class="main-container">
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <img src="image copy 5.png" alt="Profile">
            <h3>Menu Principal</h3>
        </div>
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="Affichage_liste_UE.php">
                        <i class="fas fa-list"></i>
                        <span>Listes  UE</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="souhaits_enseignants.php">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Souhaits Enseignants</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="Calcul_automatique_charge_horaire.php">
                        <i class="fas fa-calculator"></i>
                        <span>Charge horaire</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="Notification.php">
                        <i class="fas fa-bell"></i>
                        <span>Notifications</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="Consulter_modules.php">
                        <i class="fas fa-book"></i>
                        <span>Modules assurés</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="Uploader_notes.php">
                        <i class="fas fa-upload"></i>
                        <span>Upload notes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="historique.php">
                        <i class="fas fa-history"></i>
                        <span>Historique</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link logout-btn" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Déconnexion</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0"><i class="fas fa-history me-2"></i>Historique des années scolaires</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="annee" class="form-label">Choisissez une année scolaire :</label>
                            <select name="annee" id="annee" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($annees as $annee): ?>
                                    <option value="<?= htmlspecialchars($annee) ?>" <?= $annee === $annee_select ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($annee) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>

                <?php if (count($groupes) > 0): ?>
                    <?php foreach ($groupes as $groupe): ?>
                        <div class="groupe-title">
                            <?= htmlspecialchars($groupe['nom']) ?> 
                            (<?= htmlspecialchars($groupe['type']) ?> - <?= htmlspecialchars($groupe['filiere']) ?> <?= htmlspecialchars($groupe['niveau']) ?>)
                        </div>

                        <!-- Étudiants -->
                        <h5 class="mt-4 mb-3"><i class="fas fa-users me-2"></i>Étudiants</h5>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Numéro</th>
                                        <th>Nom</th>
                                        <th>Prénom</th>
                                        <th>Email</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $pdo->prepare("SELECT * FROM etudiants WHERE id_groupe = ?");
                                    $stmt->execute([$groupe['id_groupe']]);
                                    $etudiants = $stmt->fetchAll();
                                    foreach ($etudiants as $etudiant): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($etudiant['numero_etudiant']) ?></td>
                                            <td><?= htmlspecialchars($etudiant['nom']) ?></td>
                                            <td><?= htmlspecialchars($etudiant['prenom']) ?></td>
                                            <td><?= htmlspecialchars($etudiant['email']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Enseignants -->
                        <h5 class="mt-4 mb-3"><i class="fas fa-chalkboard-teacher me-2"></i>Enseignants</h5>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Prénom</th>
                                        <th>Rôle</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt2 = $pdo->prepare("SELECT e.nom, e.prenom, ge.role FROM groupes_enseignants ge
                                                            JOIN enseignants e ON ge.id_enseignant = e.id_enseignant
                                                            WHERE ge.id_groupe = ?");
                                    $stmt2->execute([$groupe['id_groupe']]);
                                    $enseignants = $stmt2->fetchAll();
                                    foreach ($enseignants as $ens): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($ens['nom']) ?></td>
                                            <td><?= htmlspecialchars($ens['prenom']) ?></td>
                                            <td><?= htmlspecialchars($ens['role']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Unités d'enseignement -->
                        <h5 class="mt-4 mb-3"><i class="fas fa-book-open me-2"></i>Unités d'enseignement</h5>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Nom</th>
                                        <th>Type</th>
                                        <th>Volume horaire</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt3 = $pdo->prepare("SELECT m.code, m.nom, ue.type_enseignement, ue.volume_horaire
                                                            FROM groupes_ue gu
                                                            JOIN unites_enseignements ue ON gu.id_ue = ue.id_ue
                                                            JOIN matieres m ON ue.id_matiere = m.id_matiere
                                                            WHERE gu.id_groupe = ?");
                                    $stmt3->execute([$groupe['id_groupe']]);
                                    $ues = $stmt3->fetchAll();
                                    foreach ($ues as $ue): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($ue['code']) ?></td>
                                            <td><?= htmlspecialchars($ue['nom']) ?></td>
                                            <td><?= htmlspecialchars($ue['type_enseignement']) ?></td>
                                            <td><?= htmlspecialchars($ue['volume_horaire']) ?>h</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Aucun groupe trouvé pour cette année scolaire.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Scripts Bootstrap et FontAwesome -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

</body>
</html>