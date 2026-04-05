<?php
$db = new PDO('mysql:host=localhost;dbname=ligawoc', 'root', '');

// Get all heroes
$heroes = $db->query("SELECT id, name, role FROM ml_heroes")->fetchAll(PDO::FETCH_ASSOC);

$roleMap = [
    'Tirador' => 'Tirador',
    'Mago' => 'MAGO',
    'Tanque' => 'Tanque',
    'Asesino' => 'Asesino',
    'Combatiente' => 'Combatiente',
    'Apoyo' => 'APOYO',
];

$basePath = __DIR__ . '/assets/heroes_img';

foreach ($heroes as $hero) {
    // Extract primary role
    $primaryRole = explode('/', $hero['role'])[0];
    $folder = $roleMap[$primaryRole] ?? $roleMap['Mago'];
    
    $heroPath = $basePath . '/' . $folder;
    if (!is_dir($heroPath)) continue;
    
    // Try different extensions
    $heroName = strtolower($hero['name']);
    $extensions = ['.png', '.webp', '.jpg', '.jpeg'];
    $found = null;
    
    foreach ($extensions as $ext) {
        $file = $heroPath . '/' . $heroName . $ext;
        if (file_exists($file)) {
            $found = 'assets/heroes_img/' . $folder . '/' . $heroName . $ext;
            break;
        }
    }
    
    if ($found) {
        $stmt = $db->prepare("UPDATE ml_heroes SET image_url = ? WHERE id = ?");
        $stmt->execute([$found, $hero['id']]);
        echo "{$hero['name']} -> {$found}\n";
    } else {
        echo "{$hero['name']} NOT FOUND\n";
    }
}
echo "\nDone!\n";
