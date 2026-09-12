<?php
$csvFile = __DIR__ . '/exemple/menu.csv';
$f = fopen($csvFile, 'r');
if (!$f) {
    die("Cannot open CSV file\n");
}

$header = fgetcsv($f);
echo "Products in Poissons, Viandes, Volailles:\n";

while ($row = fgetcsv($f)) {
    $id = (int)$row[0];
    $name = $row[4];
    $price = $row[26];
    $category = $row[27];
    
    if (in_array(trim($category), ['Poissons', 'Viandes', 'Volailles'])) {
        echo "ID: $id | Name: $name | Category: $category | Price: $price\n";
    }
}
fclose($f);
