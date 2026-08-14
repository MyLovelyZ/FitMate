<?php

namespace Database\Seeders;

use App\Enums\BannerPlacement;
use App\Enums\CouponType;
use App\Enums\Gender;
use App\Enums\ProductStatus;
use App\Enums\SizeType;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\BannerPromo;
use App\Models\BodyMeasurement;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\SizeChart;
use App\Models\Store;
use App\Models\User;
use App\Models\UserBodyProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Data contoh untuk pengembangan dan demo — JANGAN dijalankan di produksi.
 *
 * Isinya akun siap pakai (pembeli, penjual, admin), satu toko aktif berisi
 * produk dari tiga jenis ukuran, plus satu produk aksesoris tanpa ukuran supaya
 * jalur "produk tanpa perhitungan" ikut terlihat saat demo.
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $buyer = $this->createUser('Pembeli Contoh', 'pembeli@fitmate.test', UserRole::User, Gender::Male);
        $seller = $this->createUser('Penjual Contoh', 'penjual@fitmate.test', UserRole::Seller, Gender::Female);
        $this->createUser('Admin FitMate', 'admin@fitmate.test', UserRole::Admin, null);

        $this->createBodyProfile($buyer);

        $store = Store::updateOrCreate(
            ['slug' => 'toko-contoh'],
            [
                'user_id' => $seller->id,
                'name' => 'Toko Contoh',
                'description' => 'Toko demo untuk mencoba alur FitMate dari katalog sampai checkout.',
                'phone_number' => '081200000000',
                'email' => 'halo@tokocontoh.test',
                'street' => 'Jl. Merdeka No. 10',
                'city' => 'Bandung',
                'state' => 'Jawa Barat',
                'postal_code' => '40111',
                'status' => StoreStatus::Active,
                'verified_at' => now(),
            ],
        );

        $brand = Brand::firstWhere('slug', 'nusantara-wear') ?? Brand::factory()->create();

        $catalog = [
            ['category' => 'kaos', 'chart' => 'top-male', 'name' => 'Kaos Katun Combed Pria', 'price' => 89000, 'gender' => Gender::Male],
            ['category' => 'kemeja', 'chart' => 'top-female', 'name' => 'Kemeja Linen Wanita', 'price' => 189000, 'gender' => Gender::Female],
            ['category' => 'celana-panjang', 'chart' => 'bottom-male', 'name' => 'Celana Chino Pria', 'price' => 249000, 'gender' => Gender::Male],
            ['category' => 'sepatu', 'chart' => 'footwear', 'name' => 'Sepatu Sneakers Harian', 'price' => 399000, 'gender' => Gender::Unisex],
            ['category' => 'topi', 'chart' => null, 'name' => 'Topi Baseball Polos', 'price' => 79000, 'gender' => Gender::Unisex],
        ];

        foreach ($catalog as $item) {
            $this->createProduct($store, $brand, $item);
        }

        $this->createCoupon();
        $this->createBanner();
    }

    private function createUser(string $name, string $email, UserRole $role, ?Gender $gender): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'phone_number' => '081200000000',
                'gender' => $gender,
                'role' => $role,
                'email_verified_at' => now(),
            ],
        );
    }

    /**
     * Profil badan yang angkanya sengaja jatuh di ukuran L pada standar pria,
     * supaya rekomendasi ukuran langsung terlihat hasilnya saat demo.
     */
    private function createBodyProfile(User $user): void
    {
        $profile = UserBodyProfile::updateOrCreate(
            ['user_id' => $user->id, 'profile_name' => 'Profil Saya'],
            ['gender' => Gender::Male, 'is_default' => true],
        );

        $values = [
            'tinggi_badan' => 172,
            'berat_badan' => 68,
            'lingkar_dada' => 99,
            'lebar_bahu' => 46,
            'panjang_badan' => 71,
            'lingkar_pinggang' => 83,
            'lingkar_pinggul' => 100,
            'panjang_kaki' => 103,
            'panjang_telapak_kaki' => 26.2,
            'lebar_telapak_kaki' => 9.6,
        ];

        foreach ($values as $key => $value) {
            $measurementId = BodyMeasurement::where('key', $key)->value('id');

            if ($measurementId === null) {
                continue;
            }

            $profile->measurements()->updateOrCreate(
                ['body_measurement_id' => $measurementId],
                ['value' => $value],
            );
        }
    }

    /**
     * @param  array{category: string, chart: string|null, name: string, price: int, gender: Gender}  $item
     */
    private function createProduct(Store $store, Brand $brand, array $item): void
    {
        $category = Category::firstWhere('slug', $item['category']);

        if (! $category instanceof Category) {
            return;
        }

        $chart = $this->resolveChart($item['chart']);

        $product = Product::updateOrCreate(
            ['slug' => Str::slug($item['name'])],
            [
                'store_id' => $store->id,
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'size_chart_id' => $chart?->id,
                'name' => $item['name'],
                'description' => 'Produk contoh untuk demo FitMate. '.$item['name'].' dijual dengan ukuran mengacu standar FitMate.',
                'target_gender' => $item['gender'],
                'base_price' => $item['price'],
                'weight_gram' => $category->size_type === SizeType::Footwear ? 900 : 350,
                'status' => ProductStatus::Active,
                'is_featured' => true,
                'published_at' => now(),
            ],
        );

        $product->images()->updateOrCreate(
            ['path' => 'products/'.$product->slug.'.jpg'],
            ['alt_text' => $product->name, 'sort_order' => 1, 'is_primary' => true],
        );

        $this->createVariants($product, $chart, $item['price']);
    }

    private function resolveChart(?string $key): ?SizeChart
    {
        return match ($key) {
            'top-male' => SizeChart::firstWhere(['size_type' => SizeType::Top, 'gender' => Gender::Male]),
            'top-female' => SizeChart::firstWhere(['size_type' => SizeType::Top, 'gender' => Gender::Female]),
            'bottom-male' => SizeChart::firstWhere(['size_type' => SizeType::Bottom, 'gender' => Gender::Male]),
            'bottom-female' => SizeChart::firstWhere(['size_type' => SizeType::Bottom, 'gender' => Gender::Female]),
            'footwear' => SizeChart::firstWhere(['size_type' => SizeType::Footwear, 'gender' => Gender::Unisex]),
            default => null,
        };
    }

    /**
     * Varian dibuat dari entry chart produknya sendiri — inilah rantai yang
     * membuat "L" bernilai sama di seluruh toko (lihat BE-034).
     */
    private function createVariants(Product $product, ?SizeChart $chart, int $price): void
    {
        $colors = [['Hitam', '#111827'], ['Putih', '#F9FAFB']];

        if (! $chart instanceof SizeChart) {
            foreach ($colors as [$colorName, $colorHex]) {
                $this->upsertVariant($product, null, $colorName, $colorHex, $price);
            }

            return;
        }

        foreach ($chart->entries as $entry) {
            foreach ($colors as [$colorName, $colorHex]) {
                $this->upsertVariant($product, $entry->id, $colorName, $colorHex, $price);
            }
        }
    }

    private function upsertVariant(Product $product, ?int $entryId, string $colorName, string $colorHex, int $price): void
    {
        $product->variants()->updateOrCreate(
            ['size_chart_entry_id' => $entryId, 'color_name' => $colorName],
            [
                'sku' => Str::upper(Str::slug($product->slug.'-'.($entryId ?? 'std').'-'.$colorName)),
                'color_hex' => $colorHex,
                'price' => $price,
                'stock' => 25,
                'is_active' => true,
            ],
        );
    }

    private function createCoupon(): void
    {
        Coupon::updateOrCreate(
            ['code' => 'FITMATE10'],
            [
                'store_id' => null,
                'name' => 'Diskon Perkenalan 10%',
                'description' => 'Potongan 10% untuk pembelian pertama, maksimal Rp50.000.',
                'type' => CouponType::Percentage,
                'value' => 10,
                'min_purchase' => 100000,
                'max_discount' => 50000,
                'usage_limit' => 1000,
                'starts_at' => now()->subDay(),
                'expiry_date' => now()->addMonths(3)->toDateString(),
                'is_active' => true,
            ],
        );
    }

    private function createBanner(): void
    {
        BannerPromo::updateOrCreate(
            ['title' => 'Ukuran yang benar-benar pas'],
            [
                'subtitle' => 'Isi ukuran badanmu sekali, berlaku di semua toko.',
                'image' => 'banners/hero.jpg',
                'link_url' => '/products',
                'placement' => BannerPlacement::HomeHero,
                'sort_order' => 1,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addYear(),
                'is_active' => true,
            ],
        );
    }
}
