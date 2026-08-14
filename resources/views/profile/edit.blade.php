<x-layouts.app title="Profil Saya">
    <x-ui.page-header title="Profil Saya" />

    <div class="grid gap-6 lg:grid-cols-4">
        <aside class="lg:col-span-1">
            @include('profile.partials.nav')
        </aside>

        <div class="flex flex-col gap-6 lg:col-span-3">
            <x-ui.card heading="Data Akun">
                <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" class="flex max-w-md flex-col gap-4">
                    @csrf
                    @method('PATCH')

                    <x-form.field name="name" label="Nama Lengkap" required>
                        <x-ui.input name="name" :value="$user->name" />
                    </x-form.field>

                    <x-form.field name="email" label="Email" required hint="Mengganti email membuat verifikasinya harus diulang.">
                        <x-ui.input name="email" type="email" :value="$user->email" />
                    </x-form.field>

                    <x-form.field name="phone_number" label="Nomor HP">
                        <x-ui.input name="phone_number" type="tel" :value="$user->phone_number" />
                    </x-form.field>

                    <x-form.field name="gender" label="Jenis Kelamin">
                        <x-ui.select
                            name="gender"
                            placeholder="Pilih..."
                            :options="collect($genderOptions)->except('unisex')->all()"
                            :selected="$user->gender?->value"
                        />
                    </x-form.field>

                    <x-form.field name="profile_picture" label="Foto Profil" hint="JPG, PNG, atau WebP, maksimal 2 MB.">
                        <input type="file" name="profile_picture" accept="image/*" class="text-sm">
                    </x-form.field>

                    <x-ui.button type="submit" class="self-start">Simpan</x-ui.button>
                </form>
            </x-ui.card>

            <x-ui.card heading="Ganti Kata Sandi">
                <form action="{{ route('profile.password') }}" method="POST" class="flex max-w-md flex-col gap-4">
                    @csrf
                    @method('PUT')

                    <x-form.field name="current_password" label="Kata Sandi Saat Ini" required>
                        <x-ui.input name="current_password" type="password" autocomplete="current-password" />
                    </x-form.field>

                    <x-form.field name="password" label="Kata Sandi Baru" required hint="Minimal 8 karakter.">
                        <x-ui.input name="password" type="password" autocomplete="new-password" />
                    </x-form.field>

                    <x-form.field name="password_confirmation" label="Ulangi Kata Sandi Baru" required>
                        <x-ui.input name="password_confirmation" type="password" autocomplete="new-password" />
                    </x-form.field>

                    <x-ui.button type="submit" class="self-start">Ganti Kata Sandi</x-ui.button>
                </form>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
