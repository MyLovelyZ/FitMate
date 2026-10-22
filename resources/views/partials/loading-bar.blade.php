{{--
    Bar loading request Livewire. Dikendalikan `resources/js/app.js` lewat atribut
    `data-state` (loading → done → kosong), jadi tidak butuh Alpine.
--}}
<div
    data-request-bar
    aria-hidden="true"
    class="pointer-events-none fixed inset-x-0 top-0 z-[1000] h-0.5 origin-left scale-x-0 bg-ink opacity-0 data-[state=loading]:scale-x-[0.85] data-[state=loading]:opacity-100 data-[state=loading]:transition-transform data-[state=loading]:duration-[2500ms] data-[state=loading]:ease-out data-[state=done]:scale-x-100 data-[state=done]:opacity-0 data-[state=done]:transition-[transform,opacity] data-[state=done]:duration-300"
></div>
