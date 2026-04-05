<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/RankingController.php';
require_once __DIR__ . '/controllers/TeamController.php';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

// --- CSS: main primero, luego heroes-responsive ---
echo '<link rel="stylesheet" href="assets/css/heroes-responsive.css">';

$db = Database::getInstance();
$rankingController = new RankingController();

// Get all base heroes
$baseHeroes = $db->fetchAll("SELECT * FROM ml_heroes ORDER BY name ASC");

// Get hero stats (Win Rate, Pick Rate) from tournaments
$heroStatsRows = $rankingController->getHeroRankings();

$heroStatsMap = [];
foreach ($heroStatsRows as $stat) {
    // Use lowercase key for case-insensitive matching (hero names from matches may differ in casing)
    $heroStatsMap[strtolower($stat['hero_name'])] = [
        'matches_played' => $stat['matches_played'],
        'matches_won'    => $stat['matches_won'],
        'total_kills'    => $stat['total_kills'],
        'total_deaths'   => $stat['total_deaths'],
        'total_assists'  => $stat['total_assists'],
        'win_rate'       => $stat['win_rate']
    ];
}

$heroes = [];
foreach ($baseHeroes as $hero) {
    $name = $hero['name'];
    $stats = $heroStatsMap[strtolower($name)] ?? [
        'matches_played' => 0, 'matches_won' => 0,
        'total_kills' => 0, 'total_deaths' => 0, 'total_assists' => 0,
        'win_rate' => 0
    ];
    $kda = $stats['total_deaths'] > 0 
           ? round(($stats['total_kills'] + $stats['total_assists']) / $stats['total_deaths'], 2) 
           : ($stats['total_kills'] + $stats['total_assists']);
           
    $heroes[] = [
        'id' => $hero['id'],
        'name' => $name,
        'role' => $hero['role'],
        'image_url' => $hero['image_url'],
        'matches_played' => $stats['matches_played'],
        'win_rate' => $stats['win_rate'],
        'kda' => $kda,
        'kills' => $stats['total_kills'],
        'deaths' => $stats['total_deaths'],
        'assists' => $stats['total_assists'],
    ];
}
?>

<div class="app-wrapper">
    <div class="main-content">
        <div class="page-header" style="text-align: center; margin-bottom: 2rem;">
            <h1 class="page-title">⚡ Héroes de Mobile Legends</h1>
            <p class="page-subtitle">Conoce a los campeones que dominarán la <span style="color: var(--neon-orchid); font-weight: bold;">Liga WOC</span></p>
            <div style="margin-top: 10px; font-family: 'Orbitron', sans-serif; color: var(--text-muted);">
                Total: <span id="hero-count"><?= count($heroes); ?></span> Héroes
            </div>
        </div>

        <div class="controls-container" style="max-width: 1200px; margin: 0 auto 2rem auto; padding: 0 1rem;">
            <div class="search-filter-row" style="display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-color);">
                <div class="search-box" style="flex: 1; min-width: 250px;">
                    <input type="text" id="hero-search" placeholder="🔍 Buscar héroe..." style="width: 100%; padding: 0.8rem 1rem; border-radius: 8px; border: 1px solid var(--border-color); background: rgba(255,255,255,0.05); color: #fff; font-size: 1rem;">
                </div>
                
                <div class="sort-box" style="min-width: 200px;">
                    <select id="hero-sort" style="width: 100%; padding: 0.8rem 1rem; border-radius: 8px; border: 1px solid var(--border-color); background: #1a1e29; color: #fff; font-size: 1rem; cursor: pointer;">
                        <option value="name_asc">A-Z</option>
                        <option value="name_desc">Z-A</option>
                        <option value="played_desc">Más Jugados</option>
                        <option value="winrate_desc">Mejor Win Rate</option>
                        <option value="kda_desc">Mejor KDA</option>
                    </select>
                </div>
            </div>

            <div class="role-filters" style="display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: center; margin-top: 1.5rem;">
                <?php 
                $roles = ['Todos', 'Tanque', 'Combatiente', 'Asesino', 'Mago', 'Tirador', 'Apoyo'];
                foreach ($roles as $idx => $rRole): 
                    $isActive = $idx === 0 ? 'active' : '';
                ?>
                    <button class="role-btn <?= $isActive ?>" data-role="<?= $rRole === 'Todos' ? 'all' : strtolower($rRole) ?>" style="padding: 0.5rem 1.2rem; border-radius: 20px; border: 1px solid var(--border-color); background: rgba(255,255,255,0.05); color: #fff; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.85rem;">
                        <?= $rRole ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="heroes-grid" id="heroes-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 1rem; padding: 0 1rem; max-width: 1200px; margin: 0 auto;">
            <?php foreach ($heroes as $hero): 
                $imgSrc = !empty($hero['image_url']) && file_exists(__DIR__ . '/' . $hero['image_url']) 
                          ? htmlspecialchars(url($hero['image_url'])) 
                          : htmlspecialchars(asset('img/logo.png')); // Fallback logo

                $roleColor = 'var(--primary-color)';
                $role = htmlspecialchars($hero['role']);
                if (stripos($role, 'Tanque') !== false) $roleColor = '#28a745';
                elseif (stripos($role, 'Asesino') !== false) $roleColor = '#dc3545';
                elseif (stripos($role, 'Mago') !== false) $roleColor = '#007bff';
                elseif (stripos($role, 'Apoyo') !== false) $roleColor = '#ffc107';
                elseif (stripos($role, 'Tirador') !== false) $roleColor = '#fd7e14';
                elseif (stripos($role, 'Combatiente') !== false) $roleColor = '#e83e8c';
            ?>
                <div class="hero-card" 
                     data-name="<?= htmlspecialchars(strtolower($hero['name'])) ?>" 
                     data-role="<?= htmlspecialchars(strtolower($hero['role'])) ?>"
                     data-played="<?= $hero['matches_played'] ?>"
                     data-winrate="<?= $hero['win_rate'] ?>"
                     data-kda="<?= $hero['kda'] ?>"
                     onclick='openHeroModal(<?= json_encode([
                         "name" => $hero["name"],
                         "role" => $hero["role"],
                         "image" => $imgSrc,
                         "roleColor" => $roleColor,
                         "played" => $hero["matches_played"],
                         "winrate" => $hero["win_rate"],
                         "kda" => $hero["kda"],
                         "kills" => $hero["kills"],
                         "deaths" => $hero["deaths"],
                         "assists" => $hero["assists"]
                     ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                     style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; cursor: pointer;">
                    
                    <div class="hero-img-wrapper" style="width: 100%; aspect-ratio: 1/1; position: relative; background: #111;">
                        <div style="position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,0) 40%, rgba(13,17,23,0.9) 100%); z-index: 1;"></div>
                        <img src="<?= $imgSrc; ?>" alt="<?= htmlspecialchars($hero['name']); ?>" loading="lazy" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s ease, filter 0.4s ease; filter: grayscale(20%) contrast(110%); <?php if($imgSrc === asset('img/logo.png')) echo 'padding: 20px; filter: grayscale(100%) opacity(0.5); object-fit: contain;'; ?>">
                        
                        <?php if($hero['matches_played'] > 0): ?>
                        <div style="position: absolute; top: 5px; right: 5px; z-index: 2; background: rgba(0,0,0,0.7); padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; border: 1px solid <?= $hero['win_rate'] >= 50 ? '#28a745' : '#dc3545' ?>; color: <?= $hero['win_rate'] >= 50 ? '#28a745' : '#dc3545' ?>;">
                            <?= $hero['win_rate'] ?>% WR
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="hero-info" style="position: absolute; bottom: 0; left: 0; right: 0; padding: 0.8rem 0.5rem; z-index: 2; text-align: center;">
                        <h4 style="margin: 0; font-family: 'Orbitron', sans-serif; font-size: 0.95rem; color: #fff; text-shadow: 0 2px 4px rgba(0,0,0,0.8); text-transform: uppercase;;"><?= htmlspecialchars($hero['name']); ?></h4>
                        <div style="font-size: 0.65rem; font-weight: 700; color: <?= $roleColor ?>; text-transform: uppercase; margin-top: 2px; letter-spacing: 0.5px; text-shadow: 0 1px 2px rgba(0,0,0,0.8);">
                            <?= $role ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div id="no-results" style="display: none; text-align: center; padding: 3rem; color: var(--text-muted);">
            <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
            <h3>No se encontraron héroes</h3>
            <p>Intenta con otro término de búsqueda o cambia los filtros.</p>
        </div>
    </div>
</div>

<!-- Hero Details Modal -->
<div id="heroModal" class="modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.8); backdrop-filter: blur(5px); align-items: center; justify-content: center;">
    <div class="modal-content" style="background: var(--bg-dark); border: 1px solid var(--neon-orchid); border-radius: 12px; max-width: 500px; width: 90%; position: relative; box-shadow: 0 0 30px rgba(110,65,255,0.3); overflow: hidden; animation: modalPop 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
        
        <span class="close-modal" style="position: absolute; top: 15px; right: 20px; color: #fff; font-size: 28px; font-weight: bold; cursor: pointer; z-index: 10; text-shadow: 0 2px 4px rgba(0,0,0,0.5); transition: color 0.2s;">&times;</span>
        
        <div id="modal-hero-cover" style="width: 100%; height: 250px; position: relative; background: #0a0c10; padding: 20px 20px 40px 20px; text-align: center;">
            <div style="position: absolute; inset: 0; background: linear-gradient(0deg, var(--bg-dark) 0%, rgba(13,17,23,0) 40%); z-index: 1;"></div>
            <img id="modal-hero-img" src="" style="max-width: 100%; max-height: 100%; object-fit: contain; object-position: center; position: relative; z-index: 0; filter: drop-shadow(0 0 10px rgba(110,65,255,0.3));">
        </div>
        
        <div style="padding: 0 2rem 2rem 2rem; position: relative; z-index: 2; margin-top: -30px; text-align: center;">
            <h2 id="modal-hero-name" style="font-family: 'Orbitron', sans-serif; font-size: 2.2rem; margin: 0; text-transform: uppercase; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">Nombre</h2>
            <div id="modal-hero-role" style="font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1.5rem; font-size: 0.9rem;">Rol</div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div style="background: rgba(255,255,255,0.05); padding: 1rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                    <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Partidas</div>
                    <div id="modal-hero-played" style="font-size: 1.8rem; font-family: 'Orbitron', sans-serif; font-weight: bold; color: var(--neon-orchid);">0</div>
                </div>
                <div style="background: rgba(255,255,255,0.05); padding: 1rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                    <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Win Rate</div>
                    <div id="modal-hero-winrate" style="font-size: 1.8rem; font-family: 'Orbitron', sans-serif; font-weight: bold; color: #28a745;">0%</div>
                </div>
                <div style="background: rgba(255,255,255,0.05); padding: 1rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); grid-column: 1 / -1;">
                    <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">KDA (Kills / Deaths / Assists)</div>
                    <div id="modal-hero-kda" style="font-size: 1.5rem; font-family: 'Orbitron', sans-serif; font-weight: bold; color: #fff; margin-top: 5px;">0</div>
                    <div id="modal-hero-kda-details" style="font-size: 0.85rem; color: var(--text-muted); margin-top: 5px;">0 / 0 / 0</div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<style>
.hero-card:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 8px 20px rgba(110, 65, 255, 0.5);
    border-color: var(--neon-orchid);
    z-index: 10;
}
.hero-card:hover .hero-img-wrapper img {
    transform: scale(1.15);
    filter: grayscale(0%) contrast(120%) drop-shadow(0 0 5px rgba(110,65,255,0.4)) !important;
}

.role-btn:hover { background: rgba(255,255,255,0.1) !important; }
.role-btn.active {
    background: var(--neon-orchid) !important;
    color: white !important;
    border-color: var(--neon-orchid) !important;
    box-shadow: 0 0 10px rgba(110,65,255,0.4);
}

#hero-search:focus, #hero-sort:focus {
    outline: none;
    border-color: var(--neon-orchid) !important;
    box-shadow: 0 0 8px rgba(110,65,255,0.3);
}

.close-modal:hover { color: var(--danger-color) !important; }

@keyframes modalPop {
    from { transform: scale(0.8); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('hero-search');
    const sortSelect = document.getElementById('hero-sort');
    const roleBtns = document.querySelectorAll('.role-btn');
    const grid = document.getElementById('heroes-grid');
    const cards = Array.from(grid.getElementsByClassName('hero-card'));
    const noResults = document.getElementById('no-results');
    const heroCount = document.getElementById('hero-count');
    
    let currentFilter = 'all';
    let currentSearch = '';

    function filterAndSortCards() {
        let visibleCount = 0;
        
        // Filter
        cards.forEach(card => {
            const name = card.dataset.name;
            const role = card.dataset.role;
            
            const matchesSearch = name.includes(currentSearch);
            const matchesRole = currentFilter === 'all' || role.includes(currentFilter);
            
            if (matchesSearch && matchesRole) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        // Sort
        const sortValue = sortSelect.value;
        const visibleCards = cards.filter(card => card.style.display !== 'none');
        
        visibleCards.sort((a, b) => {
            if (sortValue === 'name_asc') return a.dataset.name.localeCompare(b.dataset.name);
            if (sortValue === 'name_desc') return b.dataset.name.localeCompare(a.dataset.name);
            if (sortValue === 'played_desc') return parseFloat(b.dataset.played) - parseFloat(a.dataset.played);
            if (sortValue === 'winrate_desc') return parseFloat(b.dataset.winrate) - parseFloat(a.dataset.winrate);
            if (sortValue === 'kda_desc') return parseFloat(b.dataset.kda) - parseFloat(a.dataset.kda);
            return 0;
        });
        
        // Re-append to grid in sorted order
        visibleCards.forEach(card => grid.appendChild(card));
        
        // UI updates
        noResults.style.display = visibleCount === 0 ? 'block' : 'none';
        heroCount.textContent = visibleCount;
    }

    // Search Event
    searchInput.addEventListener('input', (e) => {
        currentSearch = e.target.value.toLowerCase().trim();
        filterAndSortCards();
    });

    // Sort Event
    sortSelect.addEventListener('change', filterAndSortCards);

    // Role Filter Events
    roleBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            roleBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.dataset.role;
            filterAndSortCards();
        });
    });
    
    // Initial Sort
    filterAndSortCards();
});

// Modal Logic
const modal = document.getElementById('heroModal');
const closeBtn = document.querySelector('.close-modal');

function openHeroModal(hero) {
    document.getElementById('modal-hero-name').textContent = hero.name;
    document.getElementById('modal-hero-role').textContent = hero.role;
    document.getElementById('modal-hero-role').style.color = hero.roleColor;
    document.getElementById('modal-hero-img').src = hero.image;
    
    document.getElementById('modal-hero-played').textContent = hero.played;
    
    const wrEl = document.getElementById('modal-hero-winrate');
    wrEl.textContent = hero.played > 0 ? hero.winrate + '%' : '0%';
    wrEl.style.color = hero.winrate >= 50 ? '#28a745' : (hero.played > 0 ? '#dc3545' : '--text-muted');
    
    document.getElementById('modal-hero-kda').textContent = hero.kda;
    document.getElementById('modal-hero-kda-details').textContent = `${hero.kills} K / ${hero.deaths} D / ${hero.assists} A`;
    
    modal.style.display = 'flex';
}

closeBtn.addEventListener('click', () => modal.style.display = 'none');
window.addEventListener('click', (e) => {
    if (e.target === modal) modal.style.display = 'none';
});
</script>

<?php require_once 'includes/footer.php'; ?>
