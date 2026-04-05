<?php
/**
 * Liga WOC - Admin Tournament Management
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/TournamentController.php';
if (!isAdmin()) redirect('dashboard');
$pageTitle = 'Gestionar Torneos';
$page = 'admin';

$tournCtrl = new TournamentController();
$db = Database::getInstance();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create' || $postAction === 'update') {
        $data = [
            'name' => sanitize($_POST['name']),
            'description' => $_POST['description'] ?? '',
            'rules' => $_POST['rules'] ?? '',
            'format' => $_POST['format'],
            'team_size' => intval($_POST['team_size'] ?? 5),
            'substitutes' => intval($_POST['substitutes'] ?? 0),
            'max_teams' => intval($_POST['max_teams'] ?? 16),
            'min_rank' => $_POST['min_rank'] ?? null,
            'entry_fee' => floatval($_POST['entry_fee'] ?? 0),
            'prize_pool' => sanitize($_POST['prize_pool'] ?? ''),
            'status' => $_POST['status'] ?? 'draft',
            'registration_start' => $_POST['registration_start'] ?: null,
            'registration_end' => $_POST['registration_end'] ?: null,
            'start_date' => $_POST['start_date'] ?: null,
            'end_date' => $_POST['end_date'] ?: null,
            'stream_url' => sanitize($_POST['stream_url'] ?? ''),
            'season_id' => intval($_POST['season_id'] ?? 0) ?: null,
            'is_main_tournament' => isset($_POST['is_main_tournament']) ? 1 : 0,
        ];

        // Handle image
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $fn = 'tournament_' . time() . '.' . $ext;
            $dir = UPLOAD_PATH . 'tournaments/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            move_uploaded_file($_FILES['image']['tmp_name'], $dir . $fn);
            $data['image'] = 'tournaments/' . $fn;
        }

        if ($postAction === 'create') {
            $tournCtrl->createTournament($data);
            setFlash('success', 'Torneo creado exitosamente.');
        } else {
            $tournCtrl->updateTournament(intval($_POST['tournament_id']), $data);
            setFlash('success', 'Torneo actualizado.');
        }
        redirect('admin/tournaments');
    }

    if ($postAction === 'create_preset') {
        $tournCtrl->createPresetTournament();
        setFlash('success', '🏆 Torneo LIGA WOC creado exitosamente con inscripciones abiertas.');
        redirect('admin/tournaments');
    }

    if ($postAction === 'generate_bracket') {
        $tournCtrl->generateBracket(intval($_POST['tournament_id']));
        setFlash('success', 'Bracket generado exitosamente.');
        redirect('admin/tournaments');
    }

    if ($postAction === 'verify_result') {
        $matchId = intval($_POST['match_id']);
        $db->update("UPDATE match_results SET verification_status = 'verified', verified_by = ? WHERE match_id = ?", [currentUserId(), $matchId]);
        setFlash('success', 'Resultado verificado.');
        redirect('admin/tournaments');
    }

    if ($postAction === 'delete') {
        $tournamentId = intval($_POST['tournament_id']);
        if ($tournCtrl->deleteTournament($tournamentId)) {
            setFlash('success', 'Torneo eliminado exitosamente junto con todas sus partidas y datos relacionados.');
        } else {
            setFlash('error', 'No se pudo eliminar el torneo.');
        }
        redirect('admin/tournaments');
    }

    if ($postAction === 'expel_team') {
        $teamId = intval($_POST['team_id']);
        $tournamentId = intval($_POST['tournament_id']);
        $db->delete("DELETE FROM tournament_teams WHERE tournament_id = ? AND team_id = ?", [$tournamentId, $teamId]);
        setFlash('success', 'Equipo expulsado del torneo.');
        redirect('admin/tournaments?edit=' . $tournamentId);
    }
}

$result = $tournCtrl->getAll(max(1, intval($_GET['p'] ?? 1)));
$seasons = $db->fetchAll("SELECT * FROM seasons ORDER BY start_date DESC");
$editTournament = null;
if (isset($_GET['edit'])) {
    $editTournament = $tournCtrl->getTournament(intval($_GET['edit']));
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div class="main-content">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:12px;">
        <div><h1 class="page-title">🏆 Gestionar Torneos</h1></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <form method="POST" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create_preset">
                <button type="submit" class="btn btn-success" onclick="return confirm('¿Crear un nuevo torneo LIGA WOC (32 equipos, fase de grupos + llaves)? Se vinculará a la temporada activa.')">
                    🏆 Crear LIGA WOC
                </button>
            </form>
            <button class="btn btn-primary" onclick="document.getElementById('tourn-form').style.display=document.getElementById('tourn-form').style.display==='none'?'block':'none'">
                ➕ <?= $editTournament ? 'Editando' : 'Nuevo Torneo' ?>
            </button>
        </div>
    </div>

    <!-- Create/Edit Form -->
    <div class="card mb-3" id="tourn-form" style="display:<?= $editTournament ? 'block' : 'none' ?>;">
        <form method="POST" action="<?= url('admin/tournaments') ?>" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="<?= $editTournament ? 'update' : 'create' ?>">
            <input type="hidden" name="tournament_id" value="<?= $editTournament['id'] ?? '' ?>">
            
            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label class="form-label">Nombre del Torneo *</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($editTournament['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Formato *</label>
                    <select name="format" class="form-control" required>
                        <?php foreach (TOURNAMENT_FORMATS as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($editTournament['format'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Tamaño de Equipo (Obligatorio)</label>
                    <input type="number" name="team_size" class="form-control" value="<?= $editTournament['team_size'] ?? 5 ?>" min="1" max="10">
                </div>
                <div class="form-group">
                    <label class="form-label">Suplentes (Opcional)</label>
                    <input type="number" name="substitutes" class="form-control" value="<?= $editTournament['substitutes'] ?? 0 ?>" min="0" max="5">
                </div>
                <div class="form-group">
                    <label class="form-label">Máx. Equipos</label>
                    <input type="number" name="max_teams" class="form-control" value="<?= $editTournament['max_teams'] ?? 16 ?>" min="2">
                </div>
                <div class="form-group">
                    <label class="form-label">Inscripción ($)</label>
                    <input type="number" step="0.01" name="entry_fee" class="form-control" value="<?= $editTournament['entry_fee'] ?? 0 ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Estado</label>
                    <select name="status" class="form-control">
                        <option value="draft" <?= ($editTournament['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Borrador</option>
                        <option value="registration" <?= ($editTournament['status'] ?? '') === 'registration' ? 'selected' : '' ?>>Inscripciones</option>
                        <option value="ready" <?= ($editTournament['status'] ?? '') === 'ready' ? 'selected' : '' ?>>Listo</option>
                        <option value="in_progress" <?= ($editTournament['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>En Curso</option>
                        <option value="completed" <?= ($editTournament['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Finalizado</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group"><label class="form-label">Inicio Inscripciones</label><input type="datetime-local" name="registration_start" class="form-control" value="<?= $editTournament ? ($editTournament['registration_start'] ? date('Y-m-d\TH:i', strtotime($editTournament['registration_start'])) : '') : '' ?>"></div>
                <div class="form-group"><label class="form-label">Fin Inscripciones</label><input type="datetime-local" name="registration_end" class="form-control" value="<?= $editTournament ? ($editTournament['registration_end'] ? date('Y-m-d\TH:i', strtotime($editTournament['registration_end'])) : '') : '' ?>"></div>
                <div class="form-group"><label class="form-label">Inicio Torneo</label><input type="datetime-local" name="start_date" class="form-control" value="<?= $editTournament ? ($editTournament['start_date'] ? date('Y-m-d\TH:i', strtotime($editTournament['start_date'])) : '') : '' ?>"></div>
                <div class="form-group"><label class="form-label">Fin Torneo</label><input type="datetime-local" name="end_date" class="form-control" value="<?= $editTournament ? ($editTournament['end_date'] ? date('Y-m-d\TH:i', strtotime($editTournament['end_date'])) : '') : '' ?>"></div>
            </div>

            <div class="form-group">
                <label class="form-label">Descripción</label>
                <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($editTournament['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Reglas</label>
                <textarea name="rules" class="form-control" rows="4"><?= htmlspecialchars($editTournament['rules'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group"><label class="form-label">Premios</label><input type="text" name="prize_pool" class="form-control" value="<?= htmlspecialchars($editTournament['prize_pool'] ?? '') ?>" placeholder="1er: $100, 2do: $50..."></div>
                <div class="form-group"><label class="form-label">URL del Stream</label><input type="url" name="stream_url" class="form-control" value="<?= htmlspecialchars($editTournament['stream_url'] ?? '') ?>"></div>
                <div class="form-group"><label class="form-label">Temporada</label>
                    <select name="season_id" class="form-control"><option value="">Sin temporada</option>
                    <?php foreach ($seasons as $s): ?><option value="<?= $s['id'] ?>" <?= ($editTournament['season_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label class="form-label">Imagen</label><input type="file" name="image" class="form-control" accept="image/*"></div>
            </div>

            <div class="form-group" style="margin-top:12px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="is_main_tournament" value="1" <?= ($editTournament['is_main_tournament'] ?? 0) ? 'checked' : '' ?>>
                    <span class="form-label" style="margin:0;">⭐ Torneo Principal (cuenta para el ranking global)</span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary">💾 <?= $editTournament ? 'Actualizar' : 'Crear Torneo' ?></button>
        </form>
    </div>

    <?php if ($editTournament): ?>
    <div class="card mb-3">
        <h3 class="card-title">📋 Equipos Inscritos (<?= $editTournament['registered_teams'] ?? 0 ?>)</h3>
        <?php
        $registeredTeams = $tournCtrl->getRegisteredTeams($editTournament['id']);
        if (!empty($registeredTeams)):
        ?>
        <div class="table-wrapper">
            <table class="table">
                <thead><tr><th>#</th><th>Equipo</th><th>Capitán</th><th>Estado</th><th>Roster</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($registeredTeams as $i => $rt): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <?php if ($rt['team_logo']): ?>
                            <img src="<?= UPLOAD_URL . $rt['team_logo'] ?>" style="width:24px;height:24px;border-radius:50%;object-fit:cover;margin-right:6px;">
                        <?php endif; ?>
                        <strong><?= htmlspecialchars($rt['team_name']) ?></strong>
                        <?php if ($rt['team_tag']): ?><span class="badge badge-purple" style="font-size:0.6rem;"><?= $rt['team_tag'] ?></span><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($rt['captain_name']) ?></td>
                    <td><span class="badge badge-<?= $rt['status'] === 'registered' ? 'green' : 'yellow' ?>"><?= ucfirst($rt['status']) ?></span></td>
                    <td>
                        <?php 
                        $roster = json_decode($rt['roster'] ?? '[]', true);
                        $subs = json_decode($rt['substitute_roster'] ?? '[]', true);
                        echo count($roster) . ' principales';
                        if (!empty($subs)) echo ' + ' . count($subs) . ' suplentes';
                        ?>
                    </td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="expel_team">
                            <input type="hidden" name="team_id" value="<?= $rt['team_id'] ?>">
                            <input type="hidden" name="tournament_id" value="<?= $editTournament['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Expulsar a <?= htmlspecialchars($rt['team_name'], ENT_QUOTES) ?> de este torneo?')">🚫 Expulsar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <p>No hay equipos inscritos aún</p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Tournaments List -->
    <div class="card">
        <div class="table-wrapper">
            <table class="table">
                <thead><tr><th>Torneo</th><th>Formato</th><th>Equipos</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($result['tournaments'] as $t): ?>
                <tr>
                    <td style="font-weight:600;">
                        <?= htmlspecialchars($t['name']) ?>
                        <?php if (!empty($t['is_main_tournament'])): ?>
                        <span class="badge badge-yellow" style="font-size:0.65rem;margin-left:4px;">⭐ PRINCIPAL</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-purple"><?= TOURNAMENT_FORMATS[$t['format']] ?? $t['format'] ?></span></td>
                    <td><?= $t['registered_teams'] ?>/<?= $t['max_teams'] ?></td>
                    <td>
                        <?php $colors = ['draft'=>'yellow','registration'=>'green','ready'=>'blue','in_progress'=>'blue','completed'=>'purple']; ?>
                        <span class="badge badge-<?= $colors[$t['status']] ?? 'purple' ?>"><?= ucfirst(str_replace('_',' ',$t['status'])) ?></span>
                    </td>
                    <td style="font-size:0.8rem;"><?= $t['start_date'] ? date('d M Y', strtotime($t['start_date'])) : '-' ?></td>
                    <td>
                        <div style="display:flex;gap:4px;flex-wrap:wrap;">
                            <a href="<?= url('admin/tournaments?edit=' . $t['id']) ?>" class="btn btn-sm btn-secondary">✏️</a>
                            <a href="<?= url('tournaments/view/' . $t['id']) ?>" class="btn btn-sm btn-secondary" target="_blank">👁</a>
                            <a href="<?= url('admin/tournament-matches?id=' . $t['id']) ?>" class="btn btn-sm btn-primary">⚔️ Partidas</a>
                            <?php if (in_array($t['status'], ['ready', 'registration']) && $t['registered_teams'] >= 2): ?>
                            <form method="POST" style="display:inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="generate_bracket">
                                <input type="hidden" name="tournament_id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('¿Generar bracket? Esto creará los enfrentamientos.')">🎲 Bracket</button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="tournament_id" value="<?= $t['id'] ?>">
                                <?php
                                $deleteMsg = in_array($t['status'], ['draft', 'cancelled'])
                                    ? '¿Eliminar este torneo? Esta acción no se puede deshacer.'
                                    : '⚠️ ADVERTENCIA: Este torneo tiene partidas/resultados. Se eliminarán TODOS los datos (partidas, resultados, estadísticas). ¿Estás seguro?';
                                ?>
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('<?= $deleteMsg ?>')">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
