<?php
/**
 * RESTO - Database Setup Script
 * Executes the SQL schema to create tables.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Configuration de la Base de Données Resto</h1>";

require_once __DIR__ . '/db.php';

$sqlFile = __DIR__ . '/../hostinger_init.sql';

if (!file_exists($sqlFile)) {
    die("❌ Erreur : Le fichier 'hostinger_init.sql' est introuvable à la racine.");
}

try {
    $sql = file_get_contents($sqlFile);
    
    // Execute the SQL
    // Note: PDO exec doesn't support multiple queries in some configs, 
    // but Hostinger typically allows it if emulated prepares are on.
    $pdo->exec($sql);
    
    echo "✅ Les tables ont été créées avec succès !<br>";
    echo "<p>Vous pouvez maintenant supprimer ce fichier et 'hostinger_init.sql' par sécurité.</p>";
    echo "<a href='get_menu.php'>Tester l'API Menu</a>";

} catch (PDOException $e) {
    echo "❌ Erreur SQL lors de la création : <br><pre>" . $e->getMessage() . "</pre>";
}
?>
