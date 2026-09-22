<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Checkout')] class extends Component
{
    public string $email = '';

    public string $firstName = '';

    public string $lastName = '';

    public string $address = '';

    public string $city = '';

    public string $postalCode = '';

    public string $country = 'United States';

    public string $shippingMethod = 'standard';

    public string $paymentMethod = 'card';

    public string $cardNumber = '';

    public string $cardName = '';

    public string $cardExpiry = '';

    public string $cardCvv = '';

    /**
     * Isi keranjang masih statis mengikuti desain — belum terhubung ke model Cart.
     *
     * Nama class placeholder gambar ditulis utuh supaya Tailwind bisa memindainya.
     *
     * @return array<int, array{name: string, variant: string, price: float, art: string}>
     */
    #[Computed]
    public function items(): array
    {
        return [
            [
                'name' => 'Oversized Structured Blazer',
                'variant' => 'Black / Medium / Qty 1',
                'price' => 450.00,
                'art' => 'bg-[linear-gradient(150deg,#cbc4b5,#948d7c)]',
            ],
            [
                'name' => 'Wide-Leg Utility Trousers',
                'variant' => 'Black / Medium / Qty 1',
                'price' => 425.00,
                'art' => 'bg-[linear-gradient(150deg,#d9d2c1,#b3ab98)]',
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, eta: string, price: float, days: array{int, int}}>
     */
    #[Computed]
    public function shippingOptions(): array
    {
        return [
            'standard' => [
                'label' => 'Standard Shipping',
                'eta' => '5–7 business days',
                'price' => 25.00,
                'days' => [5, 7],
            ],
            'express' => [
                'label' => 'Express Shipping',
                'eta' => '1–2 business days',
                'price' => 50.00,
                'days' => [1, 2],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function paymentTabs(): array
    {
        return [
            'card' => 'Credit Card',
            'transfer' => 'Bank Transfer',
            'wallet' => 'E-Wallet',
        ];
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function countries(): array
    {
        return ['United States', 'Indonesia', 'Singapore', 'Malaysia'];
    }

    #[Computed]
    public function subtotal(): float
    {
        return array_sum(array_column($this->items, 'price'));
    }

    #[Computed]
    public function shippingCost(): float
    {
        return $this->selectedShipping()['price'];
    }

    #[Computed]
    public function total(): float
    {
        return $this->subtotal + $this->shippingCost;
    }

    /**
     * Pembayaran belum diproses — ringkasan pesanan dikirim ke halaman sukses lewat flash session.
     */
    public function placeOrder(): void
    {
        $this->validate();

        session()->flash('checkout.order', [
            'id' => 'FM-'.Carbon::now()->format('Y').'-'.Str::upper(Str::random(4)),
            'placed_at' => Carbon::now()->format('M j, Y'),
            'total' => $this->total,
            'email' => $this->email,
            'delivery_window' => $this->deliveryWindow(),
        ]);

        $this->redirectRoute('checkout.success', navigate: true);
    }

    /**
     * Kolom kartu hanya wajib saat tab pembayaran kartu yang aktif.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $rules = [
            'email' => ['required', 'email'],
            'firstName' => ['required', 'string', 'max:60'],
            'lastName' => ['required', 'string', 'max:60'],
            'address' => ['required', 'string', 'max:160'],
            'city' => ['required', 'string', 'max:60'],
            'postalCode' => ['required', 'string', 'max:12'],
            'country' => ['required', Rule::in($this->countries)],
            'shippingMethod' => ['required', Rule::in(array_keys($this->shippingOptions))],
            'paymentMethod' => ['required', Rule::in(array_keys($this->paymentTabs))],
        ];

        if ($this->paymentMethod === 'card') {
            $rules['cardNumber'] = ['required', 'string', 'regex:/^[0-9 ]{13,19}$/'];
            $rules['cardName'] = ['required', 'string', 'max:60'];
            $rules['cardExpiry'] = ['required', 'regex:#^(0[1-9]|1[0-2])/[0-9]{2}$#'];
            $rules['cardCvv'] = ['required', 'digits_between:3,4'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'firstName' => 'first name',
            'lastName' => 'last name',
            'postalCode' => 'postal code',
            'shippingMethod' => 'shipping method',
            'paymentMethod' => 'payment method',
            'cardNumber' => 'card number',
            'cardName' => 'name on card',
            'cardExpiry' => 'expiration date',
            'cardCvv' => 'CVV',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'cardNumber.regex' => 'The card number must be 13 to 19 digits.',
            'cardExpiry.regex' => 'The expiration date must use the MM/YY format.',
        ];
    }

    /**
     * @return array{label: string, eta: string, price: float, days: array{int, int}}
     */
    protected function selectedShipping(): array
    {
        return $this->shippingOptions[$this->shippingMethod] ?? $this->shippingOptions['standard'];
    }

    /**
     * Perkiraan rentang tanggal kirim, contoh "September 27–29, 2026".
     */
    protected function deliveryWindow(): string
    {
        [$from, $to] = $this->selectedShipping()['days'];

        $start = Carbon::now()->addDays($from);
        $end = Carbon::now()->addDays($to);

        return $start->isSameMonth($end)
            ? $start->format('F j').'–'.$end->format('j, Y')
            : $start->format('F j').' – '.$end->format('F j, Y');
    }
};
?>

<div class="mx-auto max-w-[1120px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(20px,4vw,32px)]">
    <a
        href="{{ route('home') }}"
        wire:navigate
        class="inline-flex items-center gap-2 text-[12.5px] text-muted transition-colors hover:text-ink"
    >
        <span aria-hidden="true">&larr;</span> Back
    </a>

    <div class="mt-[clamp(24px,4vw,32px)] grid grid-cols-1 gap-[clamp(28px,5vw,55px)] lg:grid-cols-[minmax(0,1fr)_300px]">
        {{-- Kolom form --}}
        <section class="order-last lg:order-none">
            <div class="mb-[clamp(20px,3vw,26px)]">
                <h1 class="font-display text-[clamp(1.6rem,3.4vw,2.1rem)] font-normal leading-tight">Checkout</h1>
                <p class="mt-1 text-[13px] text-muted">Complete your order.</p>
            </div>

            <form wire:submit="placeOrder" novalidate>
                {{-- 1. Shipping Information --}}
                <section class="mb-8">
                    <h2 class="mb-3.5 flex items-center gap-2 border-b border-line pb-3 text-[13px] font-semibold uppercase tracking-[0.08em]">
                        <span class="inline-block size-2 border border-ink bg-ink/20" aria-hidden="true"></span>
                        1. Shipping Information
                    </h2>

                    <div>
                        <label for="email" class="mb-1.5 block text-[10.5px] font-semibold uppercase tracking-[0.1em] text-muted">
                            Email Address
                        </label>
                        <input
                            id="email"
                            type="email"
                            autocomplete="email"
                            placeholder="you@example.com"
                            wire:model.blur="email"
                            @class([
                                'h-10 w-full rounded-[2px] border bg-surface px-3 text-[13px] outline-none transition-colors focus:border-ink',
                                'border-sale' => $errors->has('email'),
                                'border-line' => ! $errors->has('email'),
                            ])
                        >
                        @error('email')
                            <p class="mt-1 text-[11.5px] text-sale">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mt-3.5 grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                        <div>
                            <label for="firstName" class="mb-1.5 block text-[10.5px] font-semibold uppercase tracking-[0.1em] text-muted">
                                First Name
                            </label>
                            <input
                                id="firstName"
                                type="text"
                                autocomplete="given-name"
                                placeholder="John"
                                wire:model.blur="firstName"
                                @class([
                                    'h-10 w-full rounded-[2px] border bg-surface px-3 text-[13px] outline-none transition-colors focus:border-ink',
                                    'border-sale' => $errors->has('firstName'),
                                    'border-line' => ! $errors->has('firstName'),
                                ])
                            >
                            @error('firstName')
                                <p class="mt-1 text-[11.5px] text-sale">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="lastName" class="mb-1.5 block text-[10.5px] font-semibold uppercase tracking-[0.1em] text-muted">
                                Last Name
                            </label>
                            <input
                                id="lastName"
                                type="text"
                                autocomplete="family-name"
                                placeholder="Doe"
                                wire:model.blur="lastName"
                                @class([
                                    'h-10 w-full rounded-[2px] border bg-surface px-3 text-[13px] outline-none transition-colors focus:border-ink',
                                    'border-sale' => $errors->has('lastName'),
                                    'border-line' => ! $errors->has('lastName'),
                                ])
                            >
                            @error('lastName')
                                <p class="mt-1 text-[11.5px] text-sale">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-3.5">
                        <label for="address" class="mb-1.5 block text-[10.5px] font-semibold uppercase tracking-[0.1em] text-muted">
                            Address
                        </label>
                        <input
                            id="address"
                            type="text"
                            autocomplete="street-address"
                            placeholder="123 Main St, Apt 4B"
                            wire:model.blur="address"
                            @class([
                                'h-10 w-full rounded-[2px] border bg-surface px-3 text-[13px] outline-none transition-colors focus:border-ink',
                                'border-sale' => $errors->has('address'),
                                'border-line' => ! $errors->has('address'),
                            ])
                        >
                        @error('address')
                            <p class="mt-1 text-[11.5px] text-sale">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mt-3.5 grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                        <div>
                            <label for="city" class="mb-1.5 block text-[10.5px] font-semibold uppercase tracking-[0.1em] text-muted">
                                City
                            </label>
                            <input
                                id="city"
                                type="text"
                                autocomplete="address-level2"
                                placeholder="New York"
                                wire:model.blur="city"
                                @class([
                                    'h-10 w-full rounded-[2px] border bg-surface px-3 text-[13px] outline-none transition-colors focus:border-ink',
                                    'border-sale' => $errors->has('city'),
                                    'border-line' => ! $errors->has('city'),
                                ])
                            >
                            @error('city')
                                <p class="mt-1 text-[11.5px] text-sale">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="postalCode" class="mb-1.5 block text-[10.5px] font-semibold uppercase tracking-[0.1em] text-muted">
                                Postal Code
                            </label>
                            <input
                                id="postalCode"
                                type="text"
                                autocomplete="postal-code"
                                placeholder="10001"
                                wire:model.blur="postalCode"
                                @class([
                                    'h-10 w-full rounded-[2px] border bg-surface px-3 text-[13px] outline-none transition-colors focus:border-ink',
                                    'border-sale' => $errors->has('postalCode'),
                                    'border-line' => ! $errors->has('postalCode'),
                                ])
                            >
                            @error('postalCode')
                                <p class="mt-1 text-[11.5px] text-sale">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-3.5">
                        <label for="country" class="mb-1.5 block text-[10.5px] font-semibold uppercase tracking-[0.1em] text-muted">
                            Country
                        </label>
                        <select
                            id="country"
                            autocomplete="country-name"
                            wire:model="country"
                            @class([
                                'h-10 w-full rounded-[2px] border bg-surface px-3 text-[13px] outline-none transition-colors focus:border-ink',
                                'border-sale' => $errors->has('country'),
                                'border-line' => ! $errors->has('country'),
                            ])
                        >
                            @foreach ($this->countries as $countryOption)
                                <option value="{{ $countryOption }}">{{ $countryOption }}</option>
                            @endforeach
                        </select>
                        @error('country')
                            <p class="mt-1 text-[11.5px] text-sale">{{ $message }}</p>
                        @enderror
                    </div>
                </section>

                {{-- 2. Shipping Method --}}
                <section class="mb-8">
                    <h2 class="mb-3.5 flex items-center gap-2 border-b border-line pb-3 text-[13px] font-semibold uppercase tracking-[0.08em]">
                        <span class="inline-block size-2 border border-ink bg-ink/20" aria-hidden="true"></span>
                        2. Shipping Method
                    </h2>

                    <div class="flex flex-col gap-2.5">
                        @foreach ($this->shippingOptions as $key => $option)
                            <label
                                @class([
                                    'grid cursor-pointer grid-cols-[18px_1fr_auto] items-start gap-3 rounded-[3px] border p-3.5 transition-colors',
                                    'border-ink bg-cream' => $shippingMethod === $key,
                                    'border-line hover:border-muted' => $shippingMethod !== $key,
                                ])
                            >
                                <input
                                    type="radio"
                                    name="shippingMethod"
                                    value="{{ $key }}"
                                    wire:model.live="shippingMethod"
                                    class="mt-0.5 size-4 accent-ink"
                                >

                                <span>
                                    <span class="block text-[13px] font-semibold">{{ $option['label'] }}</span>
                                    <span class="mt-0.5 block text-[11.5px] text-muted">{{ $option['eta'] }}</span>
                                </span>

                                <span class="text-[12.5px] font-semibold">${{ number_format($option['price'], 2) }}</span>
                            </label>
                        @endforeach
                    </div>

                    @error('shippingMethod')
                        <p class="mt-1 text-[11.5px] text-sale">{{ $message }}</p>
                    @enderror
                </section>

                {{-- 3. Payment --}}
                <section class="mb-8">
                    <h2 class="mb-3.5 flex items-center gap-2 border-b border-line pb-3 text-[13px] font-semibold uppercase tracking-[0.08em]">
                        <span class="inline-block size-2 border border-ink bg-ink/20" aria-hidden="true"></span>
                        3. Payment
                    </h2>

                    <div class="grid grid-cols-3 border-b border-line">
                        @foreach ($this->paymentTabs as $key => $label)
                            <button
                                type="button"
                                aria-pressed="{{ $paymentMethod === $key ? 'true' : 'false' }}"
                                wire:click="$set('paymentMethod', '{{ $key }}')"
                                @class([
                                    'cursor-pointer border-b-2 px-1 py-2.5 text-[10.5px] font-bold uppercase tracking-[0.08em] transition-colors',
                                    'border-ink text-ink' => $paymentMethod === $key,
                                    'border-transparent text-muted hover:text-ink' => $paymentMethod !== $key,
                                ])
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    <div class="mt-4">
                        @if ($paymentMethod === 'card')
                            <div>
                                <label for="cardNumber" class="mb-1.5 block text-[10.5px] font-semibold uppercase tracking-[0.1em] text-muted">
                                    Card Number
                                </label>
                                <input
                                    id="cardNumber"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="cc-number"
                                    maxlength="19"
                                    placeholder="0000 0000 0000 0000"
                                    wire:model.blur="cardNumber"
                                    @class([
                                        'h-10 w-full rounded-[2px] border bg-surface px-3 text-[13px] outline-none transition-colors focus:border-ink',
                                        'border-sale' => $errors->has('cardNumber'),
                                        'border-line' => ! $errors->has('cardNumber'),
                                    ])
                                >
                                @error('cardNumber')
                                    <p class="mt-1 text-[11.5px] text-sale">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mt-3.5">
                                <label for="cardName" class="mb-1.5 block text-[10.5px] font-semibold uppercase tracking-[0.1em] text-muted">
                                    Name on Card
                                </label>
                                <input
                                    id="cardName"
                                    type="text"
                                    autocomplete="cc-name"
                                    placeholder="JOHN DOE"
                                    wire:model.blur="cardName"
                                    @class([
                                        'h-10 w-full rounded-[2px] border bg-surface px-3 text-[13px] uppercase outline-none transition-colors focus:border-ink',
                                        'border-sale' => $errors->has('cardName'),
                                        'border-line' => ! $errors->has('cardName'),
                                    ])
                                >
                                @error('cardName')
                                    <p class="mt-1 text-[11.5px] text-sale">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mt-3.5 grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                                <div>
                                    <label for="cardExpiry" class="mb-1.5 block text-[10.5px] font-semibold uppercase tracking-[0.1em] text-muted">
                                        Expiration Date
                                    </label>
                                    <input
                                        id="cardExpiry"
                                        type="text"
                                        inputmode="numeric"
                                        autocomplete="cc-exp"
                                        maxlength="5"
                                        placeholder="MM/YY"
                                        wire:model.blur="cardExpiry"
                                        @class([
                                            'h-10 w-full rounded-[2px] border bg-surface px-3 text-[13px] outline-none transition-colors focus:border-ink',
                                            'border-sale' => $errors->has('cardExpiry'),
                                            'border-line' => ! $errors->has('cardExpiry'),
                                        ])
                                    >
                                    @error('cardExpiry')
                                        <p class="mt-1 text-[11.5px] text-sale">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="cardCvv" class="mb-1.5 block text-[10.5px] font-semibold uppercase tracking-[0.1em] text-muted">
                                        CVV
                                    </label>
                                    <input
                                        id="cardCvv"
                                        type="password"
                                        inputmode="numeric"
                                        autocomplete="cc-csc"
                                        maxlength="4"
                                        placeholder="123"
                                        wire:model.blur="cardCvv"
                                        @class([
                                            'h-10 w-full rounded-[2px] border bg-surface px-3 text-[13px] outline-none transition-colors focus:border-ink',
                                            'border-sale' => $errors->has('cardCvv'),
                                            'border-line' => ! $errors->has('cardCvv'),
                                        ])
                                    >
                                    @error('cardCvv')
                                        <p class="mt-1 text-[11.5px] text-sale">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        @elseif ($paymentMethod === 'transfer')
                            <p class="rounded-[2px] bg-cream px-4 py-3.5 text-[12.5px] leading-relaxed text-muted">
                                Bank transfer instructions will appear after you place the order.
                            </p>
                        @else
                            <p class="rounded-[2px] bg-cream px-4 py-3.5 text-[12.5px] leading-relaxed text-muted">
                                You will be redirected to the selected e-wallet after placing the order.
                            </p>
                        @endif
                    </div>

                    <button
                        type="submit"
                        class="mt-5 h-11 w-full cursor-pointer rounded-[2px] bg-ink text-[11.5px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity duration-200 hover:opacity-85 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="placeOrder">
                            Place Order &middot; ${{ number_format($this->total, 2) }}
                        </span>
                        <span wire:loading wire:target="placeOrder">Processing…</span>
                    </button>

                    <p class="mt-2.5 flex items-center justify-center gap-2 text-[11px] text-muted">
                        <span class="inline-block size-2 border border-muted" aria-hidden="true"></span>
                        Secure &amp; Encrypted Checkout
                    </p>
                </section>
            </form>
        </section>

        {{-- Ringkasan pesanan --}}
        <aside class="order-first h-fit rounded-md border border-line bg-surface p-[17px] lg:sticky lg:top-header lg:order-none">
            <h2 class="mb-3.5 border-b border-line pb-3 text-[13px] font-semibold uppercase tracking-[0.08em]">
                Order Summary
            </h2>

            @foreach ($this->items as $item)
                <div class="mb-3.5 flex gap-3">
                    <div class="h-[70px] w-[55px] shrink-0 rounded-[2px] border border-line {{ $item['art'] }}" aria-hidden="true"></div>

                    <div class="min-w-0">
                        <p class="text-[12px] font-semibold leading-snug">{{ $item['name'] }}</p>
                        <p class="mt-1 text-[11px] text-muted">{{ $item['variant'] }}</p>
                        <p class="mt-1.5 text-[12px] font-semibold">${{ number_format($item['price'], 2) }}</p>
                    </div>
                </div>
            @endforeach

            <div class="mt-2.5 flex justify-between border-t border-line pt-2.5 text-[12px]">
                <span class="text-muted">Subtotal</span>
                <span class="font-medium">${{ number_format($this->subtotal, 2) }}</span>
            </div>

            <div class="mt-2.5 flex justify-between border-t border-line pt-2.5 text-[12px]">
                <span class="text-muted">Shipping</span>
                <span class="font-medium">${{ number_format($this->shippingCost, 2) }}</span>
            </div>

            <div class="mt-2.5 flex items-baseline justify-between border-t border-line pt-3 text-[15px] font-bold">
                <span>Total</span>
                <span>${{ number_format($this->total, 2) }}</span>
            </div>
        </aside>
    </div>
</div>
