<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Katalog demo. Butuh CategorySeeder, SizeSeeder, dan ColorSeeder jalan duluan.
     *
     * Varian sengaja dibentuk dari ukuran milik jenis kategori produknya sendiri,
     * bukan ukuran acak, supaya rekomendasi ukuran bisa langsung dicoba.
     *
     * Slug kategori => daftar [nama produk, harga dasar].
     *
     * @var array<string, array<int, array{0: string, 1: int}>>
     */
    private const PRODUCTS = [
        'kaos' => [['Kaos Katun Basic', 129000], ['Kaos Oversized Archive', 159000]],
        'kemeja' => [['Kemeja Oxford Lengan Panjang', 259000], ['Kemeja Linen Santai', 289000]],
        'jaket' => [['Jaket Bomber Ringan', 449000], ['Jaket Denim Trucker', 529000]],
        'hoodie' => [['Hoodie Fleece Polos', 319000], ['Hoodie Zip-Up Archive', 379000]],
        'sweater' => [['Sweater Rajut Rib', 299000], ['Sweater Crewneck Basic', 269000]],
        'celana-jeans' => [['Celana Jeans Slim Fit', 399000], ['Celana Jeans Straight', 429000]],
        'celana-chino' => [['Celana Chino Tapered', 349000], ['Celana Chino Klasik', 329000]],
        'celana-pendek' => [['Celana Pendek Katun', 199000], ['Celana Pendek Cargo', 239000]],
        'rok' => [['Rok Plisket Midi', 289000], ['Rok A-Line Denim', 309000]],
        'sepatu-sneakers' => [['Sneakers Kanvas Low', 549000], ['Sneakers Runner Mesh', 699000]],
        'sepatu-formal' => [['Sepatu Derby Kulit', 899000], ['Sepatu Loafer Kulit', 849000]],
        'sandal' => [['Sandal Slide Karet', 189000], ['Sandal Gunung Strap', 259000]],
        'tas' => [['Tas Selempang Kanvas', 279000], ['Tas Ransel Daypack', 459000]],
        'topi' => [['Topi Baseball Katun', 149000], ['Topi Bucket Twill', 169000]],
        'ikat-pinggang' => [['Ikat Pinggang Kulit', 219000], ['Ikat Pinggang Anyam', 189000]],
    ];

    public function run(): void
    {
        $colors = Color::where('is_active', true)->orderBy('id')->get();

        if ($colors->isEmpty()) {
            throw new \RuntimeException('ColorSeeder harus jalan sebelum ProductSeeder.');
        }

        foreach (self::PRODUCTS as $categorySlug => $items) {
            $category = Category::with('categoryType')->where('slug', $categorySlug)->firstOrFail();
            $sizes = $this->sizesFor($category);

            foreach ($items as $index => [$name, $basePrice]) {
                $product = Product::updateOrCreate(
                    ['slug' => Str::slug($name)],
                    [
                        'category_id' => $category->id,
                        'name' => $name,
                        'description' => $this->describe($name, $category->name),
                        'base_price' => $basePrice,
                        'is_active' => true,
                    ]
                );

                // Dua warna per produk, digeser per produk supaya katalognya ngk seragam.
                $productColors = [
                    $colors[($index * 2) % $colors->count()],
                    $colors[($index * 2 + 1) % $colors->count()],
                ];

                $this->seedVariants($product, $sizes, $productColors);
            }
        }
    }

    /**
     * Ukuran yang boleh dipakai produk di kategori ini. Kategori tanpa ukuran
     * (aksesoris) mengembalikan satu entri null: satu varian polos.
     *
     * @return Collection<int, Size|null>
     */
    private function sizesFor(Category $category): Collection
    {
        if (! $category->categoryType->has_sizes) {
            return new Collection([null]);
        }

        return Size::where('category_type_id', $category->category_type_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @param  Collection<int, Size|null>  $sizes
     * @param  array<int, Color>  $colors
     */
    private function seedVariants(Product $product, Collection $sizes, array $colors): void
    {
        foreach ($sizes as $size) {
            foreach ($colors as $color) {
                ProductVariant::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'size_id' => $size?->id,
                        'color_id' => $color->id,
                    ],
                    [
                        'sku' => sprintf('FM-%04d-%s-%02d', $product->id, $size?->code ?? 'OS', $color->id),
                        'price' => null, // ikut base_price produknya
                        'stock' => $this->stockFor($product, $size, $color),
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    /**
     * Stok semu yang tetap sama tiap kali seeder diulang, jadi hasil seeding
     * bisa dipakai jadi acuan di test. Ukuran ujung sengaja lebih tipis.
     */
    private function stockFor(Product $product, ?Size $size, Color $color): int
    {
        return (($product->id * 7 + ($size?->sort_order ?? 1) * 3 + $color->id) % 35) + 3;
    }

    private function describe(string $name, string $categoryName): string
    {
        return sprintf(
            '%s dari koleksi FitMate Archive. Potongan %s yang rapi, bahan pilihan, nyaman dipakai harian.',
            $name,
            Str::lower($categoryName)
        );
    }
}
