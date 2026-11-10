<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Batas jumlah foto per produk di formulir seller.
     */
    public const MAX_IMAGES = 8;

    /**
     * Folder unggahan foto produk, relatif ke `public/`. File dipindah langsung
     * ke sini dengan nama berawalan `time()`, dan path yang sama disimpan di
     * `product_images.path` sehingga `ProductImage::url` (= `asset($path)`) jalan.
     */
    public const IMAGE_DIRECTORY = 'assets/images';

    /**
     * Unggahan lama sebelum pindah ke `assets/images` (disk `public`, awalan `storage/`).
     */
    public const LEGACY_IMAGE_DISK = 'public';

    protected $fillable = [
        'store_id',
        'category_id',
        'name',
        'slug',
        'description',
        'gender',
        'base_price',
        'compare_at_price',
        'is_active',
        'banned_at',
        'banned_by',
        'ban_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'is_active' => 'boolean',
            'banned_at' => 'datetime',
        ];
    }

    /**
     * Boleh dilihat dan dibeli pembeli: produknya aktif, tidak diblokir
     * superadmin, dan tokonya sedang berjualan (bukan pending, ditolak, atau
     * ditangguhkan).
     */
    public function isPurchasable(): bool
    {
        return $this->is_active && ! $this->isBanned() && (bool) $this->store?->canSell();
    }

    /**
     * Versi query dari `isPurchasable()` untuk katalog dan halaman toko.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function purchasable(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereNull('banned_at')
            ->whereHas('store', fn (Builder $store) => $store->selling());
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    /**
     * Superadmin menurunkan produk dari katalog. Terpisah dari `is_active` milik
     * seller, jadi seller tidak bisa menayangkannya lagi sendiri.
     */
    public function ban(User $by, string $reason): void
    {
        $this->update([
            'banned_at' => now(),
            'banned_by' => $by->id,
            'ban_reason' => $reason,
        ]);
    }

    public function unban(): void
    {
        $this->update([
            'banned_at' => null,
            'banned_by' => null,
            'ban_reason' => null,
        ]);
    }

    /**
     * Ringkasan ulasan untuk halaman produk: rata-rata bintang (1 desimal),
     * jumlah ulasan, jumlah per bintang, dan umpan balik ukuran terbanyak.
     *
     * @return array{average: float, count: int, stars: array<int, int>, fit: array<string, int>, topFit: ?string}
     */
    public function ratingSummary(): array
    {
        $reviews = $this->reviews()->get(['rating', 'fit_feedback']);
        $fit = $reviews->whereNotNull('fit_feedback')->countBy('fit_feedback')->sortDesc()->all();

        return [
            'average' => round((float) $reviews->avg('rating'), 1),
            'count' => $reviews->count(),
            'stars' => collect(range(5, 1))->mapWithKeys(fn (int $star): array => [$star => $reviews->where('rating', $star)->count()])->all(),
            'fit' => $fit,
            'topFit' => array_key_first($fit),
        ];
    }

    public function isOwnedBy(User $user): bool
    {
        return $user->store !== null && $this->store_id === $user->store->id;
    }

    /**
     * Slug unik dari nama produk. Produk yang sudah dihapus (soft delete) ikut
     * dicek karena kolom `slug` unique di tabel.
     */
    public static function uniqueSlugFor(string $name): string
    {
        $base = Str::slug($name) ?: 'produk';
        $slug = $base;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }

    /**
     * Menyamakan varian dengan baris di formulir seller: baris ber-`id` diubah,
     * baris tanpa `id` dibuat, varian yang tidak ada di formulir dihapus.
     * Riwayat pesanan aman karena `order_items` menyimpan salinan datanya.
     * SKU kosong diisi otomatis dengan pola yang sama dengan ProductSeeder.
     *
     * @param  array<int, array{id?: int|string|null, size_id?: int|string|null, color_id?: int|string|null, sku?: string|null, price?: int|string|null, stock: int|string}>  $rows
     */
    public function syncVariants(array $rows): void
    {
        DB::transaction(function () use ($rows): void {
            $keptIds = collect($rows)->pluck('id')->filter()->map(fn ($id): int => (int) $id)->all();

            $this->variants()->whereNotIn('id', $keptIds)->delete();

            foreach ($rows as $row) {
                $variant = filled($row['id'] ?? null)
                    ? $this->variants()->findOrFail((int) $row['id'])
                    : $this->variants()->make(['is_active' => true]);

                $variant->fill([
                    'size_id' => filled($row['size_id'] ?? null) ? (int) $row['size_id'] : null,
                    'color_id' => filled($row['color_id'] ?? null) ? (int) $row['color_id'] : null,
                    'sku' => filled($row['sku'] ?? null) ? $row['sku'] : null,
                    'price' => filled($row['price'] ?? null) ? $row['price'] : null,
                    'stock' => (int) $row['stock'],
                ])->save();

                if ($variant->sku === null) {
                    $variant->update(['sku' => sprintf(
                        'FM-%04d-%s-%02d',
                        $this->id,
                        $variant->size?->code ?? 'OS',
                        $variant->color_id ?? 0,
                    )]);
                }
            }
        });
    }

    /**
     * Memindahkan foto unggahan ke `public/assets/images/` dengan nama
     * `{time()}_{nama-file}_{acak}.{ext}` lalu mencatatnya di `product_images`,
     * diurutkan setelah foto yang sudah ada.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function addImages(array $files): void
    {
        $sortOrder = (int) ProductImage::where('product_id', $this->id)->max('sort_order');
        $directory = public_path(self::IMAGE_DIRECTORY);

        foreach ($files as $file) {
            $name = time().'_'
                .(Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'foto').'_'
                .Str::lower(Str::random(6)).'.'
                .($file->guessExtension() ?? $file->getClientOriginalExtension());

            $file->move($directory, $name);

            $this->images()->create([
                'path' => self::IMAGE_DIRECTORY.'/'.$name,
                'alt_text' => $this->name,
                'sort_order' => ++$sortOrder,
            ]);
        }

        $this->ensurePrimaryImage();
    }

    /**
     * Menghapus foto produk ini. File unggahan baru dihapus setelah transaksi
     * berhasil; foto demo di `public/images` tidak pernah dihapus.
     *
     * @param  array<int, int|string>  $imageIds
     */
    public function removeImages(array $imageIds): void
    {
        $images = ProductImage::where('product_id', $this->id)->whereKey($imageIds)->get();

        foreach ($images as $image) {
            $image->delete();

            if (str_starts_with($image->path, self::IMAGE_DIRECTORY.'/')) {
                DB::afterCommit(fn () => File::delete(public_path($image->path)));
            } elseif (str_starts_with($image->path, 'storage/')) {
                DB::afterCommit(fn () => Storage::disk(self::LEGACY_IMAGE_DISK)->delete(Str::after($image->path, 'storage/')));
            }
        }

        $this->ensurePrimaryImage();
    }

    public function setPrimaryImage(int $imageId): void
    {
        ProductImage::where('product_id', $this->id)->whereKey($imageId)->first()?->update(['is_primary' => true]);
    }

    /**
     * Pilihan formulir produk (seller & admin): kategori per jenis, peta
     * kategori → jenis/ukuran untuk Alpine, ukuran per jenis, dan warna.
     *
     * @return array{categoryTypes: Collection<int, CategoryType>, categoryMap: array<int, array{type: int, sized: bool}>, sizesByType: array<int, array<int, array{id: int, name: string}>>, colors: Collection<int, Color>}
     */
    public static function formOptions(): array
    {
        $categoryTypes = CategoryType::where('is_active', true)
            ->with(['categories' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get();

        return [
            'categoryTypes' => $categoryTypes,
            'categoryMap' => $categoryTypes
                ->flatMap(fn (CategoryType $type) => $type->categories->mapWithKeys(fn (Category $category): array => [
                    $category->id => ['type' => $type->id, 'sized' => (bool) $type->has_sizes],
                ]))
                ->all(),
            'sizesByType' => Size::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'category_type_id'])
                ->groupBy('category_type_id')
                ->map(fn ($sizes) => $sizes->map(fn (Size $size): array => ['id' => $size->id, 'name' => $size->name])->values()->all())
                ->all(),
            'colors' => Color::where('is_active', true)->orderBy('name')->get(),
        ];
    }

    /**
     * Varian produk ini dalam bentuk baris formulir.
     *
     * @return array<int, array{id: int, size_id: ?int, color_id: ?int, sku: ?string, price: ?int, stock: int}>
     */
    public function variantFormRows(): array
    {
        return $this->variants()->orderBy('id')->get()->map(fn (ProductVariant $variant): array => [
            'id' => $variant->id,
            'size_id' => $variant->size_id,
            'color_id' => $variant->color_id,
            'sku' => $variant->sku,
            'price' => $variant->price === null ? null : (int) $variant->price,
            'stock' => $variant->stock,
        ])->all();
    }

    /**
     * Kalau foto utama terhapus, foto berikutnya yang naik jadi utama.
     */
    public function ensurePrimaryImage(): void
    {
        if (ProductImage::where('product_id', $this->id)->where('is_primary', true)->exists()) {
            return;
        }

        ProductImage::where('product_id', $this->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first()
            ?->update(['is_primary' => true]);
    }

    /**
     * Diskon ditandai lewat harga coret yang lebih tinggi dari harga jual.
     */
    public function isOnSale(): bool
    {
        return $this->compare_at_price !== null
            && (float) $this->compare_at_price > (float) $this->base_price;
    }

    // relationships

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * Pembeli yang menyimpan produk ini ke wishlist.
     */
    public function wishlistedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'wishlists')->withTimestamps();
    }

    /**
     * Galeri foto, gambar utama selalu di depan.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Foto yang mewakili produk di daftar (keranjang, tabel seller).
     */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }
}
