<?php
session_start();

// Configuration de la base de données
$host = 'localhost';
$dbname = 'gestion_coordinteur';
$username = 'root';
$password = '';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// Vérification des droits
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'coordinateur') {
    header("Location: login_coordinateur.php");
    exit;
}

// Initialisation des variables
$message = '';
$erreur = '';
$specialites = [];
$user_type = $_SESSION['user']['type_utilisateur'] ?? 'Coordinateur';
$user_nom = $_SESSION['user']['nom'] ?? 'Utilisateur';

// Récupérer la liste des spécialités
try {
    $stmt = $db->query("SELECT id_specialite, nom_specialite FROM specialite ORDER BY nom_specialite");
    $specialites = $stmt->fetchAll();
} catch (Exception $e) {
    $erreur = "Erreur lors de la récupération des spécialités: " . $e->getMessage();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération et nettoyage des données
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $specialite_id = intval($_POST['specialite_id'] ?? 0);
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';

    // Validation des données
    $erreurs = [];
    
    if (empty($nom)) $erreurs[] = "Le nom est obligatoire";
    if (empty($prenom)) $erreurs[] = "Le prénom est obligatoire";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $erreurs[] = "Email invalide";
    if ($specialite_id <= 0) $erreurs[] = "Spécialité invalide";
    if (empty($mot_de_passe)) $erreurs[] = "Mot de passe obligatoire";
    if ($mot_de_passe !== $confirmation) $erreurs[] = "Les mots de passe ne correspondent pas";
    if (strlen($mot_de_passe) < 8) $erreurs[] = "Le mot de passe doit contenir au moins 8 caractères";

    if (empty($erreurs)) {
        try {
            $db->beginTransaction();

            // Vérifier si l'email existe déjà
            $stmt = $db->prepare("SELECT id FROM utilisateurs WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception("Cet email est déjà utilisé par un autre compte.");
            }

            // Vérifier que la spécialité existe
            $stmt = $db->prepare("SELECT id_specialite FROM specialite WHERE id_specialite = ?");
            $stmt->execute([$specialite_id]);
            if (!$stmt->fetch()) {
                throw new Exception("La spécialité sélectionnée n'existe pas.");
            }

            // Création du compte vacataire
            $stmt = $db->prepare("INSERT INTO utilisateurs 
                                (email, mot_de_passe, type_utilisateur, nom, prenom, id_specialite, id_filiere) 
                                VALUES (?, ?, 'vacataire', ?, ?, ?, NULL)");
            $stmt->execute([
                $email,
                password_hash($mot_de_passe, PASSWORD_DEFAULT),
                $nom,
                $prenom,
                $specialite_id
            ]);
            $id_utilisateur = $db->lastInsertId();

            $db->commit();
            $message = "Vacataire créé avec succès! ID: $id_utilisateur";
            
            // Réinitialisation du formulaire
            $_POST = [];
        } catch (Exception $e) {
            $db->rollBack();
            $erreur = "Erreur lors de la création du vacataire: " . $e->getMessage();
        }
    } else {
        $erreur = implode("<br>", $erreurs);
    }
}

// Informations pour le header
$annee_scolaire = $_SESSION['annee_scolaire'] ?? '2024-2025';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un vacataire</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* Styles spécifiques au formulaire */
        .form-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            max-width: 800px;
            margin: 0 auto;
        }

        .form-title {
            color: var(--dark-purple);
            text-align: center;
            margin-bottom: 30px;
            font-weight: 600;
        }

        .btn-purple {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
        }

        .btn-purple:hover {
            background: linear-gradient(135deg, var(--dark-purple) 0%, var(--primary-color) 100%);
            color: white;
        }

        .required:after {
            content: " *";
            color: red;
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
    <!-- En-tête avec logo ENSAH -->
    <div class="header">
        <div class="header-left">
            <div class="header-logo">
                <img src="https://upload.wikimedia.org/wikipedia/fr/thumb/5/5f/Logo_ENSAH.svg/1200px-Logo_ENSAH.svg.png" alt="Logo ENSAH">
            </div>
            <h1>Gestion des Vacataires</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-user"></i>
                <span class="user-info-label">Utilisateur:</span>
                <span class="user-info-value"><?= htmlspecialchars($user_nom) ?> (<?= htmlspecialchars($user_type) ?>)</span>
            </div>
            <div class="user-info">
                <i class="fas fa-calendar-alt"></i>
                <span class="user-info-label">Année:</span>
                <span class="user-info-value"><?= htmlspecialchars($annee_scolaire) ?></span>
            </div>
        </div>
    </div>

    <!-- Conteneur principal -->
    <div class="main-container">
        <!-- Sidebar Moderne avec sections dépliables -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($user_nom) ?>&background=8a2be2&color=fff" alt="Coordinateur">
                <h3><?= htmlspecialchars($user_nom) ?></h3>
            </div>
            
            <div class="sidebar-menu">
                <!-- Tableau de bord -->
                <div class="nav-item">
                    <a href="dashboard.php" class="nav-link">
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

                <div class="nav-item">
                        <a href="gestion_unites_enseignements.php" class="nav-link">
                            <i class="fas fa-book"></i>
                            <span>Unités d'enseignement</span>
                        </a>
                    </div>

                     <div class="submenu" id="coord-menu">
                    <div class="nav-item">
                        <a href="gerer_groupes.php" class="nav-link">
                            <i class="fas fa-users"></i>
                            <span>Gérer les groupes</span>
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
                            <span>créer compet vacataire</span>
                        </a>
                    </div>

                    
                    <div class="nav-item">
                        <a href="historique.php" class="nav-link">
                            <i class="fas fa-file-excel"></i>
                            <span>historique des années passées </span>
                        </a>
                    </div>

                    
                     <div class="nav-item">
                        <a href="Export_Exel.php" class="nav-link">
                            <i class="fas fa-file-excel"></i>
                            <span>Extraire en Excel</span>
                        </a>
                    </div>

                     <div class="nav-item">
                        <a href="#" class="nav-link">
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
            <div class="form-container">
                <h1 class="form-title">Ajouter un nouveau vacataire</h1>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><?= $message ?></div>
                <?php endif; ?>
                
                <?php if ($erreur): ?>
                    <div class="alert alert-danger"><?= $erreur ?></div>
                <?php endif; ?>
                
                <form method="POST" id="form-vacataire">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nom" class="form-label required">Nom</label>
                            <input type="text" class="form-control" id="nom" name="nom" required 
                                   value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="prenom" class="form-label required">Prénom</label>
                            <input type="text" class="form-control" id="prenom" name="prenom" required 
                                   value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label required">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="specialite_id" class="form-label required">Spécialité</label>
                        <select class="form-select" id="specialite_id" name="specialite_id" required>
                            <option value="">-- Sélectionnez une spécialité --</option>
                            <?php foreach ($specialites as $spec): ?>
                                <option value="<?= $spec['id_specialite'] ?>"
                                    <?= ($_POST['specialite_id'] ?? '') == $spec['id_specialite'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($spec['nom_specialite']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="mot_de_passe" class="form-label required">Mot de passe</label>
                            <input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe" required>
                            <div class="form-text">Minimum 8 caractères</div>
                        </div>
                        <div class="col-md-6">
                            <label for="confirmation" class="form-label required">Confirmation</label>
                            <input type="password" class="form-control" id="confirmation" name="confirmation" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-purple btn-lg w-100 py-2">
                        <i class="fas fa-user-plus me-2"></i> Créer le vacataire
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Validation du mot de passe
            $('#mot_de_passe').on('input', function() {
                const password = $(this).val();
                let strength = 0;
                
                if (password.length >= 8) strength++;
                if (password.length >= 12) strength++;
                if (/[A-Z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^A-Za-z0-9]/.test(password)) strength++;
                
                const texts = ['Très faible', 'Faible', 'Moyen', 'Fort', 'Très fort'];
                const colors = ['#dc3545', '#fd7e14', '#ffc107', '#28a745', '#20c997'];
                
                if (password.length > 0) {
                    const feedback = texts[strength - 1] || 'Très faible';
                    const color = colors[strength - 1] || '#dc3545';
                    $(this).next('.form-text').text(feedback).css('color', color);
                } else {
                    $(this).next('.form-text').text('Minimum 8 caractères').css('color', '#6c757d');
                }
            });

            // Validation du formulaire
            $('#form-vacataire').submit(function(e) {
                let valid = true;
                
                // Vérification mot de passe
                if ($('#mot_de_passe').val() !== $('#confirmation').val()) {
                    alert('Les mots de passe ne correspondent pas');
                    valid = false;
                }
                
                if (!valid) e.preventDefault();
                return valid;
            });

            // Gestion des sections dépliables
            document.querySelectorAll('.section-title').forEach(section => {
                section.addEventListener('click', function() {
                    const sectionId = this.id;
                    const menuId = sectionId.replace('section', 'menu');
                    const menu = document.getElementById(menuId);
                    const arrow = this.querySelector('.arrow');
                    
                    // Toggle menu
                    menu.classList.toggle('open');
                    
                    // Toggle arrow rotation
                    arrow.classList.toggle('rotated');
                });
            });
            
            // Ouvrir la section Coordinateur par défaut
            document.getElementById('coord-menu').classList.add('open');
            document.querySelector('#coord-section .arrow').classList.add('rotated');
            
            // Fermer la section Enseignant par défaut
            document.getElementById('teacher-menu').classList.remove('open');
        });
    </script>
</body>
</html>