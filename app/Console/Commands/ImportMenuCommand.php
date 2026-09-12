<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Category;
use App\Models\Dish;
use App\Models\DishVariation;
use App\Models\Option;
use Illuminate\Support\Facades\DB;

class ImportMenuCommand extends Command
{
    protected $signature = 'app:import-menu {file? : Path to menu.csv}';
    protected $description = 'Import menu data from CSV into the database';

    private array $supplements500 = ['Oeuf', 'Crême fraîche', 'Mais', 'Jambon', 'Poulet', 'Poulet Barbecue', 'Boeuf haché', 'Fromage'];
    private array $supplements1000 = ['Crevettes', 'Thon', 'Chorizo', 'Merguez', 'Fromage Peuhl', 'Sauce tomate'];
    private array $garnitures = ['Riz pilaf', 'Riz aux légumes', 'Pommes frites', 'Pommes sautés', 'Légumes sautés', "Haricot vert à l'ail", 'Aloco', 'Atiéké', 'Pâtes au beurre', 'Couscous', 'Riz au gras'];
    private array $parfumsGlace = ['Vanille', 'Chocolat', 'Fraise', 'Américaine', 'Yaourt', 'Coco', 'Pistache', 'Ananas', 'Ange-Bleu', 'Biscotto', 'Créole', 'Chocolat au Fruits', 'Chocolat Kinder Bueno', 'Ciocco', 'Tiramisu', 'Passion', 'Citron', 'Mangue', 'Banane', 'Melon', 'Menthe', 'Moka', 'Oréo', 'Pino-pinguino', 'Caramel'];
    private array $catsBar = ['Cafe nespresso vertuo', 'Vins', 'Eaux Minérales', 'Milkshakes', 'Boissons chaudes', 'Jus pressés', 'Bières', 'Sucreries', 'Nos Champagnes', 'Amers et Anisés', 'Liqueurs', 'Cocktails / Boisson sans Alcool', 'Cocktails / Boisson Alcoolisées', 'Whiskies', 'Cognacs', 'Gins', 'Téquilas', 'Rhums', 'Vodka', 'Shots'];

    private array $optionsCache = [];

    public function handle(): int
    {
        $csvFile = $this->argument('file') ?? base_path('exemple/menu.csv');

        if (!file_exists($csvFile)) {
            $this->error("CSV file not found: {$csvFile}");
            return 1;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $this->info('Clearing existing data...');
        DB::table('dish_options')->delete();
        DB::table('category_options')->delete();
        DB::table('dish_variations')->delete();
        DB::table('dishes')->delete();
        DB::table('categories')->delete();
        DB::table('options')->delete();

        $this->info('Inserting global options...');
        foreach ($this->supplements500 as $nom)  $this->getOrInsertOption($nom, 'supplement', 500);
        foreach ($this->supplements1000 as $nom) $this->getOrInsertOption($nom, 'supplement', 1000);
        foreach ($this->garnitures as $nom)      $this->getOrInsertOption($nom, 'garniture', 0);
        foreach ($this->parfumsGlace as $nom)    $this->getOrInsertOption($nom, 'parfum', 0);

        $this->info('Reading CSV...');
        $handle = fopen($csvFile, 'r');
        fgetcsv($handle, 1000, ','); // skip header

        $categoriesCache = [];
        $count = 0;

        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $id        = (int)($data[0] ?? 0);
            $published = (int)($data[5] ?? 0);
            $nom       = $data[4] ?? '';
            // Skip unpublished or empty products
            if (empty($nom) || empty($id) || $published < 1) continue;

            $description = $data[9] ?? '';
            $prixStr = $data[26] ?? '0';
            $prix = (float)str_replace([',', ' '], ['.', ''], $prixStr);

            $categoriesStr = $data[27] ?? '';
            $cats = explode(',', $categoriesStr);
            $mainCat = str_replace('"', '', trim($cats[0]));

            $categoryId = null;
            if (!empty($mainCat)) {
                if (!isset($categoriesCache[$mainCat])) {
                    $cat = Category::firstOrCreate(['nom' => $mainCat]);
                    $categoriesCache[$mainCat] = $cat->id;

                    // Link category options
                    if ($mainCat === 'Pizza') {
                        foreach (array_merge($this->supplements500, $this->supplements1000) as $s) {
                            $oid = $this->getOrInsertOption($s, 'supplement', in_array($s, $this->supplements500) ? 500 : 1000);
                            DB::table('category_options')->insertOrIgnore(['category_id' => $cat->id, 'option_id' => $oid]);
                        }
                    }
                    if (in_array($mainCat, ['Viandes', 'Poissons', 'Volailles'])) {
                        foreach ($this->garnitures as $g) {
                            $oid = $this->getOrInsertOption($g, 'garniture', 0);
                            DB::table('category_options')->insertOrIgnore(['category_id' => $cat->id, 'option_id' => $oid]);
                        }
                    }
                    if ($mainCat === 'Glaces') {
                        foreach ($this->parfumsGlace as $p) {
                            $oid = $this->getOrInsertOption($p, 'parfum', 0);
                            DB::table('category_options')->insertOrIgnore(['category_id' => $cat->id, 'option_id' => $oid]);
                        }
                    }
                }
                $categoryId = $categoriesCache[$mainCat];
            }

            $menuType = in_array($mainCat, $this->catsBar) ? 'bar' : 'plats';
            $image = $data[30] ?? '';

            Dish::upsert([
                ['id' => $id, 'nom' => $nom, 'description' => $description, 'prix' => $prix,
                 'category_id' => $categoryId, 'image' => $image, 'menu_type' => $menuType, 'active' => 1,
                 'orders_count' => 0, 'created_at' => now(), 'updated_at' => now()]
            ], ['id'], ['nom', 'description', 'prix', 'category_id', 'image', 'menu_type', 'updated_at']);

            // ── Specific variations (mapped to actual CSV IDs) ──────────────────
            // ID 7432 = "Retour de pêche" (Poisson du jour : bar, dorade, tilapia)
            if ($id == 7432) {
                foreach ([['Bar', 4500], ['Dorade', 5500], ['Tilapia', 6500]] as [$vname, $vprice]) {
                    DishVariation::firstOrCreate(['dish_id' => $id, 'name' => $vname], ['price' => $vprice, 'group_name' => 'Type', 'stock_kg' => 0]);
                }
            }
            // ID 7853 = "Filet de Bœuf grillé à la basquaise" → sauce options
            if ($id == 7853) {
                foreach (['Sauce Basquaise', 'Sauce Crème', 'Sauce poivre vert'] as $oName) {
                    $oid = $this->getOrInsertOption($oName, 'custom_radio', 0);
                    DB::table('dish_options')->insertOrIgnore(['dish_id' => $id, 'option_id' => $oid]);
                }
            }

            $count++;
        }

        fclose($handle);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info("✅ Import terminé : {$count} plats importés.");
        return 0;
    }

    private function getOrInsertOption(string $nom, string $type, float $prix): int
    {
        $key = "{$nom}|{$type}";
        if (isset($this->optionsCache[$key])) return $this->optionsCache[$key];

        $option = Option::firstOrCreate(['nom' => $nom, 'type' => $type], ['prix' => $prix]);
        $this->optionsCache[$key] = $option->id;
        return $option->id;
    }
}
