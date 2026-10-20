{{--
    Foto profil user, atau inisial namanya kalau belum mengunggah foto.
    Ukuran diatur pemanggil lewat class, mis. `<x-ui.avatar :user="$user" class="size-10" />`.
--}}
@props(['user'])

@if ($url = $user->profilePictureUrl())
    <img
        src="{{ $url }}"
        alt="Foto profil {{ $user->name }}"
        {{ $attributes->class('shrink-0 rounded-full object-cover') }}
    >
@else
    <span
        {{ $attributes->class('inline-flex shrink-0 items-center justify-center rounded-full bg-ink font-semibold text-cream') }}
        aria-hidden="true"
    >
        {{ $user->initials() }}
    </span>
@endif
