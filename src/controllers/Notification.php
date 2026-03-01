<?php
// Connexion à la base de données
$host = "localhost";
$dbname = "gestion_coordinteur";
$user = "root";
$pass = "";
$charge_minimale_attendue = 192; // seuil défini

try {
    // Établir la connexion avec la base de données
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer l'ID de l'enseignant connecté (vous pouvez adapter selon votre système de session)
    $id_enseignant_connecte = 19; // ID de l'enseignant connecté

    // Récupérer uniquement les informations de l'enseignant connecté
    $stmt = $pdo->prepare("
        SELECT u.nom, u.prenom, ch.id_utilisateur, ch.annee_scolaire, ch.charge_min
        FROM charge_horaire_minimale ch
        JOIN utilisateurs u ON ch.id_utilisateur = u.id
        WHERE u.type_utilisateur = 'enseignant' AND u.id = ?
        ORDER BY ch.annee_scolaire DESC
    ");
    $stmt->execute([$id_enseignant_connecte]);
    $enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les informations de base de l'enseignant connecté
    $enseignant_info_stmt = $pdo->prepare("
        SELECT nom, prenom FROM utilisateurs WHERE id = ? AND type_utilisateur = 'enseignant'
    ");
    $enseignant_info_stmt->execute([$id_enseignant_connecte]);
    $enseignant_info = $enseignant_info_stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Afficher une erreur en cas de connexion échouée
    die("Erreur de connexion : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification de non-respect de charge</title>
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
            <h1>Les Notifications</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <div>
                    <div class="user-info-label">Enseignant</div>
                    <div class="user-info-value"><?= htmlspecialchars($enseignant_info['prenom'] . ' ' . $enseignant_info['nom']) ?></div>
                </div>
            </div>
        </div>
    </header>

    <div class="main-container">
       <aside class="sidebar">
    <div class="sidebar-header">
        <!-- Cercle avec initiales seulement (supprimer l'ancienne image) -->
        
    <div class="sidebar-header">
               <div class="profile-circle text-white fw-bold" style="font-size: 38px">EN</div>
<h3 class="text-white fw-bold">Enseignant</h3>
            </div>
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
                        <a href="Notification_non-respect_charge_minimale.php" class="nav-link active">
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
            <div class="dashboard-section">
                <div class="section-header">
                    <h2 class="section-title">Ma Charge Horaire</h2>
                    <div>
                        <span class="badge badge-warning">Charge minimale requise : <?= $charge_minimale_attendue ?> heures</span>
                    </div>
                </div>

                <?php if (count($enseignants) > 0): ?>
                    <?php
                        // Calculer les statistiques pour l'enseignant connecté
                        $total_charges = count($enseignants);
                        $charges_ok = 0;
                        $charges_deficit = 0;
                        $charge_totale = 0;

                        foreach ($enseignants as $enseignant) {
                            $charge_totale += $enseignant['charge_min'];
                            if ($enseignant['charge_min'] >= $charge_minimale_attendue) {
                                $charges_ok++;
                            } else {
                                $charges_deficit++;
                            }
                        }

                        // Calculer la charge moyenne si plusieurs années
                        $charge_moyenne = $total_charges > 0 ? round($charge_totale / $total_charges, 1) : 0;
                    ?>

                    <!-- Cartes de statistiques personnelles -->
                    <div class="stats-container">
                        <div class="stat-card">
                            <h3>Charge Moyenne</h3>
                            <p class="value"><?= $charge_moyenne ?> h</p>
                        </div>
                        <div class="stat-card ok">
                            <h3>Années Conformes</h3>
                            <p class="value"><?= $charges_ok ?></p>
                        </div>
                        <div class="stat-card danger">
                            <h3>Années en Déficit</h3>
                            <p class="value"><?= $charges_deficit ?></p>
                        </div>
                    </div>

                    <!-- Tableau de mes charges horaires -->
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Année scolaire</th>
                                    <th>Charge horaire</th>
                                    <th>Écart par rapport au minimum</th>
                                    <th>État</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enseignants as $enseignant): ?>
                                    <?php
                                        // Déterminer l'état de l'enseignant
                                        $est_en_deficit = ($enseignant['charge_min'] < $charge_minimale_attendue);
                                        $etat = $est_en_deficit ? "En déficit" : "Conforme";
                                        $ecart = $enseignant['charge_min'] - $charge_minimale_attendue;
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($enseignant['annee_scolaire']) ?></strong></td>
                                        <td><strong><?= htmlspecialchars($enseignant['charge_min']) ?> h</strong></td>
                                        <td style="color: <?= $ecart >= 0 ? 'var(--success-green)' : 'var(--danger-red)' ?>">
                                            <strong><?= ($ecart >= 0 ? '+' : '') . $ecart ?> h</strong>
                                        </td>
                                        <td>
                                            <span class="badge <?= $est_en_deficit ? 'badge-danger' : 'badge-success' ?>">
                                                <?= $est_en_deficit ? '⚠ ' . $etat : '✅ ' . $etat ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <!-- Message en cas d'absence de charges horaires -->
                    <div class="no-data">
                        <h3>📭 Aucune charge horaire définie</h3>
                        <p>Aucune charge horaire n'a été trouvée pour votre compte.</p>
                        <p><small>Contactez l'administration pour définir vos charges horaires minimales.</small></p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animation des liens de la sidebar
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(5px)';
            });

            link.addEventListener('mouseleave', function() {
                this.style.transform = 'translateX(0)';
            });
        });
    </script>
</body>
</html>