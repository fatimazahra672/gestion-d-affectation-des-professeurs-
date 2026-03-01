<?php
require_once 'config.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chef_departement') {
    die("<div class='alert alert-danger'>Accès non autorisé</div>");
}

if (!isset($_GET['prof_id']) || !is_numeric($_GET['prof_id'])) {
    die("<div class='alert alert-danger'>ID professeur invalide</div>");
}

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

    // Récupération des infos du professeur
    $stmt = $pdo->prepare("
        SELECT id, nom, prenom, type, heures_max, heures_vacataire
        FROM professeurs
        WHERE id = ?
    ");
    $stmt->execute([$_GET['prof_id']]);
    $prof = $stmt->fetch();

    if (!$prof) {
        die("<div class='alert alert-danger'>Professeur non trouvé</div>");
    }

    // Récupération des affectations
    $stmt = $pdo->prepare("
        SELECT
            a.id,
            ue.id AS id_unite_enseignement,
            ue.intitule,
            ue.code_ue AS code,
            a.heures,
            ue.semestre,
            cp.statut
        FROM
            affectations a
        JOIN unites_enseignement ue ON a.id = ue.id
        LEFT JOIN choix_professeurs cp ON a.id = cp.id
        WHERE
            a.professeur_id = ?
        ORDER BY
            ue.semestre, ue.intitule
    ");
    $stmt->execute([$_GET['prof_id']]);
    $affectations = $stmt->fetchAll();

    // Calcul des totaux
    $totalHeures = array_sum(array_column($affectations, 'heures'));
    $maxHeures = ($prof['type'] === 'permanent') ? $prof['heures_max'] : $prof['heures_vacataire'];
    $pourcentage = ($maxHeures > 0) ? round(($totalHeures / $maxHeures) * 100) : 0;

    // Affichage
    echo '<div class="container-fluid">';

    // En-tête
    echo '<div class="row mb-4">
            <div class="col-md-6">
                <h4><i class="fas fa-user-tie me-2"></i> '.sanitize($prof['prenom'].' '.$prof['nom']).'</h4>
                <p><strong>Type:</strong> '.ucfirst($prof['type']).'</p>
                <p><strong>Heures max:</strong> '.$maxHeures.'h</p>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Charge horaire</h5>
                        <div class="progress mb-2" style="height: 30px;">
                            <div class="progress-bar bg-'.getAlertClass($pourcentage).'"
                                 style="width: '.$pourcentage.'%"
                                 role="progressbar">
                                '.$pourcentage.'% ('.$totalHeures.'h/'.$maxHeures.'h)
                            </div>
                        </div>';

    if ($totalHeures > $maxHeures) {
        echo '<p class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>
              Surcharge: +'.($totalHeures - $maxHeures).'h</p>';
    }

    echo '</div></div></div></div>';

    // Tableau des affectations
    echo '<div class="table-responsive">
            <table class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>Module</th>
                    <th>Code</th>
                    <th>Semestre</th>
                    <th>Volume Horaire</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($affectations as $aff) {
        echo '<tr>
                <td>'.sanitize($aff['intitule']).'</td>
                <td>'.sanitize($aff['code']).'</td>
                <td>'.sanitize($aff['semestre']).'</td>
                <td>
                    <div class="input-group">
                        <input type="number" class="form-control volume-input"
                               value="'.(int)$aff['heures'].'"
                               data-unite-id="'.(int)$aff['id_unite_enseignement'].'"
                               data-prof-id="'.(int)$_GET['prof_id'].'"
                               min="1" max="300">
                        <span class="input-group-text">h</span>
                    </div>
                </td>
                <td>
                    <span class="badge bg-'.($aff['statut'] === 'validee' ? 'success' : 'warning').'">
                        '.ucfirst($aff['statut'] ?? 'en attente').'
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-danger remove-module"
                            data-affectation-id="'.(int)$aff['id'].'">
                        <i class="fas fa-trash-alt me-1"></i> Retirer
                    </button>
                </td>
              </tr>';
    }

    echo '</tbody></table></div>';

} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Erreur de base de données : '.sanitize($e->getMessage()).'</div>';
}

function getAlertClass($pourcentage) {
    if ($pourcentage >= 100) return 'danger';
    if ($pourcentage >= 80) return 'warning';
    return 'success';
}

function sanitize($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}
?>