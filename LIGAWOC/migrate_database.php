<?php
/**
 * Liga WOC - Database Migration Tool
 * 
 * INSTRUCCIONES:
 * 1. Sube este archivo a tu hosting (raíz del sitio o carpeta privada)
 * 2. Abre en el navegador: https://tudominio.com/migrate_database.php?key=LIGAWOC_MIGRATE_2026
 * 3. Sigue los pasos en pantalla
 * 4. ELIMINA este archivo después de terminar
 * 
 * SEGURIDAD: Solo accesible con la clave secreta en la URL
 */

// ============================================================
// CONFIGURACIÓN - AJUSTA ESTOS VALORES
// ============================================================
define('MIGRATE_KEY', 'LIGAWOC_MIGRATE_2026'); // Cambia esto a algo único

// Base de datos ORIGEN (la actual con datos)
define('OLD_DB_HOST', 'localhost');
define('OLD_DB_PORT', 3306);
define('OLD_DB_USER', 'tu_usuario_actual');   // ← CAMBIA ESTO
define('OLD_DB_PASS', 'tu_password_actual');  // ← CAMBIA ESTO
define('OLD_DB_NAME', 'ligawoc_vieja');       // ← CAMBIA ESTO (nombre de tu BD actual)

// Base de datos DESTINO (la nueva con el nuevo schema)
define('NEW_DB_HOST', 'localhost');
define('NEW_DB_PORT', 3306);
define('NEW_DB_USER', 'tu_usuario_nuevo');    // ← CAMBIA ESTO
define('NEW_DB_PASS', 'tu_password_nuevo');   // ← CAMBIA ESTO
define('NEW_DB_NAME', 'ligawoc_nueva');       // ← CAMBIA ESTO (nombre de tu nueva BD)
// ============================================================

// Verificar clave de seguridad
if (!isset($_GET['key']) || $_GET['key'] !== MIGRATE_KEY) {
    http_response_code(403);
    die('Acceso denegado.');
}

set_time_limit(300);
ini_set('memory_limit', '256M');

$step = isset($_GET['step']) ? (int)$_GET['step'] : 0;
$baseUrl = '?key=' . MIGRATE_KEY;

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Liga WOC - Migración de Base de Datos</title>
<style>
body { font-family: monospace; background: #1a1a2e; color: #e0e0e0; padding: 20px; max-width: 900px; margin: 0 auto; }
h1 { color: #7c3aed; }
h2 { color: #9d4edd; border-bottom: 1px solid #333; padding-bottom: 8px; }
.box { background: #16213e; border: 1px solid #333; border-radius: 8px; padding: 16px; margin: 12px 0; }
.ok { color: #4ade80; } .err { color: #f87171; } .warn { color: #fbbf24; } .info { color: #60a5fa; }
.btn { display: inline-block; background: #7c3aed; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; margin: 8px 4px; font-size: 14px; }
.btn:hover { background: #6d28d9; }
.btn-danger { background: #dc2626; }
.btn-danger:hover { background: #b91c1c; }
pre { background: #0d1117; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 12px; }
table { width: 100%; border-collapse: collapse; }
td, th { padding: 6px 10px; border: 1px solid #333; text-align: left; font-size: 13px; }
th { background: #1e1b4b; color: #a78bfa; }
</style>
</head>
<body>
<h1>⚙️ Liga WOC - Migración de Base de Datos</h1>

<?php

// ============================================================
// FUNCIONES AUXILIARES
// ============================================================

function connectDB($host, $port, $user, $pass, $db) {
    try {
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        return $pdo;
    } catch (PDOException $e) {
        return null;
    }
}

function getRowCount($pdo, $table) {
    try {
        return $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    } catch (Exception $e) {
        return 'N/A';
    }
}

function tableExists($pdo, $table, $db) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
    $stmt->execute([$db, $table]);
    return (bool)$stmt->fetchColumn();
}

// Tablas en orden de migración (respetando foreign keys)
$tables = [
    'users',
    'email_change_codes',
    'news_categories',
    'news',
    'seasons',
    'tournaments',
    'teams',
    'team_members',
    'team_join_requests',
    'tournament_teams',
    'tournament_matches',
    'match_results',
    'match_player_stats',
    'team_rankings',
    'player_rankings',
    'notifications',
    'streams',
    'prizes',
    'platform_config',
    'audit_logs',
    'user_coins',
    'coin_packages',
    'coin_purchases',
    'item_categories',
    'shop_items',
    'user_inventory',
    'designer_applications',
    'paypal_orders',
    'designer_payouts',
];

// Columnas a excluir al migrar (columnas en BD vieja que no existen en nueva)
$excludeColumns = [
    'users' => ['main_hero', 'current_rank', 'whatsapp', 'phone_brand', 'discord'],
];

// ============================================================
// PASO 0: DIAGNÓSTICO
// ============================================================
if ($step === 0) {
?>
<div class="box">
<h2>Paso 1: Diagnóstico de Conexiones</h2>
<?php
    $oldPdo = connectDB(OLD_DB_HOST, OLD_DB_PORT, OLD_DB_USER, OLD_DB_PASS, OLD_DB_NAME);
    $newPdo = connectDB(NEW_DB_HOST, NEW_DB_PORT, NEW_DB_USER, NEW_DB_PASS, NEW_DB_NAME);
    
    echo "<p><strong>Base de datos ORIGEN</strong> (<code>" . htmlspecialchars(OLD_DB_NAME) . "</code>): ";
    if ($oldPdo) {
        echo "<span class='ok'>✓ Conectado</span></p>";
        echo "<table><tr><th>Tabla</th><th>Registros</th></tr>";
        foreach ($tables as $t) {
            if (tableExists($oldPdo, $t, OLD_DB_NAME)) {
                $count = getRowCount($oldPdo, $t);
                echo "<tr><td>$t</td><td>$count</td></tr>";
            } else {
                echo "<tr><td>$t</td><td><span class='warn'>No existe</span></td></tr>";
            }
        }
        echo "</table>";
    } else {
        echo "<span class='err'>✗ Error de conexión - Verifica credenciales arriba</span></p>";
    }
    
    echo "<br><p><strong>Base de datos DESTINO</strong> (<code>" . htmlspecialchars(NEW_DB_NAME) . "</code>): ";
    if ($newPdo) {
        echo "<span class='ok'>✓ Conectado</span></p>";
        echo "<p class='info'>ℹ Tablas encontradas en destino:</p><table><tr><th>Tabla</th><th>Registros actuales</th></tr>";
        foreach ($tables as $t) {
            if (tableExists($newPdo, $t, NEW_DB_NAME)) {
                $count = getRowCount($newPdo, $t);
                echo "<tr><td>$t</td><td>$count</td></tr>";
            } else {
                echo "<tr><td>$t</td><td><span class='warn'>No existe aún</span></td></tr>";
            }
        }
        echo "</table>";
    } else {
        echo "<span class='err'>✗ Error de conexión - Verifica credenciales arriba</span></p>";
    }
    
    if ($oldPdo && $newPdo) {
        echo "<br><p class='ok'>✓ Ambas conexiones exitosas. Puedes continuar.</p>";
        echo "<a href='{$baseUrl}&step=1' class='btn'>▶ Iniciar Migración</a>";
    } else {
        echo "<br><p class='err'>✗ Corrige las credenciales en este archivo antes de continuar.</p>";
    }
?>
</div>
<?php
}

// ============================================================
// PASO 1: EJECUTAR MIGRACIÓN
// ============================================================
elseif ($step === 1) {
    $oldPdo = connectDB(OLD_DB_HOST, OLD_DB_PORT, OLD_DB_USER, OLD_DB_PASS, OLD_DB_NAME);
    $newPdo = connectDB(NEW_DB_HOST, NEW_DB_PORT, NEW_DB_USER, NEW_DB_PASS, NEW_DB_NAME);
    
    if (!$oldPdo || !$newPdo) {
        echo "<p class='err'>✗ Error de conexión. Vuelve al paso anterior.</p>";
        echo "<a href='{$baseUrl}&step=0' class='btn'>← Volver</a>";
    } else {
?>
<div class="box">
<h2>Paso 2: Migración en Progreso</h2>
<?php
        $errors = [];
        $success = [];
        
        // Deshabilitar foreign key checks temporalmente
        $newPdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        foreach ($tables as $tableName) {
            echo "<p class='info'>⟳ Migrando: <strong>$tableName</strong>... </p>";
            flush();
            
            // Verificar si tabla existe en origen
            if (!tableExists($oldPdo, $tableName, OLD_DB_NAME)) {
                echo "<span class='warn'>⚠ Tabla no existe en origen - omitida</span><br>";
                continue;
            }
            
            // Verificar si tabla existe en destino
            if (!tableExists($newPdo, $tableName, NEW_DB_NAME)) {
                echo "<span class='warn'>⚠ Tabla no existe en destino - omitida (¿importaste prueba.sql?)</span><br>";
                $errors[] = "$tableName: No existe en base de datos destino";
                continue;
            }
            
            try {
                // Obtener columnas del DESTINO
                $destCols = $newPdo->query("SHOW COLUMNS FROM `$tableName`")->fetchAll(PDO::FETCH_COLUMN);
                
                // Obtener columnas del ORIGEN disponibles
                $srcCols = $oldPdo->query("SHOW COLUMNS FROM `$tableName`")->fetchAll(PDO::FETCH_COLUMN);
                
                // Columnas comunes (sin las excluidas)
                $exclude = $excludeColumns[$tableName] ?? [];
                $cols = array_intersect($destCols, $srcCols);
                $cols = array_diff($cols, $exclude);
                $cols = array_values($cols);
                
                if (empty($cols)) {
                    echo "<span class='warn'>⚠ No hay columnas coincidentes</span><br>";
                    continue;
                }
                
                $colList = implode(', ', array_map(fn($c) => "`$c`", $cols));
                
                // Limpiar tabla destino primero
                $newPdo->exec("TRUNCATE TABLE `$tableName`");
                
                // Migrar en lotes de 1000
                $offset = 0;
                $batchSize = 1000;
                $totalMigrated = 0;
                
                do {
                    $rows = $oldPdo->query(
                        "SELECT $colList FROM `$tableName` LIMIT $batchSize OFFSET $offset"
                    )->fetchAll();
                    
                    if (empty($rows)) break;
                    
                    // Construir INSERT batch
                    $placeholders = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
                    $allPlaceholders = implode(',', array_fill(0, count($rows), $placeholders));
                    $sql = "INSERT IGNORE INTO `$tableName` ($colList) VALUES $allPlaceholders";
                    
                    $flatValues = [];
                    foreach ($rows as $row) {
                        foreach ($cols as $col) {
                            $flatValues[] = $row[$col] ?? null;
                        }
                    }
                    
                    $stmt = $newPdo->prepare($sql);
                    $stmt->execute($flatValues);
                    
                    $totalMigrated += count($rows);
                    $offset += $batchSize;
                    
                } while (count($rows) === $batchSize);
                
                // Actualizar AUTO_INCREMENT
                $maxId = $oldPdo->query("SELECT MAX(id) FROM `$tableName`")->fetchColumn();
                if ($maxId) {
                    $newPdo->exec("ALTER TABLE `$tableName` AUTO_INCREMENT = " . ($maxId + 1));
                }
                
                echo "<span class='ok'>✓ $totalMigrated registros migrados";
                if (!empty($exclude)) {
                    echo " (columnas omitidas: " . implode(', ', $exclude) . ")";
                }
                echo "</span><br>";
                $success[] = "$tableName: $totalMigrated registros";
                
            } catch (Exception $e) {
                $msg = htmlspecialchars($e->getMessage());
                echo "<span class='err'>✗ Error: $msg</span><br>";
                $errors[] = "$tableName: $msg";
            }
            
            ob_flush();
            flush();
        }
        
        // Re-habilitar foreign key checks
        $newPdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        echo "<br><h2>Resumen</h2>";
        echo "<p class='ok'>✓ Tablas migradas exitosamente: " . count($success) . "</p>";
        if (!empty($errors)) {
            echo "<p class='err'>✗ Errores: " . count($errors) . "</p><ul>";
            foreach ($errors as $e) echo "<li class='err'>" . htmlspecialchars($e) . "</li>";
            echo "</ul>";
        }
        
        echo "<br><a href='{$baseUrl}&step=2' class='btn'>▶ Ver Verificación Final</a>";
?>
</div>
<?php
    }
}

// ============================================================
// PASO 2: VERIFICACIÓN
// ============================================================
elseif ($step === 2) {
    $oldPdo = connectDB(OLD_DB_HOST, OLD_DB_PORT, OLD_DB_USER, OLD_DB_PASS, OLD_DB_NAME);
    $newPdo = connectDB(NEW_DB_HOST, NEW_DB_PORT, NEW_DB_USER, NEW_DB_PASS, NEW_DB_NAME);
?>
<div class="box">
<h2>Paso 3: Verificación Final</h2>
<table>
<tr><th>Tabla</th><th>Registros en ORIGEN</th><th>Registros en DESTINO</th><th>Estado</th></tr>
<?php
    foreach ($tables as $t) {
        $oldCount = ($oldPdo && tableExists($oldPdo, $t, OLD_DB_NAME)) ? getRowCount($oldPdo, $t) : 'N/A';
        $newCount = ($newPdo && tableExists($newPdo, $t, NEW_DB_NAME)) ? getRowCount($newPdo, $t) : 'N/A';
        
        if ($oldCount === 'N/A' && $newCount === 'N/A') continue;
        
        $status = '';
        if ($oldCount === 'N/A') $status = "<span class='warn'>⚠ No en origen</span>";
        elseif ($newCount === 'N/A') $status = "<span class='err'>✗ No en destino</span>";
        elseif ($oldCount == $newCount) $status = "<span class='ok'>✓ OK</span>";
        else $status = "<span class='warn'>⚠ Diferencia ($oldCount → $newCount)</span>";
        
        echo "<tr><td>$t</td><td>$oldCount</td><td>$newCount</td><td>$status</td></tr>";
    }
?>
</table>
<br>
<p class='warn'>⚠ <strong>IMPORTANTE:</strong> Después de verificar la migración, elimina este archivo del servidor.</p>
<a href="?key=<?= MIGRATE_KEY ?>&step=3" class='btn btn-danger'>🗑 Eliminar este script</a>
</div>
<?php
}

// ============================================================
// PASO 3: AUTO-ELIMINACIÓN
// ============================================================
elseif ($step === 3) {
    $selfDelete = @unlink(__FILE__);
    if ($selfDelete) {
        echo "<div class='box'><p class='ok'>✓ Script eliminado exitosamente. Migración completa.</p></div>";
    } else {
        echo "<div class='box'><p class='err'>✗ No se pudo eliminar automáticamente. Elimínalo manualmente desde cPanel File Manager.</p></div>";
    }
}

?>

<hr style="border-color:#333; margin-top:30px">
<p style="font-size:11px; color:#666;">Liga WOC Migration Tool — Solo accesible con clave. Eliminar tras uso.</p>
</body>
</html>
