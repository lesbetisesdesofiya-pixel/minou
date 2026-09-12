<?php
require_once 'backend/db.php';

$csvFile = 'menu.csv';

if (!file_exists($csvFile)) {
    die("Le fichier $csvFile est introuvable.");
}

// 1. Définition des données Globales (Hardcoded comme dans le template WP)
$supplements_500 = ['Oeuf', 'Crême fraîche', 'Mais', 'Jambon', 'Poulet', 'Poulet Barbecue', 'Boeuf haché', 'Fromage'];
$supplements_1000 = ['Crevettes', 'Thon', 'Chorizo', 'Merguez', 'Fromage Peuhl', 'Sauce tomate'];
$garnitures = ['Riz pilaf', 'Riz aux légumes', 'Pommes frites', 'Pommes sautés', 'Légumes sautés', 'Haricot vert à l\'ail', 'Aloco', 'Atiéké', 'Pâtes au beurre', 'Couscous', 'Riz au gras'];
$parfums_glace = ['Vanille', 'Chocolat', 'Fraise', 'Américaine', 'Yaourt', 'Coco', 'Pistache', 'Ananas', 'Ange-Bleu', 'Biscotto', 'Créole', 'Chocolat au Fruits', 'Chocolat Kinder Bueno', 'Ciocco', 'Tiramisu', 'Passion', 'Citron', 'Mangue', 'Banane', 'Melon', 'Menthe', 'Moka', 'Oréo', 'Pino-pinguino', 'Caramel'];

// Catégories Menu Types
$cats_plats = ['Volailles', 'Viandes', 'Poissons', 'Pizza', 'Snack', 'Omelettes', 'Pâtes', 'Petit déjeuner', 'Entrées Froides', 'Entrées chaudes', 'Glaces', 'Déjeuner', 'Petite faim', 'Burger', 'Sandwich']; // Added Burger/Sandwich just in case
$cats_bar = ['Cafe nespresso vertuo', 'Vins', 'Eaux Minérales', 'Milkshakes', 'Boissons chaudes', 'Jus pressés', 'Bières', 'Sucreries', 'Nos Champagnes', 'Amers et Anisés', 'Liqueurs', 'Cocktails / Boisson sans Alcool', 'Cocktails / Boisson Alcoolisées', 'Whiskies', 'Cognacs', 'Gins', 'Téquilas', 'Rhums', 'Vodka', 'Shots'];

// 2. Fonctions Utilitaires
function getOrInsertOption($pdo, $nom, $type, $prix)
{
    static $cache = [];
    $key = "$nom|$type";
    if (isset($cache[$key]))
        return $cache[$key];

    $stmt = $pdo->prepare("SELECT id FROM options WHERE nom = ? AND type = ?");
    $stmt->execute([$nom, $type]);
    $id = $stmt->fetchColumn();

    if (!$id) {
        $stmt = $pdo->prepare("INSERT INTO options (nom, type, prix) VALUES (?, ?, ?)");
        $stmt->execute([$nom, $type, $prix]);
        $id = $pdo->lastInsertId();
    }
    $cache[$key] = $id;
    return $id;
}

function linkCategoryOption($pdo, $categoryName, $optionId)
{
    if (!$categoryName)
        return;
    // Get Cat ID
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE nom = ?");
    $stmt->execute([$categoryName]);
    $catId = $stmt->fetchColumn();
    if ($catId) {
        // Check link
        $stmt = $pdo->prepare("INSERT IGNORE INTO category_options (category_id, option_id) VALUES (?, ?)");
        $stmt->execute([$catId, $optionId]);
    }
}

// 3. Vidage des Tables (via init_db.sql préférablement, mais ici pour options data)
echo "Initialisation des Options Globales...\n";
$pdo->exec("DELETE FROM options; DELETE FROM category_options;"); // Clear old options

// Insertion des Supplements
foreach ($supplements_500 as $nom)
    getOrInsertOption($pdo, $nom, 'supplement', 500);
foreach ($supplements_1000 as $nom)
    getOrInsertOption($pdo, $nom, 'supplement', 1000);
// Insertion Garnitures
foreach ($garnitures as $nom)
    getOrInsertOption($pdo, $nom, 'garniture', 0);
// Insertion Parfums
foreach ($parfums_glace as $nom)
    getOrInsertOption($pdo, $nom, 'parfum', 0);

echo "Options insérées.\n";


// 4. Importation CSV
$handle = fopen($csvFile, "r");
if ($handle === FALSE)
    die("Impossible d'ouvrir le CSV.");

// Skip header
fgetcsv($handle, 1000, ",");

$categories_cache = [];

echo "Importation des Plats...\n";

while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
    $id = intval($data[0]); // ID du CSV
    $nom = $data[4] ?? '';
    if (empty($nom) || empty($id))
        continue;

    $description = $data[9] ?? '';
    $prix_str = $data[26] ?? '0';
    $prix = floatval(str_replace(',', '.', str_replace(' ', '', $prix_str)));

    $categories_str = $data[27] ?? '';
    $cats = explode(',', $categories_str);
    $main_cat = trim($cats[0]);

    // Fix category cleaning (remove quotes if any)
    $main_cat = str_replace('"', '', $main_cat);

    // Create/Get Category
    $category_id = null;
    if (!empty($main_cat)) {
        if (!isset($categories_cache[$main_cat])) {
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE nom = ?");
            $stmt->execute([$main_cat]);
            $cat_id = $stmt->fetchColumn();
            if (!$cat_id) {
                $stmt = $pdo->prepare("INSERT INTO categories (nom) VALUES (?)");
                $stmt->execute([$main_cat]);
                $cat_id = $pdo->lastInsertId();
            }
            $categories_cache[$main_cat] = $cat_id;
        }
        $category_id = $categories_cache[$main_cat];
    }

    // Determine Menu Type
    $menu_type = 'plats'; // Default
    if (in_array($main_cat, $cats_bar)) {
        $menu_type = 'bar';
    }
    // Fallback logic if needed (or assume Plats)

    // Images (URL external or local?) 
    // CSV column 30 usually has image URL provided by WC export
    $image = $data[30] ?? '';

    // Insert Dish
    // Using INSERT ON DUPLICATE KEY UPDATE to handle re-runs properly with ID
    $stmt = $pdo->prepare("INSERT INTO dishes (id, nom, description, prix, category_id, image, menu_type, active) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                           ON DUPLICATE KEY UPDATE nom=VALUES(nom), description=VALUES(description), prix=VALUES(prix), category_id=VALUES(category_id), image=VALUES(image), menu_type=VALUES(menu_type)");
    $stmt->execute([$id, $nom, $description, $prix, $category_id, $image, $menu_type]);


    // --- Gestion des Liaisons Options Globale par Catégorie ---
    // Pizza -> Supplements
    if ($main_cat === 'Pizza') {
        foreach ($supplements_500 as $s)
            linkCategoryOption($pdo, $main_cat, getOrInsertOption($pdo, $s, 'supplement', 500));
        foreach ($supplements_1000 as $s)
            linkCategoryOption($pdo, $main_cat, getOrInsertOption($pdo, $s, 'supplement', 1000));
    }
    // Viandes/Poissons/Volailles -> Garnitures
    if (in_array($main_cat, ['Viandes', 'Poissons', 'Volailles'])) {
        foreach ($garnitures as $g)
            linkCategoryOption($pdo, $main_cat, getOrInsertOption($pdo, $g, 'garniture', 0));
    }
    // Glaces -> Parfums
    if ($main_cat === 'Glaces') {
        foreach ($parfums_glace as $p)
            linkCategoryOption($pdo, $main_cat, getOrInsertOption($pdo, $p, 'parfum', 0));
    }

    // --- Gestion des Variations Spécifiques (Hardcoded from WP function) ---
    // ID 8124 (Type: Bar, Dorade, Tilapia)
    if ($id == 8124) {
        $vars = [
            ['Bar', 4500],
            ['Dorade', 5500],
            ['Tilapia', 6500]
        ];
        foreach ($vars as $v) {
            $stmt = $pdo->prepare("INSERT INTO dish_variations (dish_id, name, price, group_name) VALUES (?, ?, ?, 'Type')");
            $stmt->execute([$id, $v[0], $v[1]]);
        }
    }
    // ID 8561 (Taille: petite, Moyenne, Grande)
    if ($id == 8561) {
        $vars = [['petite', 5000], ['Moyenne', 6000], ['Grande', 7000]];
        foreach ($vars as $v) {
            $stmt = $pdo->prepare("INSERT INTO dish_variations (dish_id, name, price, group_name) VALUES (?, ?, ?, 'Taille')");
            $stmt->execute([$id, $v[0], $v[1]]);
        }
    }
    // ID 8563 (Taille)
    if ($id == 8563) {
        $vars = [['petite', 7000], ['Moyenne', 9000], ['Grande', 12000]];
        foreach ($vars as $v) {
            $stmt = $pdo->prepare("INSERT INTO dish_variations (dish_id, name, price, group_name) VALUES (?, ?, ?, 'Taille')");
            $stmt->execute([$id, $v[0], $v[1]]);
        }
    }
    // ID 8620 (Taille)
    if ($id == 8620) {
        $vars = [['petite', 3500], ['Grande', 5500]];
        foreach ($vars as $v) {
            $stmt = $pdo->prepare("INSERT INTO dish_variations (dish_id, name, price, group_name) VALUES (?, ?, ?, 'Taille')");
            $stmt->execute([$id, $v[0], $v[1]]);
        }
    }

    // --- Custom Options for Product 8554 (Sauce) ---
    if ($id == 8554) {
        // Sauce Basquaise, Sauce Crème, Sauce poivre vert (Radio, Prix 0)
        // Here we insert into options first then link to DISH directly
        $opts = ['Sauce Basquaise', 'Sauce Crème', 'Sauce poivre vert'];
        foreach ($opts as $oName) {
            $oid = getOrInsertOption($pdo, $oName, 'custom_radio', 0); // custom_radio type for UI
            $stmt = $pdo->prepare("INSERT IGNORE INTO dish_options (dish_id, option_id) VALUES (?, ?)");
            $stmt->execute([$id, $oid]);
        }
    }
}

fclose($handle);
echo "Importation terminée.\n";
?>