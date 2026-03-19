{{--
|--------------------------------------------------------------------------
| Komponen: x-confirm-modal
|--------------------------------------------------------------------------
| Modal konfirmasi global bergaya — menggantikan dialog bawaan browser.
|
| Dipicu otomatis oleh form yang memiliki atribut data-confirm="...":
|   <form data-confirm="Yakin ingin menghapus data ini?" ...>
|
| Atribut opsional pada <form>:
|   data-confirm-label      — teks tombol konfirmasi (default: 'Ya, Lanjutkan')
|   data-confirm-destructive="true" — mengubah tombol konfirmasi menjadi merah
|
| Dipicu manual dari JavaScript:
|   window.dispatchEvent(new CustomEvent('confirm-modal', {
|       detail: {
|           message:     '...',
|           confirmLabel: 'Hapus',          // opsional
|           destructive:  true,             // opsional
|           onConfirm:    () => { ... },    // callback wajib
|       }
|   }));
|
| Komponen ini di-include sekali di layouts/app.blade.php.
|--------------------------------------------------------------------------
--}}

<div x-data="confirmModal()"
     @confirm-modal.window="open($event.detail)"
     @keydown.escape.window="cancel()"
     x-show="isOpen"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-[9998] flex items-center justify-center p-4"
     style="display: none;"
     role="dialog"
     aria-modal="true"
     aria-labelledby="confirm-modal-title">

    {{-- Backdrop blur --}}
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"
         @click="cancel()"></div>

    {{-- Panel modal --}}
    <div class="relative w-full max-w-sm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-2">

        <div class="overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200/60">

            {{-- Strip warna atas --}}
            <div class="h-1.5 w-full" :class="isDestructive ? 'bg-red-500' : 'bg-brand'"></div>

            <div class="p-6">

                {{-- Ikon peringatan --}}
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full"
                     :class="isDestructive ? 'bg-red-100' : 'bg-amber-100'">
                    <svg class="h-7 w-7"
                         :class="isDestructive ? 'text-red-600' : 'text-amber-600'"
                         fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71
                                 c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898
                                 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                    </svg>
                </div>

                {{-- Judul --}}
                <h3 id="confirm-modal-title"
                    class="mb-2 text-center text-base font-bold text-slate-800">
                    Konfirmasi Tindakan
                </h3>

                {{-- Pesan --}}
                <p class="mb-6 text-center text-sm leading-relaxed text-slate-500"
                   x-text="message"></p>

                {{-- Tombol aksi --}}
                <div class="flex gap-3">

                    {{-- Batal --}}
                    <button @click="cancel()"
                            type="button"
                            class="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold
                                   text-slate-600 transition-colors hover:bg-slate-50
                                   focus:outline-none focus:ring-2 focus:ring-slate-300"
                            x-ref="cancelBtn">
                        Batal
                    </button>

                    {{-- Konfirmasi --}}
                    <button @click="confirm()"
                            type="button"
                            class="flex-1 rounded-xl px-4 py-2.5 text-sm font-semibold text-white
                                   transition-all focus:outline-none focus:ring-2 focus:ring-offset-2"
                            :class="isDestructive
                                ? 'bg-red-600 hover:bg-red-700 focus:ring-red-500'
                                : 'bg-brand hover:bg-brand/90 focus:ring-brand/50'"
                            x-ref="confirmBtn">
                        <span x-text="confirmLabel"></span>
                    </button>

                </div>
            </div>
        </div>
    </div>
</div>
