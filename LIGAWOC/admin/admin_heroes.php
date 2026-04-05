<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/TeamController.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$db = Database::getInstance();
$message = '';

// ── Handle DELETE hero ──
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_hero_id'])) {
    $del_id = (int)$_POST['delete_hero_id'];
    $hero = $db->fetch("SELECT name, image_url FROM ml_heroes WHERE id = ?", [$del_id]);
    if ($hero) {
        // Delete image file if exists
        if (!empty($hero['image_url']) && file_exists('../' . $hero['image_url'])) {
            unlink('../' . $hero['image_url']);
        }
        $db->query("DELETE FROM ml_heroes WHERE id = ?", [$del_id]);
        $message = "<div class='alert alert-success'>Héroe <strong>{$hero['name']}</strong> eliminado correctamente.</div>";
    }
}

// ── Handle ADD new hero ──
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['new_hero_name'])) {
    $name = trim($_POST['new_hero_name']);
    $role = trim($_POST['new_hero_role']);
    
    if (empty($name) || empty($role)) {
        $message = "<div class='alert alert-danger'>Error: Nombre y rol son obligatorios.</div>";
    } else {
        // Check if hero already exists
        $exists = $db->fetch("SELECT id FROM ml_heroes WHERE name = ?", [$name]);
        if ($exists) {
            $message = "<div class='alert alert-danger'>Error: El héroe <strong>{$name}</strong> ya existe.</div>";
        } else {
            $image_path = '';
            
            // Handle image upload if provided
            if (isset($_FILES['new_hero_image']) && $_FILES['new_hero_image']['error'] == 0) {
                $allowed = array("jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png", "webp" => "image/webp");
                $filename = $_FILES['new_hero_image']['name'];
                $filesize = $_FILES['new_hero_image']['size'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (!array_key_exists($ext, $allowed)) {
                    $message = "<div class='alert alert-danger'>Error: Formato de imagen no válido.</div>";
                } elseif ($filesize > 5 * 1024 * 1024) {
                    $message = "<div class='alert alert-danger'>Error: El archivo es demasiado grande (máx 5MB).</div>";
                } else {
                    $clean_name = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '_', $name));
                    $new_filename = $clean_name . '.' . $ext;
                    
                    $roles = explode('/', $role);
                    $primary_role = trim($roles[0]);
                    $safe_role = str_replace(['/', '\\', '-'], '', $primary_role);
                    $safe_role = trim($safe_role);
                    
                    $upload_physical_dir = '../assets/heroes_img/' . $safe_role . '/';
                    $db_upload_path = 'assets/heroes_img/' . $safe_role . '/';
                    
                    if (!is_dir($upload_physical_dir)) {
                        mkdir($upload_physical_dir, 0777, true);
                    }
                    
                    if (move_uploaded_file($_FILES['new_hero_image']['tmp_name'], $upload_physical_dir . $new_filename)) {
                        $image_path = $db_upload_path . $new_filename;
                    }
                }
            }
            
            if (empty($message)) {
                $db->query("INSERT INTO ml_heroes (name, role, image_url) VALUES (?, ?, ?)", [$name, $role, $image_path]);
                $message = "<div class='alert alert-success'>Héroe <strong>{$name}</strong> agregado correctamente.</div>";
            }
        }
    }
}

// ── Handle UPDATE image ──
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['hero_id']) && !isset($_POST['new_hero_name']) && !isset($_POST['delete_hero_id'])) {
    $hero_id = (int)$_POST['hero_id'];
    
    if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] == 0) {
        $allowed = array("jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png", "webp" => "image/webp");
        $filename = $_FILES['hero_image']['name'];
        $filetype = $_FILES['hero_image']['type'];
        $filesize = $_FILES['hero_image']['size'];
    
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if(!array_key_exists($ext, $allowed)) {
            $message = "<div class='alert alert-danger'>Error: Por favor seleccione un formato de imagen válido.</div>";
        } else {
            if($filesize > 5 * 1024 * 1024) {
                 $message = "<div class='alert alert-danger'>Error: El archivo es demasiado grande.</div>";
            } else {
                $hero = $db->fetch("SELECT name, role FROM ml_heroes WHERE id = ?", [$hero_id]);
                if ($hero) {
                    $clean_name = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '_', $hero['name']));
                    $new_filename = $clean_name . '.' . $ext;
                    
                    $roles = explode('/', $hero['role']);
                    $primary_role = trim($roles[0]);
                    
                    $safe_role = str_replace(['/', '\\', '-'], '', $primary_role);
                    $safe_role = trim($safe_role);
                    
                    $upload_physical_dir = '../assets/heroes_img/' . $safe_role . '/';
                    $db_upload_path = 'assets/heroes_img/' . $safe_role . '/';
                    
                    if (!is_dir($upload_physical_dir)) {
                        mkdir($upload_physical_dir, 0777, true);
                    }
                    
                    if(move_uploaded_file($_FILES['hero_image']['tmp_name'], $upload_physical_dir . $new_filename)) {
                        $image_path = $db_upload_path . $new_filename;
                        $db->update("UPDATE ml_heroes SET image_url = ? WHERE id = ?", [$image_path, $hero_id]);
                        $message = "<div class='alert alert-success'>La imagen de {$hero['name']} se ha actualizado correctamente.</div>";
                    } else {
                        $message = "<div class='alert alert-danger'>Error al subir el archivo.</div>";
                    }
                }
            }
        }
    }
}

$heroes = $db->fetchAll("SELECT * FROM ml_heroes ORDER BY name ASC");
$roles_list = ['Tanque', 'Combatiente', 'Asesino', 'Mago', 'Tirador', 'Apoyo'];
?>

<div class="app-wrapper">
    <div class="main-content">
        <div class="page-header" style="text-align: center; margin-bottom: 2rem;">
            <h2 class="page-title" style="font-family: 'Orbitron', sans-serif; text-transform: uppercase;">📸 Panel de Héroes</h2>
            <p class="page-subtitle">Sube y administra las fotos de los campeones</p>
        </div>
    <?php echo $message; ?>
    
    <!-- ═══════════════════════════════════════════
         ADD NEW HERO FORM
         ═══════════════════════════════════════════ -->
    <div style="max-width: 1000px; margin: 0 auto 1.5rem;">
        <div class="card" style="border: 1px solid var(--border); background: var(--bg-card);">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px; padding-bottom: 14px; border-bottom: 1px solid var(--border);">
                <span style="font-size: 1.2rem;">➕</span>
                <h3 style="font-family: 'Montserrat', sans-serif; font-size: .92rem; font-weight: 800; color: var(--text-1); text-transform: uppercase; letter-spacing: .01em;">Agregar Nuevo Héroe</h3>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div style="display: grid; grid-template-columns: 1fr 1fr auto auto; gap: 12px; align-items: end;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: .72rem; color: var(--text-3); letter-spacing: .06em; text-transform: uppercase;">Nombre del Héroe *</label>
                        <input type="text" name="new_hero_name" placeholder="Ej: Lunox" required
                            style="width: 100%; padding: 10px 14px; background: var(--bg-input); border: 1px solid var(--border); border-radius: 6px; color: var(--text-1); font-size: .88rem; outline: none;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: .72rem; color: var(--text-3); letter-spacing: .06em; text-transform: uppercase;">Rol *</label>
                        <select name="new_hero_role" required
                            style="width: 100%; padding: 10px 14px; background: var(--bg-input); border: 1px solid var(--border); border-radius: 6px; color: var(--text-1); font-size: .88rem; outline: none; cursor: pointer;">
                            <option value="">Seleccionar rol...</option>
                            <?php foreach($roles_list as $r): ?>
                                <option value="<?= $r ?>"><?= $r ?></option>
                            <?php endforeach; ?>
                            <option value="Tanque/Apoyo">Tanque/Apoyo</option>
                            <option value="Combatiente/Asesino">Combatiente/Asesino</option>
                            <option value="Combatiente/Tanque">Combatiente/Tanque</option>
                            <option value="Combatiente/Mago">Combatiente/Mago</option>
                            <option value="Mago/Apoyo">Mago/Apoyo</option>
                            <option value="Mago/Tanque">Mago/Tanque</option>
                            <option value="Mago/Asesino">Mago/Asesino</option>
                            <option value="Asesino/Mago">Asesino/Mago</option>
                            <option value="Asesino/Combatiente">Asesino/Combatiente</option>
                            <option value="Asesino/Tirador">Asesino/Tirador</option>
                            <option value="Tirador/Asesino">Tirador/Asesino</option>
                            <option value="Tirador/Mago">Tirador/Mago</option>
                            <option value="Apoyo/Mago">Apoyo/Mago</option>
                            <option value="Apoyo/Tanque">Apoyo/Tanque</option>
                            <option value="Apoyo/Asesino">Apoyo/Asesino</option>
                            <option value="Tanque/Combatiente">Tanque/Combatiente</option>
                            <option value="Tanque/Tirador">Tanque/Tirador</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: .72rem; color: var(--text-3); letter-spacing: .06em; text-transform: uppercase;">Imagen (opcional)</label>
                        <input type="file" name="new_hero_image" accept="image/*"
                            style="background: rgba(0,0,0,0.2); border: 1px solid var(--border); color: #fff; padding: 0.4rem; border-radius: 4px; font-size: 0.85rem; max-width: 200px;">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary" style="padding: 10px 20px; font-size: 0.82rem; white-space: nowrap;">
                            ➕ Agregar Héroe
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════
         HEROES TABLE
         ═══════════════════════════════════════════ -->
    <div style="max-width: 1000px; margin: 0 auto;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
            <span style="color: var(--text-3); font-size: .85rem;">Total: <strong style="color: var(--accent-bright);"><?= count($heroes) ?></strong> héroes</span>
            <input type="text" id="heroAdminSearch" placeholder="🔍 Buscar héroe..." 
                style="padding: 8px 14px; background: var(--bg-input); border: 1px solid var(--border); border-radius: 6px; color: var(--text-1); font-size: .85rem; width: 220px; outline: none;">
        </div>
        <div class="card p-0" style="overflow: hidden; border: 1px solid var(--border); background: var(--bg-card);">
            <table class="table" id="heroesTable" style="margin: 0; color: #fff; width: 100%; border-collapse: collapse;">
                <thead style="background: rgba(110,65,255,0.1); border-bottom: 1px solid var(--border); font-family: 'Montserrat', sans-serif;">
                    <tr>
                        <th style="padding: 1rem; text-align: left; width: 22%;">Héroe</th>
                        <th style="padding: 1rem; text-align: left; width: 20%;">Rol</th>
                        <th style="padding: 1rem; text-align: center; width: 15%;">Foto</th>
                        <th style="padding: 1rem; text-align: center; width: 28%;">Cambiar Imagen</th>
                        <th style="padding: 1rem; text-align: center; width: 15%;">Eliminar</th>
                    </tr>
                </thead>
            <tbody>
                <?php foreach ($heroes as $hero): ?>
                    <tr class="hero-row" data-name="<?= strtolower(htmlspecialchars($hero['name'])) ?>" style="border-bottom: 1px solid var(--border); transition: background 0.2s ease;">
                        <td style="padding: 0.8rem 1rem; font-weight: 600; color: var(--accent-bright);"><?= htmlspecialchars($hero['name']); ?></td>
                        <td style="padding: 0.8rem 1rem; color: var(--text-muted); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px;"><?= htmlspecialchars($hero['role']); ?></td>
                        <td style="padding: 0.8rem 1rem; text-align: center;">
                            <?php if (!empty($hero['image_url'])): ?>
                                <img src="<?= url($hero['image_url']) ?>" alt="Foto" style="width: 45px; height: 45px; object-fit: cover; border-radius: 8px; border: 1px solid var(--accent); box-shadow: 0 0 10px rgba(110,65,255,0.2);">
                            <?php else: ?>
                                <span style="display: inline-block; padding: 3px 8px; background: rgba(220,53,69,0.2); color: #ff6b6b; border-radius: 4px; font-size: 0.75rem; border: 1px solid rgba(220,53,69,0.3);">Sin foto</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.6rem 1rem; text-align: center;">
                            <form action="" method="POST" enctype="multipart/form-data" style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <input type="hidden" name="hero_id" value="<?= $hero['id']; ?>">
                                <input type="file" name="hero_image" style="background: rgba(0,0,0,0.2); border: 1px solid var(--border); color: #fff; padding: 0.3rem; border-radius: 4px; font-size: 0.8rem; max-width: 160px;" required accept="image/*">
                                <button type="submit" class="btn btn-primary btn-sm" style="padding: 0.35rem 0.7rem; font-size: 0.78rem;">Subir</button>
                            </form>
                        </td>
                        <td style="padding: 0.6rem 1rem; text-align: center;">
                            <form action="" method="POST" onsubmit="return confirm('¿Eliminar a <?= htmlspecialchars($hero['name']) ?>? Esta acción no se puede deshacer.');">
                                <input type="hidden" name="delete_hero_id" value="<?= $hero['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" style="padding: 0.35rem 0.7rem; font-size: 0.78rem;">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
tr:hover { background: rgba(255,255,255,0.03); }
input[type=file]::file-selector-button {
    background: var(--accent);
    border: none;
    color: white;
    padding: 0.2rem 0.8rem;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
}
input[type=file]::file-selector-button:hover {
    background: var(--accent-dim);
}
select option {
    background: var(--bg-panel);
    color: var(--text-1);
}
@media (max-width: 768px) {
    #heroAdminSearch { width: 150px !important; }
    table th:nth-child(5), table td:nth-child(5) { display: none; }
}
</style>

<script>
// Search filter for heroes table
document.getElementById('heroAdminSearch')?.addEventListener('input', function() {
    const term = this.value.toLowerCase().trim();
    document.querySelectorAll('.hero-row').forEach(row => {
        const name = row.dataset.name;
        row.style.display = term === '' || name.includes(term) ? '' : 'none';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
