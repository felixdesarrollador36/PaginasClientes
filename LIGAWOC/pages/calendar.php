<?php
/**
 * Liga WOC — Calendario de Partidas
 */
$pageTitle = 'Calendario';
$pageCss = 'calendar';
$page = 'calendar';
$db = Database::getInstance();

// Determine month/year to display
$year  = isset($_GET['year'])  ? intval($_GET['year'])  : intval(date('Y'));
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$month = max(1, min(12, $month));

$firstDay   = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = (int) date('t', $firstDay);
$startDow   = (int) date('N', $firstDay); // 1=Mon..7=Sun

// Prev / Next month navigation
$prevMonth = $month - 1 < 1  ? 12 : $month - 1;
$prevYear  = $month - 1 < 1  ? $year - 1 : $year;
$nextMonth = $month + 1 > 12 ? 1  : $month + 1;
$nextYear  = $month + 1 > 12 ? $year + 1 : $year;

// Fetch matches that have a scheduled_at in this month
$rangeStart = sprintf('%04d-%02d-01 00:00:00', $year, $month);
$rangeEnd   = sprintf('%04d-%02d-%02d 23:59:59', $year, $month, $daysInMonth);

$matches = $db->fetchAll(
    "SELECT m.id, m.scheduled_at, m.status, m.round,
            t1.name as t1_name, t1.logo as t1_logo,
            t2.name as t2_name, t2.logo as t2_logo,
            tr.id as tournament_id, tr.name as tournament_name,
            m.team1_score, m.team2_score, m.winner_id
     FROM tournament_matches m
     LEFT JOIN teams t1 ON m.team1_id = t1.id
     LEFT JOIN teams t2 ON m.team2_id = t2.id
     LEFT JOIN tournaments tr ON m.tournament_id = tr.id
     WHERE m.scheduled_at BETWEEN ? AND ?
     ORDER BY m.scheduled_at ASC",
    [$rangeStart, $rangeEnd]
);

// Index matches by day
$byDay = [];
foreach ($matches as $m) {
    $day = intval(date('j', strtotime($m['scheduled_at'])));
    $byDay[$day][] = $m;
}

$monthNames = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
               'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$today = intval(date('j'));
$todayMonth = intval(date('n'));
$todayYear  = intval(date('Y'));

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div class="main-content">

    <div class="page-header cal-page-header">
        <h1 class="page-title">📅 Calendario de Partidas</h1>
        <p class="page-subtitle">Partidas programadas del mes</p>
    </div>

    <!-- Month Navigation -->
    <div class="cal-nav">
        <a href="<?= url('calendar?year=' . $prevYear . '&month=' . $prevMonth) ?>" class="btn btn-secondary btn-sm">
            ← <?= $monthNames[$prevMonth] ?>
        </a>
        <h2 class="cal-title">
            <?= $monthNames[$month] ?> <?= $year ?>
        </h2>
        <a href="<?= url('calendar?year=' . $nextYear . '&month=' . $nextMonth) ?>" class="btn btn-secondary btn-sm">
            <?= $monthNames[$nextMonth] ?> →
        </a>
    </div>

    <!-- Calendar Grid -->
    <div class="card cal-board">
        <!-- Day headers -->
        <div class="cal-grid-header">
            <span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span><span>Dom</span>
        </div>

        <div class="cal-grid">
            <?php
            // Empty cells before the first day
            for ($i = 1; $i < $startDow; $i++) {
                echo '<div class="cal-cell cal-empty"></div>';
            }

            for ($day = 1; $day <= $daysInMonth; $day++):
                $isToday = ($day === $today && $month === $todayMonth && $year === $todayYear);
                $hasMatches = isset($byDay[$day]);
            ?>
            <div class="cal-cell<?= $isToday ? ' cal-today' : '' ?><?= $hasMatches ? ' cal-has-matches' : '' ?>">
                <div class="cal-day-number"><?= $day ?></div>
                <?php if ($hasMatches): foreach ($byDay[$day] as $m): ?>
                <a href="<?= url('tournaments/view/' . $m['tournament_id']) ?>"
                   class="cal-match <?= $m['status'] === 'completed' ? 'cal-completed' : 'cal-scheduled' ?>"
                   title="<?= htmlspecialchars(($m['t1_name'] ?? 'TBD') . ' vs ' . ($m['t2_name'] ?? 'TBD') . ' — ' . $m['tournament_name']) ?>">
                    <span class="cal-match-time"><?= date('H:i', strtotime($m['scheduled_at'])) ?></span>
                    <span class="cal-match-teams"><?= htmlspecialchars(substr($m['t1_name'] ?? 'TBD', 0, 5)) ?> vs <?= htmlspecialchars(substr($m['t2_name'] ?? 'TBD', 0, 5)) ?></span>
                </a>
                <?php endforeach; endif; ?>
            </div>
            <?php endfor; ?>

            <?php
            // Trailing empty cells to complete last week row
            $lastDow = (int) date('N', mktime(0, 0, 0, $month, $daysInMonth, $year));
            for ($i = $lastDow + 1; $i <= 7; $i++) {
                echo '<div class="cal-cell cal-empty"></div>';
            }
            ?>
        </div>
    </div>

    <!-- Legend -->
    <div class="cal-legend">
        <span class="cal-legend-item"><span class="cal-legend-dot today"></span> Hoy</span>
        <span class="cal-legend-item"><span class="cal-legend-dot scheduled"></span> Programada</span>
        <span class="cal-legend-item"><span class="cal-legend-dot completed"></span> Completada</span>
    </div>

    <!-- Upcoming matches list -->
    <?php if (!empty($matches)): ?>
    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title">Partidas de <?= $monthNames[$month] ?></h3>
        </div>
        <div class="cal-list">
            <?php foreach ($matches as $m): ?>
            <a href="<?= url('tournaments/view/' . $m['tournament_id']) ?>"
               class="cal-list-item">
                <div class="cal-list-date">
                    <div class="cal-list-day"><?= date('d', strtotime($m['scheduled_at'])) ?></div>
                    <div class="cal-list-time"><?= date('H:i', strtotime($m['scheduled_at'])) ?></div>
                </div>
                <div class="cal-list-body">
                    <div class="cal-list-teams">
                        <?= htmlspecialchars($m['t1_name'] ?? 'TBD') ?>
                        <?php if ($m['status'] === 'completed'): ?>
                        <span class="cal-score"><?= $m['team1_score'] ?> - <?= $m['team2_score'] ?></span>
                        <?php else: ?>
                        <span class="cal-versus">vs</span>
                        <?php endif; ?>
                        <?= htmlspecialchars($m['t2_name'] ?? 'TBD') ?>
                    </div>
                    <div class="cal-list-meta">🏆 <?= htmlspecialchars($m['tournament_name']) ?> · R<?= $m['round'] ?></div>
                </div>
                <span class="badge <?= $m['status'] === 'completed' ? 'badge-green' : 'badge-blue' ?>"><?= $m['status'] === 'completed' ? 'Jugada' : 'Próxima' ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="empty-state cal-empty-state">
        <div class="empty-state-icon">📅</div>
        <h3 class="empty-state-title">Sin partidas programadas</h3>
        <p>No hay partidas con fecha asignada este mes.</p>
    </div>
    <?php endif; ?>

</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
