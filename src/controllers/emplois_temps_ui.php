<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Emplois du Temps</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary-blue: #1E90FF;
            --primary-magenta: magenta;
            --blue-transparent: rgba(30, 144, 255, 0.3);
            --dark-bg: rgba(10, 25, 47, 0.85);
            --card-bg: rgba(10, 25, 47, 0.9);
            --hover-color: rgba(30, 144, 255, 0.2);
            --text-color: white;
            --border-radius: 10px;
            --box-shadow: 0 5px 15px var(--blue-transparent);
        }

        body {
            background: url('image copy 4.png') no-repeat center center fixed;
            background-size: cover;
            color: white;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            position: relative;
        }

        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(10, 25, 47, 0.85);
            z-index: -1;
        }

        .sidebar {
            width: 250px;
            background-color: rgba(10, 25, 47, 0.9);
            color: white;
            padding: 20px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            border-right: 1px solid var(--primary-blue);
            box-shadow: 0 0 20px var(--blue-transparent);
            animation: sidebarBorderPulse 8s infinite alternate;
        }

        @keyframes sidebarBorderPulse {
            0% { border-right-color: var(--primary-blue); }
            50% { border-right-color: var(--primary-magenta); }
            100% { border-right-color: var(--primary-blue); }
        }

        .sidebar h1 {
            font-size: 1.8rem;
            margin-bottom: 20px;
            text-align: center;
            color: var(--primary-blue);
            text-shadow: 0 0 10px var(--blue-transparent);
            animation: textPulse 5s infinite;
        }

        @keyframes textPulse {
            0% { text-shadow: 0 0 10px var(--primary-blue); }
            50% { text-shadow: 0 0 15px var(--primary-magenta); }
            100% { text-shadow: 0 0 10px var(--primary-blue); }
        }

        .sidebar nav ul {
            list-style: none;
            padding: 0;
        }

        .sidebar nav ul li {
            margin: 10px 0;
        }

        .sidebar nav ul li a {
            color: white;
            text-decoration: none;
            font-size: 1.1rem;
            display: block;
            padding: 10px;
            transition: all 0.3s ease;
            border-radius: 5px;
            border: 1px solid transparent;
        }

        .sidebar nav ul li a:hover {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-magenta) 100%);
            transform: translateX(5px);
            box-shadow: 0 5px 15px var(--blue-transparent);
        }

        .content {
            margin-left: 250px;
            padding: 30px;
        }

        .card {
            background-color: rgba(10, 25, 47, 0.9);
            border: 1px solid var(--primary-blue);
            border-radius: 10px;
            transition: all 0.3s ease;
            animation: cardBorderPulse 10s infinite;
            margin-bottom: 20px;
        }

        @keyframes cardBorderPulse {
            0% { border-color: var(--primary-blue); box-shadow: 0 5px 15px var(--blue-transparent); }
            50% { border-color: var(--primary-magenta); box-shadow: 0 5px 20px rgba(255, 0, 255, 0.3); }
            100% { border-color: var(--primary-blue); box-shadow: 0 5px 15px var(--blue-transparent); }
        }

        .card-header {
            background: linear-gradient(90deg, var(--primary-blue) 0%, #0a192f 100%) !important;
            border-bottom: 1px solid var(--primary-magenta) !important;
            padding: 1rem;
            transition: all 0.3s ease;
            color: white;
        }

        .card-body {
            color: white;
        }

        .form-control, .form-select {
            background-color: rgba(30, 144, 255, 0.1);
            border: 1px solid var(--primary-blue);
            color: white;
        }

        .form-control:focus, .form-select:focus {
            background-color: rgba(30, 144, 255, 0.2);
            border-color: var(--primary-magenta);
            box-shadow: 0 0 0 0.25rem rgba(255, 0, 255, 0.25);
            color: white;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-magenta) 100%);
            border: none;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px var(--blue-transparent);
        }

        table {
            background-color: rgba(10, 25, 47, 0.9);
            color: white;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid var(--primary-blue);
            animation: cardBorderPulse 10s infinite;
        }

        table th {
            background: linear-gradient(90deg, var(--primary-blue) 0%, #0a192f 100%) !important;
            color: white !important;
            border-bottom: 1px solid var(--primary-magenta) !important;
        }

        table td {
            border-bottom: 1px solid rgba(30, 144, 255, 0.3);
        }

        table tr:hover td {
            background-color: rgba(30, 144, 255, 0.2);
        }

        a {
            color: var(--primary-blue);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        a:hover {
            color: var(--primary-magenta);
            text-shadow: 0 0 5px var(--blue-transparent);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, var(--primary-magenta) 100%);
            border: none;
        }

        .top-navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--card-bg);
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            border: 1px solid var(--primary-blue);
            box-shadow: var(--box-shadow);
            animation: fadeInDown 0.5s;
        }

        .navbar-left h2 {
            margin: 0;
            color: var(--primary-blue);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
        }

        .navbar-left h2 i {
            margin-right: 10px;
            animation: pulse 2s infinite;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .search-box {
            position: relative;
            width: 250px;
        }

        .search-box input {
            width: 100%;
            padding: 8px 35px 8px 15px;
            border-radius: 20px;
            border: 1px solid var(--primary-blue);
            background-color: rgba(10, 25, 47, 0.7);
            color: white;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-magenta);
            box-shadow: 0 0 10px rgba(255, 0, 255, 0.3);
            width: 280px;
        }

        .search-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-blue);
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-buttons .btn {
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .action-buttons .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .table-container {
            animation: fadeIn 0.8s;
        }

        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h1>Gestion Coordinateur</h1>
        <nav>
            <ul>
                <li><a href="dashboard_coordinateur.php">Tableau de bord</a></li>
                <li><a href="gerer_groupes.php">Gérer les groupes</a></li>
                <li><a href="emplois_temps_complet.php" class="active">Emplois du temps</a></li>
                <li><a href="affectation_vactaire.php">Affectation vacataires</a></li>
                <li><a href="Definir_UE.php">Définir les UE</a></li>
            </ul>
        </nav>
    </div>

    <div class="content">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="top-navbar">
            <div class="navbar-left">
                <h2><i class="fas fa-calendar-alt"></i> Gestion des Emplois du Temps</h2>
            </div>
            <div class="navbar-right">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Rechercher un emploi du temps...">
                    <i class="fas fa-search search-icon"></i>
                </div>
                <div class="action-buttons">
                    <a href="emplois_temps_form.php" class="btn btn-sm btn-success"><i class="fas fa-plus"></i> Nouvel emploi du temps</a>
                    <a href="?page=liste" class="btn btn-sm btn-info"><i class="fas fa-list"></i> Liste des emplois du temps</a>
                    <a href="dashboard_coordinateur.php" class="btn btn-sm btn-secondary"><i class="fas fa-home"></i> Dashboard</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script pour la recherche dans le tableau
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
                    const searchValue = this.value.toLowerCase();
                    const tableRows = document.querySelectorAll('table tbody tr');

                    tableRows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        if (text.includes(searchValue)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>
