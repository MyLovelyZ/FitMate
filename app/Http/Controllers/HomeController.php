<?php

namespace App\Http\Controllers;

use App\Enums\BannerPlacement;
use App\Models\BannerPromo;
use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', [
            'banners' => BannerPromo::query()->activeNow(BannerPlacement::HomeHero)->get(),
            'categories' => Category::query()->active()->roots()->with('children')->get(),
            'latestProducts' => Product::query()
                ->published()
                ->with(['store:id,name', 'primaryImage'])
                ->latest('published_at')
                ->take(8)
                ->get(),
        ]);
    }
}
