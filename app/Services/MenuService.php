<?php

namespace App\Services;

use App\Models\Dish;
use App\Models\Option;

/**
 * Catalogue restaurant partagé entre la page menu web et GET /api/menu.
 */
class MenuService
{
    /**
     * Catégories alcoolisées masquées quand SHOW_ALCOHOLIC_BEVERAGES=false.
     */
    private array $alcoholicCategories = [
        'Vins', 'Bières', 'Bièrers importées', 'Bières pression',
        'Nos Champagnes', 'Amers et Anisés', 'Liqueurs',
        'Cocktails / Boisson Alcoolisées', 'Whiskies', 'Cognacs',
        'Gins', 'Téquilas', 'Rhums', 'Vodka', 'Shots',
    ];

    /**
     * @return array ['productsData' => [...], 'garnituresGlobal' => [...]]
     */
    public function catalog(): array
    {
        $garnituresGlobal = Option::where('type', 'garniture')->pluck('nom')->toArray();

        $showAlcoholic = filter_var(env('SHOW_ALCOHOLIC_BEVERAGES', 'true'), FILTER_VALIDATE_BOOLEAN);

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

            if ($dish->variations->isNotEmpty()) {
                $attributes['manual_variations'] = [
                    'title' => $dish->variations->first()->group_name ?? 'Variations',
                    'items' => $dish->variations->map(fn($v) => [
                        'name'  => $v->name,
                        'price' => (float)$v->price,
                    ])->toArray(),
                ];
            }

            $catOptions  = $dish->category?->options ?? collect();
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

        return ['productsData' => $productsData, 'garnituresGlobal' => $garnituresGlobal];
    }
}
