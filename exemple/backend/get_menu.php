<?php
require_once __DIR__ . '/db.php';

// 1. Récupérer toutes les catégories
$stmt = $pdo->query("SELECT * FROM categories");
$categories = $stmt->fetchAll();
$category_map = [];
foreach ($categories as $cat) {
    $category_map[$cat['id']] = $cat['nom'];
}

// 2. Récupérer toutes les options globales pour les garnitures (pour $garnitures_global)
$stmt = $pdo->query("SELECT nom FROM options WHERE type = 'garniture'");
$garnitures_global = $stmt->fetchAll(PDO::FETCH_COLUMN);

// 3. Récupérer tous les plats actifs triés par popularité (orders_count DESC)
$stmt = $pdo->query("SELECT * FROM dishes WHERE active = 1 ORDER BY orders_count DESC, nom ASC");
$dishes = $stmt->fetchAll();

$products_data = [];

foreach ($dishes as $dish) {
    $dish_id = $dish['id'];
    $category_name = $category_map[$dish['category_id']] ?? '';

    // Récupérer les variations pour ce plat
    $stmt = $pdo->prepare("SELECT name, price, group_name FROM dish_variations WHERE dish_id = ?");
    $stmt->execute([$dish_id]);
    $variations = $stmt->fetchAll();

    $attributes = [];

    // Gérer les variations manuelles (comme dans le template index.php)
    if (!empty($variations)) {
        $attributes['manual_variations'] = [
            'title' => $variations[0]['group_name'] ?? 'Variations',
            'items' => array_map(function($v) {
                return ['name' => $v['name'], 'price' => (float)$v['price']];
            }, $variations)
        ];
    }

    // Récupérer les options liées à la catégorie
    $stmt = $pdo->prepare("
        SELECT o.nom, o.prix, o.type 
        FROM options o 
        JOIN category_options co ON o.id = co.option_id 
        WHERE co.category_id = ?
    ");
    $stmt->execute([$dish['category_id']]);
    $cat_options = $stmt->fetchAll();

    // Récupérer les options liées directement au plat
    $stmt = $pdo->prepare("
        SELECT o.nom, o.prix, o.type 
        FROM options o 
        JOIN dish_options do ON o.id = do.option_id 
        WHERE do.dish_id = ?
    ");
    $stmt->execute([$dish_id]);
    $dish_specific_options = $stmt->fetchAll();

    $all_options = array_merge($cat_options, $dish_specific_options);

    foreach ($all_options as $opt) {
        $type = $opt['type'];
        if ($type === 'garniture') {
            if (!isset($attributes['garnitures'])) $attributes['garnitures'] = [];
            $attributes['garnitures'][] = $opt['nom'];
        } elseif ($type === 'supplement') {
            if (!isset($attributes['supplements'])) $attributes['supplements'] = [];
            $attributes['supplements'][] = ['name' => $opt['nom'], 'price' => (float)$opt['prix']];
        } elseif ($type === 'parfum') {
            if (!isset($attributes['parfums'])) $attributes['parfums'] = [];
            $attributes['parfums'][] = $opt['nom'];
        } elseif ($type === 'custom_radio') {
            // Group custom_radio variants by "Choix de la sauce" or similar context
            // Since all current custom_radios are sauces, we group them under one custom_option
            if (!isset($attributes['custom_options'])) {
                $attributes['custom_options'] = [];
            }
            
            // Look if we already created a "Sauce" custom option block
            $sauce_idx = -1;
            foreach ($attributes['custom_options'] as $idx => $co) {
                if ($co['title'] === 'Sauce au choix') {
                    $sauce_idx = $idx;
                    break;
                }
            }
            
            if ($sauce_idx === -1) {
                $attributes['custom_options'][] = [
                    'title' => 'Sauce au choix',
                    'type' => 'radio',
                    'required' => true,
                    'items' => []
                ];
                $sauce_idx = count($attributes['custom_options']) - 1;
            }
            
            $attributes['custom_options'][$sauce_idx]['items'][] = [
                'name' => $opt['nom'],
                'price' => (float)$opt['prix']
            ];
        }
    }

    $products_data[] = [
        'id' => (int)$dish_id,
        'name' => $dish['nom'],
        'description' => $dish['description'],
        'price' => (float)$dish['prix'],
        'category' => $category_name,
        'menu' => $dish['menu_type'],
        'image' => $dish['image'],
        'attributes' => $attributes
    ];
}
?>
