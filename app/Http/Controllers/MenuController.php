<?php

namespace App\Http\Controllers;

use App\Models\Dish;
use App\Models\Option;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    /**
     * Alcoholic categories hidden when SHOW_ALCOHOLIC_BEVERAGES=false.
     * Non-alcoholic bar items (coffees, juices, soft drinks, milkshakes) are always visible.
     */
    private array $alcoholicCategories = [
        'Vins', 'Bières', 'Bièrers importées', 'Bières pression',
        'Nos Champagnes', 'Amers et Anisés', 'Liqueurs',
        'Cocktails / Boisson Alcoolisées', 'Whiskies', 'Cognacs',
        'Gins', 'Téquilas', 'Rhums', 'Vodka', 'Shots',
    ];

    public function index()
    {
        // Garnitures globales
        $garnituresGlobal = Option::where('type', 'garniture')->pluck('nom')->toArray();

        // Check env flag (default: show alcoholic beverages)
        $showAlcoholic = filter_var(env('SHOW_ALCOHOLIC_BEVERAGES', 'true'), FILTER_VALIDATE_BOOLEAN);

        // Récupérer tous les plats actifs avec leurs catégories
        $dishes = Dish::with(['category', 'variations', 'options'])
            ->where('active', 1)
            ->when(!$showAlcoholic, function ($q) {
                $q->where(function ($sub) {
                    $sub->whereHas('category', fn ($cat) => $cat->whereNotIn('nom', $this->alcoholicCategories))
                        ->orWhereNull('category_id');
                });
            })
            ->orderByDesc('orders_count')
            ->orderBy('nom')
            ->get();

        $productsData = [];

        foreach ($dishes as $dish) {
            $attributes = [];

            // Variations manuelles
            if ($dish->variations->isNotEmpty()) {
                $attributes['manual_variations'] = [
                    'title' => $dish->variations->first()->group_name ?? 'Variations',
                    'items' => $dish->variations->map(fn($v) => [
                        'name'  => $v->name,
                        'price' => (float)$v->price,
                    ])->toArray(),
                ];
            }

            // Options de la catégorie
            $catOptions = $dish->category?->options ?? collect();
            // Options spécifiques au plat
            $dishOptions = $dish->options;
            $allOptions  = $catOptions->merge($dishOptions);

            foreach ($allOptions as $opt) {
                $type = $opt->type;
                if ($type === 'garniture') {
                    $attributes['garnitures'][] = $opt->nom;
                } elseif ($type === 'supplement') {
                    $attributes['supplements'][] = ['name' => $opt->nom, 'price' => (float)$opt->prix];
                } elseif ($type === 'parfum') {
                    $attributes['parfums'][] = $opt->nom;
                } elseif ($type === 'custom_radio') {
                    if (!isset($attributes['custom_options'])) $attributes['custom_options'] = [];
                    $sauceIdx = -1;
                    foreach ($attributes['custom_options'] as $idx => $co) {
                        if ($co['title'] === 'Sauce au choix') { $sauceIdx = $idx; break; }
                    }
                    if ($sauceIdx === -1) {
                        $attributes['custom_options'][] = ['title' => 'Sauce au choix', 'type' => 'radio', 'required' => true, 'items' => []];
                        $sauceIdx = count($attributes['custom_options']) - 1;
                    }
                    $attributes['custom_options'][$sauceIdx]['items'][] = ['name' => $opt->nom, 'price' => (float)$opt->prix];
                }
            }

            $productsData[] = [
                'id'          => (int)$dish->id,
                'name'        => $dish->nom,
                'description' => $dish->description,
                'price'       => (float)$dish->prix,
                'category'    => $dish->category?->nom ?? '',
                'menu'        => $dish->menu_type,
                'image'       => $dish->image,
                'attributes'  => $attributes,
            ];
        }

        return view('menu', compact('productsData', 'garnituresGlobal'));
    }
}
