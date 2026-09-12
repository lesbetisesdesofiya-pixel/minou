<?php
$csvFile = 'public_html\avepozocommande\menu.csv';
if (!file_exists($csvFile)) die("CSV not found");

$handle = fopen($csvFile, "r");
fgetcsv($handle, 1000, ","); // skip header

$sql = "SET FOREIGN_KEY_CHECKS = 0;\n\n";

// 1. Options Globales
$supplements_500 = ['Oeuf', 'Crême fraîche', 'Mais', 'Jambon', 'Poulet', 'Poulet Barbecue', 'Boeuf haché', 'Fromage'];
$supplements_1000 = ['Crevettes', 'Thon', 'Chorizo', 'Merguez', 'Fromage Peuhl', 'Sauce tomate'];
$garnitures = ['Riz pilaf', 'Riz aux légumes', 'Pommes frites', 'Pommes sautés', 'Légumes sautés', 'Haricot vert à l\'ail', 'Aloco', 'Atiéké', 'Pâtes au beurre', 'Couscous', 'Riz au gras'];
$parfums_glace = ['Vanille', 'Chocolat', 'Fraise', 'Américaine', 'Yaourt', 'Coco', 'Pistache', 'Ananas', 'Ange-Bleu', 'Biscotto', 'Créole', 'Chocolat au Fruits', 'Chocolat Kinder Bueno', 'Ciocco', 'Tiramisu', 'Passion', 'Citron', 'Mangue', 'Banane', 'Melon', 'Menthe', 'Moka', 'Oréo', 'Pino-pinguino', 'Caramel'];

$option_id = 1;
$options_map = []; // name|type => id

function addOption(&$sql, $nom, $type, $prix, &$option_id, &$options_map) {
    if (isset($options_map["$nom|$type"])) return;
    $sql .= "INSERT INTO options (id, nom, type, prix) VALUES ($option_id, " . quote($nom) . ", '$type', $prix);\n";
    $options_map["$nom|$type"] = $option_id;
    $option_id++;
}

function quote($str) {
    return "'" . str_replace("'", "''", $str) . "'";
}

foreach ($supplements_500 as $s) addOption($sql, $s, 'supplement', 500, $option_id, $options_map);
foreach ($supplements_1000 as $s) addOption($sql, $s, 'supplement', 1000, $option_id, $options_map);
foreach ($garnitures as $g) addOption($sql, $g, 'garniture', 0, $option_id, $options_map);
foreach ($parfums_glace as $p) addOption($sql, $p, 'parfum', 0, $option_id, $options_map);

$sql .= "\n";

// 2. Categories & Dishes
$cats_bar = ['Cafe nespresso vertuo', 'Vins', 'Eaux Minérales', 'Milkshakes', 'Boissons chaudes', 'Jus pressés', 'Bières', 'Sucreries', 'Nos Champagnes', 'Amers et Anisés', 'Liqueurs', 'Cocktails / Boisson sans Alcool', 'Cocktails / Boisson Alcoolisées', 'Whiskies', 'Cognacs', 'Gins', 'Téquilas', 'Rhums', 'Vodka', 'Shots'];
$categories_cache = []; // name => id
$cat_id_seq = 1;

$dish_variations = "";
$category_options = "";
$dish_options = "";

while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
    $id = intval($data[0]);
    $nom = $data[4] ?? '';
    if (empty($nom) || empty($id)) continue;

    $description = $data[9] ?? '';
    $prix_str = $data[26] ?? '0';
    $prix = floatval(str_replace(',', '.', str_replace(' ', '', $prix_str)));

    $categories_str = $data[27] ?? '';
    $cats = explode(',', $categories_str);
    $main_cat = trim($cats[0]);
    $main_cat = str_replace('"', '', $main_cat);

    if (!empty($main_cat)) {
        if (!isset($categories_cache[$main_cat])) {
            $sql .= "INSERT INTO categories (id, nom) VALUES ($cat_id_seq, " . quote($main_cat) . ");\n";
            $categories_cache[$main_cat] = $cat_id_seq;
            
            // Link Category Options
            if ($main_cat === 'Pizza') {
                foreach (array_merge($supplements_500, $supplements_1000) as $s) {
                    $oid = $options_map["$s|supplement"];
                    $category_options .= "INSERT IGNORE INTO category_options (category_id, option_id) VALUES ($cat_id_seq, $oid);\n";
                }
            }
            if (in_array($main_cat, ['Viandes', 'Poissons', 'Volailles'])) {
                foreach ($garnitures as $g) {
                    $oid = $options_map["$g|garniture"];
                    $category_options .= "INSERT IGNORE INTO category_options (category_id, option_id) VALUES ($cat_id_seq, $oid);\n";
                }
            }
            if ($main_cat === 'Glaces') {
                foreach ($parfums_glace as $p) {
                    $oid = $options_map["$p|parfum"];
                    $category_options .= "INSERT IGNORE INTO category_options (category_id, option_id) VALUES ($cat_id_seq, $oid);\n";
                }
            }
            
            $cat_id_seq++;
        }
        $cat_id = $categories_cache[$main_cat];
    } else {
        $cat_id = 'NULL';
    }

    $menu_type = in_array($main_cat, $cats_bar) ? 'bar' : 'plats';
    $image = $data[30] ?? '';

    $sql .= "INSERT INTO dishes (id, nom, description, prix, category_id, image, menu_type, active) VALUES ($id, " . quote($nom) . ", " . quote($description) . ", $prix, $cat_id, " . quote($image) . ", '$menu_type', 1);\n";

    // Variations
    if ($id == 8124) {
        $vars = [['Bar', 4500], ['Dorade', 5500], ['Tilapia', 6500]];
        foreach ($vars as $v) $dish_variations .= "INSERT INTO dish_variations (dish_id, name, price, group_name) VALUES ($id, " . quote($v[0]) . ", $v[1], 'Type');\n";
    }
    if ($id == 8561) {
        $vars = [['petite', 5000], ['Moyenne', 6000], ['Grande', 7000]];
        foreach ($vars as $v) $dish_variations .= "INSERT INTO dish_variations (dish_id, name, price, group_name) VALUES ($id, " . quote($v[0]) . ", $v[1], 'Taille');\n";
    }
    if ($id == 8563) {
        $vars = [['petite', 7000], ['Moyenne', 9000], ['Grande', 12000]];
        foreach ($vars as $v) $dish_variations .= "INSERT INTO dish_variations (dish_id, name, price, group_name) VALUES ($id, " . quote($v[0]) . ", $v[1], 'Taille');\n";
    }
    if ($id == 8620) {
        $vars = [['petite', 3500], ['Grande', 5500]];
        foreach ($vars as $v) $dish_variations .= "INSERT INTO dish_variations (dish_id, name, price, group_name) VALUES ($id, " . quote($v[0]) . ", $v[1], 'Taille');\n";
    }
    if ($id == 8554) {
        $opts = ['Sauce Basquaise', 'Sauce Crème', 'Sauce poivre vert'];
        foreach ($opts as $oName) {
            $key = "$oName|custom_radio";
            if (!isset($options_map[$key])) {
                $sql .= "INSERT INTO options (id, nom, type, prix) VALUES ($option_id, " . quote($oName) . ", 'custom_radio', 0);\n";
                $options_map[$key] = $option_id;
                $option_id++;
            }
            $oid = $options_map[$key];
            $dish_options .= "INSERT IGNORE INTO dish_options (dish_id, option_id) VALUES ($id, $oid);\n";
        }
    }
}

$sql .= "\n-- Linking Category Options\n" . $category_options;
$sql .= "\n-- Linking Dish Options\n" . $dish_options;
$sql .= "\n-- Dish Variations\n" . $dish_variations;
$sql .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";

file_put_contents('c:\xampp\htdocs\resto\import_data.sql', $sql);
echo "SQL File generated: c:\xampp\htdocs\resto\import_data.sql\n";
