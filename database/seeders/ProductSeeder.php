<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Katalog demo. Butuh CategorySeeder, SizeSeeder, ColorSeeder, dan StoreSeeder
     * jalan duluan.
     *
     * Varian sengaja dibentuk dari ukuran milik jenis kategori produknya sendiri,
     * bukan ukuran acak, supaya rekomendasi ukuran bisa langsung dicoba.
     *
     * Slug kategori => daftar [nama produk, harga jual, gender, harga coret].
     * Harga coret diisi hanya untuk produk yang sedang diskon (menu "On Sale").
     *
     * @var array<string, array<int, array{0: string, 1: int, 2: string, 3: int|null}>>
     */
    private const PRODUCTS = [
        'kaos' => [['Kaos Katun Basic', 129000, 'men', null], ['Kaos Oversized Archive', 159000, 'women', 199000]],
        'kemeja' => [['Kemeja Oxford Lengan Panjang', 259000, 'men', null], ['Kemeja Linen Santai', 289000, 'women', null]],
        'jaket' => [['Jaket Bomber Ringan', 449000, 'men', 559000], ['Jaket Denim Trucker', 529000, 'unisex', null]],
        'hoodie' => [['Hoodie Fleece Polos', 319000, 'unisex', null], ['Hoodie Zip-Up Archive', 379000, 'men', null]],
        'sweater' => [['Sweater Rajut Rib', 299000, 'women', 369000], ['Sweater Crewneck Basic', 269000, 'unisex', null]],
        'celana-jeans' => [['Celana Jeans Slim Fit', 399000, 'men', null], ['Celana Jeans Straight', 429000, 'women', null]],
        'celana-chino' => [['Celana Chino Tapered', 349000, 'men', 429000], ['Celana Chino Klasik', 329000, 'men', null]],
        'celana-pendek' => [['Celana Pendek Katun', 199000, 'women', null], ['Celana Pendek Cargo', 239000, 'men', null]],
        'rok' => [['Rok Plisket Midi', 289000, 'women', 349000], ['Rok A-Line Denim', 309000, 'women', null]],
        'sepatu-sneakers' => [['Sneakers Kanvas Low', 549000, 'unisex', 649000], ['Sneakers Runner Mesh', 699000, 'unisex', null]],
        'sepatu-formal' => [['Sepatu Derby Kulit', 899000, 'men', null], ['Sepatu Loafer Kulit', 849000, 'women', null]],
        'sandal' => [['Sandal Slide Karet', 189000, 'unisex', null], ['Sandal Gunung Strap', 259000, 'unisex', null]],
        'tas' => [['Tas Selempang Kanvas', 279000, 'unisex', 339000], ['Tas Ransel Daypack', 459000, 'unisex', null]],
        'topi' => [['Topi Baseball Katun', 149000, 'unisex', null], ['Topi Bucket Twill', 169000, 'unisex', null]],
        'ikat-pinggang' => [['Ikat Pinggang Kulit', 219000, 'men', null], ['Ikat Pinggang Anyam', 189000, 'women', 229000]],
    ];

    public function run(): void
    {
        $colors = Color::where('is_active', true)->orderBy('id')->get();

        if ($colors->isEmpty()) {
            throw new \RuntimeException('ColorSeeder harus jalan sebelum ProductSeeder.');
        }

        $stores = Store::where('status', 'active')->orderBy('id')->get();

        if ($stores->isEmpty()) {
            throw new \RuntimeException('StoreSeeder harus jalan sebelum ProductSeeder.');
        }

        $productNumber = 0;

        foreach (self::PRODUCTS as $categorySlug => $items) {
            $category = Category::with('categoryType')->where('slug', $categorySlug)->firstOrFail();
            $sizes = $this->sizesFor($category);

            foreach ($items as $index => [$name, $basePrice, $gender, $compareAtPrice]) {
                // Produk dibagi rata ke tiap toko supaya katalognya terlihat
                // sebagai marketplace, bukan satu toko besar.
                $store = $stores[$productNumber++ % $stores->count()];

                $product = Product::updateOrCreate(
                    ['slug' => Str::slug($name)],
                    [
                        'store_id' => $store->id,
                        'category_id' => $category->id,
                        'name' => $name,
                        'description' => $this->describe($name, $category->name),
                        'gender' => $gender,
                        'base_price' => $basePrice,
                        'compare_at_price' => $compareAtPrice,
                        'is_active' => true,
                    ]
                );

                // Dua warna per produk, digeser per produk supaya katalognya ngk seragam.
                $productColors = [
                    $colors[($index * 2) % $colors->count()],
                    $colors[($index * 2 + 1) % $colors->count()],
                ];

                $this->seedVariants($product, $sizes, $productColors);
                $this->seedImages($product);
            }
        }
    }

    /**
     * Foto demo diambil dari file `public/images/products/{slug}-{n}.webp`; foto
     * nomor 1 jadi gambar utama di katalog. Nambah foto cukup taruh file baru
     * dengan nomor berikutnya, lalu jalankan seeder lagi.
     */
    private function seedImages(Product $product): void
    {
        $pattern = '/^'.preg_quote($product->slug, '/').'-\d+\.webp$/';

        $paths = collect(glob(public_path('images/products/'.$product->slug.'-*.webp')))
            ->map(fn (string $file): string => basename($file))
            ->filter(fn (string $file): bool => preg_match($pattern, $file) === 1)
            ->sort(SORT_NATURAL)
            ->map(fn (string $file): string => 'images/products/'.$file)
            ->values();

        foreach ($paths as $index => $path) {
            ProductImage::updateOrCreate(
                ['product_id' => $product->id, 'sort_order' => $index],
                ['path' => $path, 'alt_text' => $product->name, 'is_primary' => $index === 0]
            );
        }

        // Foto yang filenya sudah dihapus ikut dibuang supaya seeder aman diulang.
        ProductImage::where('product_id', $product->id)
            ->where('sort_order', '>=', $paths->count())
            ->delete();
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
