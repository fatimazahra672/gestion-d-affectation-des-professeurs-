<?php
require_once 'config.php';
session_start();

// Vérifier si l'utilisateur est connecté et est un coordinateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'coordinateur') {
    header("Location: login_coordinateur.php");
    exit;
}

// Définir une constante pour indiquer que ce fichier est inclus
define('INCLUDED_FILE', true);

// Inclure le fichier de logique
include 'emplois_temps.php';

// Inclure le script d'enregistrement des visites
require_once 'record_page_visit.php';
recordPageVisit('emplois_temps_complet.php', 'coordinateur');
?>

<div class="content" style="margin-left: 250px; padding: 30px;">
    <?php if ($page === 'liste'): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Liste des emplois du temps</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered data-table" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Jour</th>
                                <th>Horaire</th>
                                <th>Groupe</th>
                                <th>Salle</th>
                                <th>UE</th>
                                <th>Enseignant</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($emplois as $emploi): ?>
                                <tr>
                                    <td><?= htmlspecialchars($emploi['jour']) ?></td>
                                    <td><?= htmlspecialchars($emploi['heure_debut']) ?> - <?= htmlspecialchars($emploi['heure_fin']) ?></td>
                                    <td><?= htmlspecialchars($emploi['nom_groupe']) ?></td>
                                    <td><?= htmlspecialchars($emploi['nom_salle']) ?></td>
                                    <td><?= htmlspecialchars($emploi['nom_ue']) ?></td>
                                    <td><?= htmlspecialchars($emploi['nom_enseignant'] ?? 'Non assigné') ?></td>
                                    <td>
                                        <a href="?page=modifier&id=<?= $emploi['id_emploi'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete=<?= $emploi['id_emploi'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet emploi du temps ?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php elseif ($page === 'ajouter'): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Ajouter un emploi du temps</h6>
            </div>
            <div class="card-body">
                <form method="post" action="emplois_temps_complet.php" class="animated-form">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="jour" class="form-label">Jour</label>
                            <select class="form-select" id="jour" name="jour" required>
                                <option value="">Sélectionner un jour</option>
                                <option value="Lundi">Lundi</option>
                                <option value="Mardi">Mardi</option>
                                <option value="Mercredi">Mercredi</option>
                                <option value="Jeudi">Jeudi</option>
                                <option value="Vendredi">Vendredi</option>
                                <option value="Samedi">Samedi</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="heure_debut" class="form-label">Heure de début</label>
                            <input type="time" class="form-control" id="heure_debut" name="heure_debut" required>
                        </div>
                        <div class="col-md-3">
                            <label for="heure_fin" class="form-label">Heure de fin</label>
                            <input type="time" class="form-control" id="heure_fin" name="heure_fin" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="id_groupe" class="form-label">Groupe</label>
                            <select class="form-select" id="id_groupe" name="id_groupe" required>
                                <option value="">Sélectionner un groupe</option>
                                <?php foreach ($groupes as $groupe): ?>
                                    <option value="<?= $groupe['id_groupe'] ?>"><?= htmlspecialchars($groupe['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="id_salle" class="form-label">Salle</label>
                            <select class="form-select" id="id_salle" name="id_salle" required>
                                <option value="">Sélectionner une salle</option>
                                <?php foreach ($salles as $salle): ?>
                                    <option value="<?= $salle['id_salle'] ?>"><?= htmlspecialchars($salle['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="id_ue" class="form-label">Unité d'enseignement</label>
                            <select class="form-select" id="id_ue" name="id_ue" required>
                                <option value="">Sélectionner une UE</option>
                                <?php foreach ($ues as $ue): ?>
                                    <option value="<?= $ue['id_ue'] ?>"><?= htmlspecialchars($ue['code_ue'] . ' - ' . $ue['intitule']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="id_enseignant" class="form-label">Enseignant (optionnel)</label>
                            <select class="form-select" id="id_enseignant" name="id_enseignant">
                                <option value="">Sélectionner un enseignant</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                    <option value="<?= $enseignant['id'] ?>"><?= htmlspecialchars($enseignant['nom'] . ' ' . $enseignant['prenom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="ajouter" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                        <a href="?page=liste" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    <?php elseif ($page === 'modifier' && isset($_GET['id'])): ?>
        <?php
        // Récupérer les informations de l'emploi du temps à modifier
        $id = (int)$_GET['id'];
        $stmt = $conn->prepare("
            SELECT * FROM emplois_temps WHERE id_emploi = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $emploi = $stmt->get_result()->fetch_assoc();

        if (!$emploi) {
            echo '<div class="alert alert-danger">Emploi du temps non trouvé.</div>';
        } else {
        ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Modifier un emploi du temps</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="emplois_temps_complet.php" class="animated-form">
                        <input type="hidden" name="id" value="<?= $emploi['id_emploi'] ?>">
                        <!-- Reste du formulaire de modification -->
                    </form>
                </div>
            </div>
        <?php } ?>
    <?php endif; ?>
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
