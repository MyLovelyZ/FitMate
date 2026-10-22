/**
 * Tema dari tab "Tampilan" di profil. Halaman berikutnya sudah dirender server
 * dengan `<html data-theme>` yang baru; ini hanya supaya halaman profil langsung berubah.
 */
window.addEventListener('theme-changed', (event) => {
    document.documentElement.dataset.theme = event.detail.theme;
});

/**
 * Foto tidak boleh diseret ke luar browser (di desktop itu menyimpan file gambar)
 * atau disimpan lewat menu klik-kanan/tekan-lama. Firefox tidak mengenal
 * `-webkit-user-drag` di app.css, jadi dragstart ikut dicegah di sini; link yang
 * berisi foto juga, supaya kartu produk tidak terseret saat diklik sambil bergeser.
 */
const isPhoto = (target) =>
    target instanceof Element && (target.matches('img') || (target.matches('a') && target.querySelector('img') !== null));

['dragstart', 'contextmenu'].forEach((type) => {
    document.addEventListener(type, (event) => {
        if (type === 'contextmenu' ? event.target instanceof HTMLImageElement : isPhoto(event.target)) {
            event.preventDefault();
        }
    });
});

/**
 * Bar loading di atas halaman (`partials/loading-bar`) selama ada request
 * Livewire — filter, paginasi, simpan form — yang lebih lama dari 200ms.
 * Perpindahan halaman lewat `wire:navigate` sudah punya progress bar bawaan
 * Livewire (warnanya diatur di config/livewire.php), jadi ini khusus request komponen.
 */
const SHOW_DELAY_MS = 200;
const FINISH_ANIMATION_MS = 400;

let pendingRequests = 0;
let showTimer = null;

const loadingBar = () => document.querySelector('[data-request-bar]');

function startLoading() {
    pendingRequests++;

    if (pendingRequests === 1) {
        clearTimeout(showTimer);
        showTimer = setTimeout(() => loadingBar()?.setAttribute('data-state', 'loading'), SHOW_DELAY_MS);
    }
}

function finishLoading() {
    pendingRequests = Math.max(0, pendingRequests - 1);

    if (pendingRequests > 0) {
        return;
    }

    clearTimeout(showTimer);

    const bar = loadingBar();

    if (bar?.getAttribute('data-state') !== 'loading') {
        return;
    }

    bar.setAttribute('data-state', 'done');
    setTimeout(() => {
        if (bar.getAttribute('data-state') === 'done') {
            bar.removeAttribute('data-state');
        }
    }, FINISH_ANIMATION_MS);
}

document.addEventListener('livewire:init', () => {
    window.Livewire.interceptRequest(({ onSend, onCancel, onFinish }) => {
        // onCancel dan onFinish bisa sama-sama terpanggil; hitung sekali saja.
        let isCounted = false;

        const settle = () => {
            if (isCounted) {
                isCounted = false;
                finishLoading();
            }
        };

        onSend(() => {
            isCounted = true;
            startLoading();
        });
        onCancel(settle);
        onFinish(settle);
    });
});
