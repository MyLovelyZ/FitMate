<?php

use App\Models\Category;
use App\Models\CategoryType;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\SizeGuide;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserBodyProfile;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CategoryTypeSeeder;
use Database\Seeders\ColorSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\SizeSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates every model through its factory', function () {
    expect(User::factory()->create())->toBeInstanceOf(User::class)
        ->and(UserAddress::factory()->create())->toBeInstanceOf(UserAddress::class)
        ->and(UserBodyProfile::factory()->create())->toBeInstanceOf(UserBodyProfile::class)
        ->and(CategoryType::factory()->create())->toBeInstanceOf(CategoryType::class)
        ->and(Category::factory()->create())->toBeInstanceOf(Category::class)
        ->and(Size::factory()->create())->toBeInstanceOf(Size::class)
        ->and(SizeGuide::factory()->create())->toBeInstanceOf(SizeGuide::class)
        ->and(Color::factory()->create())->toBeInstanceOf(Color::class)
        ->and(Product::factory()->create())->toBeInstanceOf(Product::class)
        ->and(ProductVariant::factory()->create())->toBeInstanceOf(ProductVariant::class);
});

it('resolves relationships in both directions', function () {
    $user = User::factory()
        ->has(UserAddress::factory()->default(), 'addresses')
        ->has(UserBodyProfile::factory()->default(), 'bodyProfiles')
        ->create();

    expect($user->addresses)->toHaveCount(1)
        ->and($user->defaultAddress)->not->toBeNull()
        ->and($user->defaultBodyProfile)->not->toBeNull()
        ->and($user->addresses->first()->user->id)->toBe($user->id);

    $type = CategoryType::factory()
        ->has(Category::factory()->count(3), 'categories')
        ->create();

    $size = Size::factory()->for($type)->create();
    SizeGuide::factory()->for($size)->create();

    expect($type->categories)->toHaveCount(3)
        ->and($type->sizes)->toHaveCount(1)
        ->and($size->sizeGuides)->toHaveCount(1)
        ->and($size->categoryType->id)->toBe($type->id);
});

it('casts booleans and decimals instead of returning raw strings', function () {
    $type = CategoryType::factory()->withoutSizes()->create();
    $profile = UserBodyProfile::factory()->default()->create(['height' => 170.5]);

    expect($type->has_sizes)->toBeFalse()
        ->and($type->is_active)->toBeTrue()
        ->and($profile->is_default)->toBeTrue()
        ->and((float) $profile->height)->toBe(170.5);
});

it('blocks duplicate size labels within the same category type', function () {
    $type = CategoryType::factory()->create();
    Size::factory()->for($type)->create(['size_type' => 'top', 'name' => 'L']);

    Size::factory()->for($type)->create(['size_type' => 'top', 'name' => 'L']);
})->throws(QueryException::class);

it('cascades deletes from category type down to size guides', function () {
    $type = CategoryType::factory()->create();
    $size = Size::factory()->for($type)->create();
    SizeGuide::factory()->for($size)->create();

    $type->delete();

    expect(Size::count())->toBe(0)
        ->and(SizeGuide::count())->toBe(0);
});

it('seeds a usable catalogue', function () {
    $this->seed();

    expect(CategoryType::count())->toBe(4)
        ->and(Category::count())->toBe(15)
        ->and(Size::where('size_type', 'top')->count())->toBe(6)
        ->and(User::where('role', 'admin')->count())->toBe(1)
        ->and(UserAddress::count())->toBe(11);

    $xl = Size::where('size_type', 'top')->where('name', 'XL')->firstOrFail();

    expect($xl->sizeGuides->pluck('measurement_key')->all())
        ->toEqualCanonicalizing(['chest', 'length']);
});

it('matches a body profile against the size guide ranges', function () {
    $this->seed();

    $profile = UserBodyProfile::factory()->create(['chest' => 107]);

    $recommended = Size::where('size_type', 'top')
        ->whereHas('sizeGuides', fn ($query) => $query
            ->where('measurement_key', 'chest')
            ->where('min_value', '<=', $profile->chest)
            ->where('max_value', '>', $profile->chest))
        ->firstOrFail();

    expect($recommended->name)->toBe('XL');
});

it('re-runs seeders without duplicating reference data', function () {
    $this->seed(CategoryTypeSeeder::class);
    $this->seed(CategoryTypeSeeder::class);

    expect(CategoryType::count())->toBe(4);
});

it('resolves product relationships in both directions', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();
    $variant = ProductVariant::factory()->for($product)->create();

    expect($category->products)->toHaveCount(1)
        ->and($product->variants)->toHaveCount(1)
        ->and($product->category->id)->toBe($category->id)
        ->and($variant->product->id)->toBe($product->id)
        ->and($variant->size)->not->toBeNull()
        ->and($variant->color)->not->toBeNull();
});

it('blocks a duplicate size and colour combination on the same product', function () {
    $product = Product::factory()->create();
    $size = Size::factory()->create();
    $color = Color::factory()->create();
    $combination = ['size_id' => $size->id, 'color_id' => $color->id];

    ProductVariant::factory()->for($product)->create($combination);
    ProductVariant::factory()->for($product)->create($combination);
})->throws(QueryException::class);

it('allows the same size and colour combination across different products', function () {
    $size = Size::factory()->create();
    $color = Color::factory()->create();
    $combination = ['size_id' => $size->id, 'color_id' => $color->id];

    ProductVariant::factory()->create($combination);
    ProductVariant::factory()->create($combination);

    expect(ProductVariant::count())->toBe(2);
});

it('refuses to delete a size or colour that a variant still uses', function () {
    $size = Size::factory()->create();
    ProductVariant::factory()->create(['size_id' => $size->id]);

    $size->delete();
})->throws(QueryException::class);

it('refuses to delete a category that still has products', function () {
    $category = Category::factory()->create();
    Product::factory()->for($category)->create();

    $category->delete();
})->throws(QueryException::class);

it('keeps variants on soft delete and drops them on force delete', function () {
    $product = Product::factory()
        ->has(ProductVariant::factory()->count(3), 'variants')
        ->create();

    $product->delete();

    expect(Product::count())->toBe(0)
        ->and(Product::withTrashed()->count())->toBe(1)
        ->and(ProductVariant::count())->toBe(3);

    $product->forceDelete();

    expect(ProductVariant::count())->toBe(0);
});

it('falls back to the product base price when the variant has none', function () {
    $product = Product::factory()->create(['base_price' => 150000]);
    $inherited = ProductVariant::factory()->for($product)->create(['price' => null]);
    $overridden = ProductVariant::factory()->for($product)->create(['price' => 175000]);

    expect($inherited->effective_price)->toBe('150000.00')
        ->and($overridden->effective_price)->toBe('175000.00');
});

it('treats a variant as buyable only when active and in stock', function () {
    expect(ProductVariant::factory()->create()->isInStock())->toBeTrue()
        ->and(ProductVariant::factory()->outOfStock()->create()->isInStock())->toBeFalse()
        ->and(ProductVariant::factory()->inactive()->create()->isInStock())->toBeFalse();
});

it('allows an accessory variant without size or colour', function () {
    $variant = ProductVariant::factory()->withoutSizing()->create();

    expect($variant->size_id)->toBeNull()
        ->and($variant->color_id)->toBeNull()
        ->and($variant->isInStock())->toBeTrue();
});

it('seeds a catalogue whose variants use sizes from their own category type', function () {
    $this->seed();

    expect(Color::count())->toBe(8)
        ->and(Product::count())->toBe(30)
        ->and(Product::doesntHave('variants')->count())->toBe(0);

    $kaos = Product::where('slug', 'kaos-katun-basic')->firstOrFail();

    expect($kaos->variants->pluck('size.size_type')->unique()->all())->toBe(['top'])
        ->and($kaos->variants)->toHaveCount(12)
        ->and($kaos->variants->first()->effective_price)->toBe('129000.00');

    $topi = Product::where('slug', 'topi-baseball-katun')->firstOrFail();

    expect($topi->variants)->toHaveCount(2)
        ->and($topi->variants->pluck('size_id')->filter())->toBeEmpty();
});

it('re-runs the catalogue seeders without duplicating products or variants', function () {
    $this->seed(ColorSeeder::class);
    $this->seed(CategoryTypeSeeder::class);
    $this->seed(CategorySeeder::class);
    $this->seed(SizeSeeder::class);
    $this->seed(ProductSeeder::class);

    $products = Product::count();
    $variants = ProductVariant::count();

    $this->seed(ColorSeeder::class);
    $this->seed(ProductSeeder::class);

    expect(Product::count())->toBe($products)
        ->and(ProductVariant::count())->toBe($variants)
        ->and(Color::count())->toBe(8);
});
