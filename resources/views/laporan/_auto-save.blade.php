{{--
|--------------------------------------------------------------------------
| Auto-Save Alpine.js Component (Partial)
|--------------------------------------------------------------------------
| Komponen Alpine.js untuk auto-save background pada formulir laporan.
|
| Cara pakai:
|   1. Sertakan partial ini di dalam @section('konten').
|   2. Tambahkan x-data="autoSaveForm()" pada <form>.
|   3. Tambahkan @input.debounce.2000ms="simpanBackground" pada <form>.
|   4. Tambahkan hidden input: <input type="hidden" name="insiden_id" :value="insidenId">
|   5. Sertakan indikator status di area tombol navigasi.
|
| Variabel yang harus tersedia sebelum include:
|   $autoSaveInsidenId - int|null - ID insiden (null untuk form baru)
|--------------------------------------------------------------------------
--}}

{{-- ================================================================
     INDIKATOR STATUS AUTO-SAVE (disisipkan di area tombol navigasi)
     ================================================================ --}}
{{-- Komponen indikator ini harus ditempatkan secara manual di view parent.
     Gunakan markup berikut di dalam form yang memiliki x-data="autoSaveForm()":

     <div x-show="statusAutoSave !== 'idle'" x-cloak
          class="flex items-center gap-1.5 text-xs transition-all duration-300">
         ... (lihat template di bawah)
     </div>
--}}

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('autoSaveForm', () => ({
            /**
             * ID insiden yang sedang di-edit.
             * null = form baru (CREATE), terisi setelah auto-save pertama.
             */
            insidenId: @json($autoSaveInsidenId ?? null),

            /**
             * Status auto-save saat ini.
             * 'idle' | 'saving' | 'saved' | 'error'
             */
            statusAutoSave: 'idle',

            /**
             * Waktu terakhir berhasil disimpan (format HH:MM:SS).
             */
            waktuTerakhir: null,

            /**
             * Menandakan apakah sedang dalam proses simpan.
             * Mencegah request ganda yang tumpang tindih.
             */
            _sedangSimpan: false,

            /**
             * Simpan data form ke server secara background.
             *
             * Dipanggil oleh @input.debounce.2000ms pada form.
             * Menggunakan fetch() dengan FormData dari seluruh isi form.
             */
            async simpanBackground() {
                // Cegah request tumpang tindih.
                if (this._sedangSimpan) return;
                this._sedangSimpan = true;
                this.statusAutoSave = 'saving';

                try {
                    const form = this.$el;
                    const formData = new FormData(form);

                    // Pastikan insiden_id selalu terkirim (bisa null/kosong).
                    if (this.insidenId) {
                        formData.set('insiden_id', this.insidenId);
                    }

                    const response = await fetch('{{ route("laporan.auto-save") }}', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });

                    // ── Handle session expired / CSRF mismatch ──────
                    if (response.status === 401 || response.status === 419) {
                        this.statusAutoSave = 'error';
                        this._sedangSimpan = false;

                        window.dispatchEvent(new CustomEvent('toast', {
                            detail: {
                                type: 'warning',
                                message: 'Sesi Anda telah berakhir. Halaman akan dimuat ulang untuk login kembali.',
                                duration: 3000,
                            },
                        }));
                        setTimeout(() => window.location.reload(), 3000);
                        return;
                    }

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const hasil = await response.json();

                    if (hasil.status === 'ok') {
                        // Update insiden_id dari response (penting untuk CREATE pertama).
                        if (hasil.insiden_id) {
                            this.insidenId = hasil.insiden_id;
                        }

                        this.waktuTerakhir  = hasil.waktu;
                        this.statusAutoSave = 'saved';

                        // Kembalikan ke idle setelah 3 detik agar indikator hilang.
                        setTimeout(() => {
                            if (this.statusAutoSave === 'saved') {
                                this.statusAutoSave = 'idle';
                            }
                        }, 3000);
                    } else {
                        this.statusAutoSave = 'error';
                    }
                } catch (error) {
                    console.error('[AutoSave] Gagal:', error);
                    this.statusAutoSave = 'error';

                    // Kembalikan ke idle setelah 5 detik agar bisa retry.
                    setTimeout(() => {
                        if (this.statusAutoSave === 'error') {
                            this.statusAutoSave = 'idle';
                        }
                    }, 5000);
                } finally {
                    this._sedangSimpan = false;
                }
            },
        }));
    });
</script>
