<?php

namespace Database\Seeders;

use App\Models\VariationType;
use App\Models\VariationOption;
use Illuminate\Database\Seeder;

class VariationSeeder extends Seeder
{
    public function run(): void
    {
        // Size variations
        $size = VariationType::create([
            'name' => 'Size',
            'slug' => 'size',
            'sort_order' => 1,
        ]);

        $sizeOptions = [
            ['name' => 'Extra Small', 'code' => 'XS', 'sort_order' => 1],
            ['name' => 'Small', 'code' => 'S', 'sort_order' => 2],
            ['name' => 'Medium', 'code' => 'M', 'sort_order' => 3],
            ['name' => 'Large', 'code' => 'L', 'sort_order' => 4],
            ['name' => 'Extra Large', 'code' => 'XL', 'sort_order' => 5],
            ['name' => 'XXL', 'code' => 'XXL', 'sort_order' => 6],
        ];

        foreach ($sizeOptions as $option) {
            $size->options()->create($option);
        }

        // Color variations
        $color = VariationType::create([
            'name' => 'Color',
            'slug' => 'color',
            'sort_order' => 2,
        ]);

        $colorOptions = [
            ['name' => 'Black', 'code' => 'BLK', 'value' => '#000000', 'sort_order' => 1],
            ['name' => 'White', 'code' => 'WHT', 'value' => '#FFFFFF', 'sort_order' => 2],
            ['name' => 'Red', 'code' => 'RED', 'value' => '#FF0000', 'sort_order' => 3],
            ['name' => 'Blue', 'code' => 'BLU', 'value' => '#0000FF', 'sort_order' => 4],
            ['name' => 'Green', 'code' => 'GRN', 'value' => '#00FF00', 'sort_order' => 5],
            ['name' => 'Navy', 'code' => 'NVY', 'value' => '#000080', 'sort_order' => 6],
            ['name' => 'Grey', 'code' => 'GRY', 'value' => '#808080', 'sort_order' => 7],
            ['name' => 'Royal Blue', 'code' => 'RB', 'value' => '#4169E1', 'sort_order' => 8],
            ['name' => 'Midnight Black', 'code' => 'MB', 'value' => '#191970', 'sort_order' => 9],
        ];

        foreach ($colorOptions as $option) {
            $color->options()->create($option);
        }

        // Material variations
        $material = VariationType::create([
            'name' => 'Material',
            'slug' => 'material',
            'sort_order' => 3,
        ]);

        $materialOptions = [
            ['name' => 'Cotton', 'code' => 'COT', 'sort_order' => 1],
            ['name' => 'Polyester', 'code' => 'PLY', 'sort_order' => 2],
            ['name' => 'Leather', 'code' => 'LTH', 'sort_order' => 3],
            ['name' => 'Denim', 'code' => 'DNM', 'sort_order' => 4],
            ['name' => 'Wool', 'code' => 'WOL', 'sort_order' => 5],
            ['name' => 'Silk', 'code' => 'SLK', 'sort_order' => 6],
            ['name' => 'Linen', 'code' => 'LIN', 'sort_order' => 7],
        ];

        foreach ($materialOptions as $option) {
            $material->options()->create($option);
        }
    }
}
