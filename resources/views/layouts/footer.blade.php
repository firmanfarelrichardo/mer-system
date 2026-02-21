{{--
|--------------------------------------------------------------------------
| Footer Global (footer.blade.php)
|--------------------------------------------------------------------------
| Komponen footer yang konsisten di seluruh halaman sistem.
| Di-@include dari layout guest.blade.php dan app.blade.php.
|
| Struktur:
|   Kiri  → "© {tahun} RSU Mayjend. H.M. Ryacudu. All Rights Reserved."
|   Kanan → "Dibiayai HETI Project Unila · Dibuat oleh Mahasiswa PSTI Unila"
|
| Menerima parameter opsional:
|   $footerGelap (bool) — true untuk bg gelap (login), false untuk terang
|--------------------------------------------------------------------------
--}}

<footer class="w-full px-6 py-3 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs border-t border-slate-200 bg-white text-slate-600">
    {{-- Sisi kiri: Hak cipta --}}
    <span>
        &copy; {{ date('Y') }}
        <strong class="text-slate-700">RSUD H.M. Ryacudu.</strong>
        All Rights Reserved.
    </span>

    {{-- Sisi kanan: Kredit --}}
    <span class="flex items-center gap-1.5">
        <!-- Designed with by
        <a href="https://github.com/firmanfarelrichardo"
           target="_blank" rel="noopener noreferrer"
           class="font-semibold underline underline-offset-2 transition-colors text-slate-700 hover:text-slate-900">
            Mahasiswa PSTI Unila
        </a> -->
    </span>
</footer>
