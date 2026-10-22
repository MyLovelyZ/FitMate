{{--
    Kotak abu berdenyut sebagai penahan tempat selama konten lazy dimuat.
    Ukurannya diatur pemanggil lewat class, mis. `<x-ui.skeleton class="h-24" />`.
--}}
<div {{ $attributes->class('animate-pulse rounded-md bg-line/70 motion-reduce:animate-none') }} aria-hidden="true"></div>
