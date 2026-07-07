<?php
require_once __DIR__ . "/config/app.php";
require_once __DIR__ . "/config/db.php";
$pdo = Database::getConnection();

// Get all used images from DB
$usedImages = [];
$products = $pdo->query("SELECT image FROM products WHERE image IS NOT NULL AND image != ''")->fetchAll(PDO::FETCH_COLUMN);
foreach ($products as $img) $usedImages[basename($img)] = 1;

$variants = $pdo->query("SELECT image_url FROM product_variants WHERE image_url IS NOT NULL AND image_url != ''")->fetchAll(PDO::FETCH_COLUMN);
foreach ($variants as $img) $usedImages[basename($img)] = 1;

// Scan assets/images
$dir = __DIR__ . '/assets/images';
if (is_dir($dir)) {
    $files = scandir($dir);
    $count = 0;
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === '.gitkeep') continue;
        if (str_ends_with($file, '.bak')) continue;
        
        if (!isset($usedImages[$file])) {
            rename("$dir/$file", "$dir/$file.bak");
            echo "Renamed unused image: $file to $file.bak\n";
            $count++;
        }
    }
    echo "Done. Total unused images renamed: $count\n";
} else {
    echo "Directory not found.\n";
}
