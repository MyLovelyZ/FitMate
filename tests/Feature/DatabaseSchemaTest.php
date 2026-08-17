<?php

use App\Models\Category;
use App\Models\CategoryType;
use App\Models\Size;
use App\Models\SizeGuide;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserBodyProfile;
use Database\Seeders\CategoryTypeSeeder;
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
        ->and(SizeGuide::factory()->create())->toBeInstanceOf(SizeGuide::class);
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
