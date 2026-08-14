<?php

/**
 * BE-018: aturan yang lebih murah dijaga otomatis daripada lewat code review.
 */
arch('tidak ada debug yang tertinggal')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('semua enum adalah backed enum string')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

arch('model tidak memakai facade DB langsung')
    ->expect('App\Models')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('controller tidak dipanggil dari model atau service')
    ->expect('App\Http\Controllers')
    ->not->toBeUsedIn(['App\Models', 'App\Services']);

arch('service tidak bergantung pada request HTTP')
    ->expect('App\Services')
    ->not->toUse('Illuminate\Http\Request');

arch('policy hanya berisi kelas policy')
    ->expect('App\Policies')
    ->toHaveSuffix('Policy');

arch('form request diakhiri Request')
    ->expect('App\Http\Requests')
    ->toHaveSuffix('Request');

/**
 * Controller sengaja dikecualikan: beberapa aksi domain di aplikasi ini tidak
 * muat di tujuh metode resource baku (`setDefault`, `toggle`, `ship`,
 * `updateStatus`), dan memaksakannya justru mengaburkan maksudnya.
 */
arch('preset laravel')
    ->preset()
    ->laravel()
    ->ignoring(['App\Services', 'App\Rules', 'App\Enums', 'App\Http\Controllers']);
