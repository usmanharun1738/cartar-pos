<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Users with different roles
        $this->createUsers();
        
        // Create Categories
        $categories = $this->createCategories();
        
        // Create Products
        $this->createProducts($categories);
    }

    /**
     * Create users with different roles.
     */
    private function createUsers(): void
    {
        // Admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@cartarpos.com',
            'password' => Hash::make('password'),
            'employee_id' => 'EMP001',
            'role' => 'admin',
        ]);

        // Store Manager
        User::create([
            'name' => 'Store Manager',
            'email' => 'manager@cartarpos.com',
            'password' => Hash::make('password'),
            'employee_id' => 'EMP002',
            'role' => 'store-manager',
        ]);

        // Sales Staff
        User::create([
            'name' => 'John Sales',
            'email' => 'john@cartarpos.com',
            'password' => Hash::make('password'),
            'employee_id' => 'EMP003',
            'role' => 'sales',
        ]);

        User::create([
            'name' => 'Jane Sales',
            'email' => 'jane@cartarpos.com',
            'password' => Hash::make('password'),
            'employee_id' => 'EMP004',
            'role' => 'sales',
        ]);
    }

    /**
     * Create product categories.
     */
    private function createCategories(): array
    {
        $categories = [
            ['name' => 'Drinks', 'slug' => 'drinks', 'icon' => 'local_cafe', 'sort_order' => 1],
            ['name' => 'Food', 'slug' => 'food', 'icon' => 'restaurant', 'sort_order' => 2],
            ['name' => 'Snacks', 'slug' => 'snacks', 'icon' => 'cookie', 'sort_order' => 3],
            ['name' => 'Desserts', 'slug' => 'desserts', 'icon' => 'cake', 'sort_order' => 4],
            ['name' => 'Sides', 'slug' => 'sides', 'icon' => 'dining', 'sort_order' => 5],
        ];

        $created = [];
        foreach ($categories as $cat) {
            $created[$cat['name']] = Category::create($cat);
        }

        return $created;
    }

    /**
     * Create sample products.
     */
    private function createProducts(array $categories): void
    {
        $products = [
            // Drinks
            ['category' => 'Drinks', 'name' => 'Coca-Cola', 'sku' => 'DRK001', 'cost' => 100, 'price' => 200, 'stock' => 100],
            ['category' => 'Drinks', 'name' => 'Fanta Orange', 'sku' => 'DRK002', 'cost' => 100, 'price' => 200, 'stock' => 80],
            ['category' => 'Drinks', 'name' => 'Sprite', 'sku' => 'DRK003', 'cost' => 100, 'price' => 200, 'stock' => 75],
            ['category' => 'Drinks', 'name' => 'Water 50cl', 'sku' => 'DRK004', 'cost' => 50, 'price' => 100, 'stock' => 150],
            ['category' => 'Drinks', 'name' => 'Malt Drink', 'sku' => 'DRK005', 'cost' => 150, 'price' => 300, 'stock' => 50],
            ['category' => 'Drinks', 'name' => 'Fresh Juice', 'sku' => 'DRK006', 'cost' => 200, 'price' => 500, 'stock' => 30, 'is_hot' => true],
            
            // Food
            ['category' => 'Food', 'name' => 'Jollof Rice', 'sku' => 'FD001', 'cost' => 400, 'price' => 1000, 'stock' => 25, 'is_hot' => true],
            ['category' => 'Food', 'name' => 'Fried Rice', 'sku' => 'FD002', 'cost' => 400, 'price' => 1000, 'stock' => 20],
            ['category' => 'Food', 'name' => 'Chicken & Chips', 'sku' => 'FD003', 'cost' => 600, 'price' => 1500, 'stock' => 15],
            ['category' => 'Food', 'name' => 'Suya Wrap', 'sku' => 'FD004', 'cost' => 500, 'price' => 1200, 'stock' => 18, 'is_hot' => true],
            ['category' => 'Food', 'name' => 'Meat Pie', 'sku' => 'FD005', 'cost' => 150, 'price' => 350, 'stock' => 40],
            ['category' => 'Food', 'name' => 'Sausage Roll', 'sku' => 'FD006', 'cost' => 100, 'price' => 250, 'stock' => 45],
            
            // Snacks
            ['category' => 'Snacks', 'name' => 'Pringles', 'sku' => 'SNK001', 'cost' => 500, 'price' => 800, 'stock' => 25],
            ['category' => 'Snacks', 'name' => 'Lays Chips', 'sku' => 'SNK002', 'cost' => 200, 'price' => 400, 'stock' => 35],
            ['category' => 'Snacks', 'name' => 'Plantain Chips', 'sku' => 'SNK003', 'cost' => 150, 'price' => 300, 'stock' => 50],
            ['category' => 'Snacks', 'name' => 'Popcorn', 'sku' => 'SNK004', 'cost' => 100, 'price' => 250, 'stock' => 3, 'threshold' => 10],
            
            // Desserts
            ['category' => 'Desserts', 'name' => 'Ice Cream Cup', 'sku' => 'DST001', 'cost' => 200, 'price' => 500, 'stock' => 20],
            ['category' => 'Desserts', 'name' => 'Chocolate Cake', 'sku' => 'DST002', 'cost' => 400, 'price' => 1000, 'stock' => 8, 'is_hot' => true],
            ['category' => 'Desserts', 'name' => 'Doughnut', 'sku' => 'DST003', 'cost' => 100, 'price' => 300, 'stock' => 0],
            ['category' => 'Desserts', 'name' => 'Puff Puff', 'sku' => 'DST004', 'cost' => 50, 'price' => 150, 'stock' => 60],
            
            // Sides
            ['category' => 'Sides', 'name' => 'Coleslaw', 'sku' => 'SD001', 'cost' => 100, 'price' => 300, 'stock' => 30],
            ['category' => 'Sides', 'name' => 'French Fries', 'sku' => 'SD002', 'cost' => 150, 'price' => 400, 'stock' => 25],
            ['category' => 'Sides', 'name' => 'Fried Plantain', 'sku' => 'SD003', 'cost' => 100, 'price' => 350, 'stock' => 20],
        ];

        foreach ($products as $product) {
            Product::create([
                'category_id' => $categories[$product['category']]->id,
                'name' => $product['name'],
                'sku' => $product['sku'],
                'cost_price' => $product['cost'],
                'selling_price' => $product['price'],
                'stock_quantity' => $product['stock'],
                'low_stock_threshold' => $product['threshold'] ?? 5,
                'is_active' => true,
                'is_hot' => $product['is_hot'] ?? false,
            ]);
        }
    }
}
