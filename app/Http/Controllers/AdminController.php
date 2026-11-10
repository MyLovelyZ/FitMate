<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminProductRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use LogicException;

/**
 * Back-office transaksi: daftar semua pesanan masuk dari semua toko beserta
 * kartu statistiknya, dan tombol ubah status per pesanan. Halaman admin lain
 * tetap komponen Livewire `pages::admin-*`; semuanya memakai `layouts/admin`.
 */
class AdminController extends Controller
{
    /**
     * Status pesanan yang terhitung omzet: sudah dibayar dan tidak dikembalikan.
     *
     * @var array<int, string>
     */
    public const REVENUE_STATUSES = ['paid', 'processing', 'shipped', 'completed'];

    public function transactions(Request $request): View
    {
        $status = array_key_exists((string) $request->query('status'), Order::STATUS_LABELS) ? (string) $request->query('status') : '';
        $search = str((string) $request->query('q'))->squish()->limit(100, '')->toString();

        $orders = Order::query()
            ->with(['user', 'storeOrders.store'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            }))
            // Pesanan terbaru di baris paling atas.
            ->latest()
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.transactions', [
            'orders' => $orders,
            'status' => $status,
            'search' => $search,
            'stats' => [
                'Total Produk' => number_format(Product::count(), 0, ',', '.'),
                'Total Pesanan Masuk' => number_format(Order::count(), 0, ',', '.'),
                'Menunggu Pembayaran' => number_format(Order::where('status', 'pending_payment')->count(), 0, ',', '.'),
                'Total Omzet Penjualan' => 'Rp '.number_format((float) Order::whereIn('status', self::REVENUE_STATUSES)->sum('grand_total'), 0, ',', '.'),
                'Komisi FitMate' => 'Rp '.number_format((float) StoreOrder::where('status', 'completed')->sum('platform_fee_amount'), 0, ',', '.'),
            ],
        ]);
    }

    /**
     * Form "Tambah Produk" admin (view `admin/create_product`): admin mengunggah
     * produk atas nama salah satu toko.
     */
    public function createProduct(): View
    {
        return view('admin.create_product', [
            'options' => Product::formOptions(),
            'stores' => Store::whereIn('status', ['active', 'suspended'])->orderBy('name')->get(),
        ]);
    }

    /**
     * Menyimpan produk dari form multipart admin. `AdminProductRequest` sudah
     * memvalidasi semua isian (termasuk tipe file JPG/PNG/WebP, maks. 2 MB)
     * sebelum method ini jalan; fotonya dipindah ke `public/assets/images/`
     * dengan awalan `time()` oleh `Product::addImages()`.
     */
    public function storeProduct(AdminProductRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $product = DB::transaction(function () use ($request, $validated): Product {
            $product = Store::findOrFail($validated['store_id'])->products()->create([
                ...$request->productAttributes(),
                'slug' => Product::uniqueSlugFor($validated['name']),
            ]);

            $product->syncVariants($validated['variants']);
            $product->addImages($request->file('images', []));

            return $product;
        });

        return redirect()
            ->route('admin.products.index')
            ->with('notify', "Produk \"{$product->name}\" berhasil ditambahkan.");
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(Order::ADMIN_ACTIONS))],
            'tracking_number' => ['nullable', 'required_if:status,shipped', 'string', 'max:64'],
            'reason' => ['nullable', 'required_if:status,cancelled', 'string', 'max:255'],
        ], [
            'status.required' => 'Pilih status tujuan.',
            'status.in' => 'Status tujuan tidak dikenal.',
            'tracking_number.required_if' => 'Isi nomor resi untuk menandai pesanan dikirim.',
            'tracking_number.max' => 'Nomor resi maksimal 64 karakter.',
            'reason.required_if' => 'Isi alasan pembatalan.',
            'reason.max' => 'Alasan pembatalan maksimal 255 karakter.',
        ]);

        try {
            $order->applyAdminStatus(
                $validated['status'],
                $request->user(),
                isset($validated['tracking_number']) ? trim($validated['tracking_number']) : null,
                isset($validated['reason']) ? trim($validated['reason']) : null,
            );
        } catch (LogicException $exception) {
            return back()->with('failure', $exception->getMessage());
        }

        return back()->with('success', "Status pesanan {$order->order_number} sekarang: ".Order::STATUS_LABELS[$order->status].'.');
    }
}
