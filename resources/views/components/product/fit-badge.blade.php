@props(['status'])

{{--
    Menerjemahkan `fit_status` dari SizeRecommendationService (BE-031)
    menjadi label yang bisa dibaca pembeli.
--}}
@php
    $map = [
        'fit' => ['variant' => 'success', 'label' => 'Pas untukmu'],
        'tight' => ['variant' => 'warning', 'label' => 'Cenderung sempit'],
        'loose' => ['variant' => 'warning', 'label' => 'Cenderung longgar'],
    ];

    $config = $map[$status] ?? ['variant' => 'gray', 'label' => 'Ukuran belum dihitung'];
@endphp

<x-ui.badge :variant="$config['variant']" {{ $attributes }}>{{ $config['label'] }}</x-ui.badge>
