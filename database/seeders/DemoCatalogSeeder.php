<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class DemoCatalogSeeder extends Seeder
{
    /**
     * Prices are hellers including VAT. Keyed by slug so the seeder can run repeatedly.
     *
     * @var array<string, array{name: string, position: int, products: list<array{string, string, int, int, bool, string}>}>
     */
    private const CATALOG = [
        'kava' => [
            'name' => 'Káva',
            'position' => 1,
            'products' => [
                ['espresso-blend', 'Espresso blend 1 kg', 64900, 25, true, 'Směs brazilské a indické arabiky s tónem čokolády a oříšků. Vhodná do pákového kávovaru.'],
                ['kolumbie-supremo', 'Kolumbie Supremo 250 g', 28900, 12, true, 'Jednodruhová káva s karamelovou sladkostí a jemnou kyselinkou.'],
                ['etiopie-yirgacheffe', 'Etiopie Yirgacheffe 250 g', 34950, 1, true, 'Květinová a citrusová chuť, ideální na filtr.'],
                ['bezkofeinova-smes', 'Bezkofeinová směs 250 g', 26900, 0, true, 'Bezkofeinová káva zpracovaná švýcarskou vodní metodou.'],
            ],
        ],
        'caj' => [
            'name' => 'Čaj',
            'position' => 2,
            'products' => [
                ['sencha-zeleny-caj', 'Sencha zelený čaj 100 g', 18900, 30, true, 'Japonský zelený čaj se svěží travnatou chutí.'],
                ['earl-grey', 'Earl Grey 100 g', 14900, 40, true, 'Černý čaj s bergamotem.'],
                ['rooibos-vanilka', 'Rooibos s vanilkou 100 g', 12900, 18, true, 'Bezkofeinový jihoafrický rooibos s vanilkou.'],
                ['matcha', 'Matcha ceremoniální 30 g', 59900, 6, false, 'Dočasně stažená z prodeje – čekáme na novou sklizeň.'],
            ],
        ],
        'prislusenstvi' => [
            'name' => 'Příslušenství',
            'position' => 3,
            'products' => [
                ['moka-konvicka', 'Moka konvička na 3 šálky', 89900, 8, true, 'Hliníková konvička na sporák, připraví tři šálky silné kávy.'],
                ['french-press', 'French press 1 l', 74950, 5, true, 'Skleněná konvice s nerezovým sítkem.'],
                ['rucni-mlynek', 'Ruční mlýnek s keramickými kameny', 129900, 3, true, 'Nastavitelná hrubost mletí od espressa po french press.'],
                ['porcelanovy-salek', 'Porcelánový šálek 150 ml', 24900, 50, true, 'Šálek s podšálkem, vhodný do myčky.'],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $categorySlug => $categoryData) {
            $category = Category::query()->updateOrCreate(
                ['slug' => $categorySlug],
                ['name' => $categoryData['name'], 'position' => $categoryData['position']],
            );

            foreach ($categoryData['products'] as [$slug, $name, $price, $stock, $available, $description]) {
                Product::query()->updateOrCreate(['slug' => $slug], [
                    'category_id' => $category->id,
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'stock' => $stock,
                    'is_available' => $available,
                    'image_path' => "images/demo/{$slug}.svg",
                ]);
            }
        }
    }
}
