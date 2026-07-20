{{--
|--------------------------------------------------------------------------
| Edit Kategori Kesalahan (admin/kategori/edit.blade.php)
|--------------------------------------------------------------------------
| Formulir pengeditan kategori kesalahan yang sudah ada.
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Edit Kategori - Sistem MER')

@section('konten')

    {{-- HEADER + BREADCRUMB --}}
    <div class="mb-6">
        <nav class="mb-2 text-xs text-slate-400">
            <a href="{{ route('admin.kategori.index') }}" class="hover:text-brand">Master Kategori Insiden</a>
            <span class="mx-1">›</span>
            <span class="text-slate-600">Edit</span>
        </nav>
        <h1 class="text-2xl font-bold text-slate-800">Edit Kategori</h1>
        <p class="mt-1 text-sm text-slate-400">
            Perbarui data kategori <strong>{{ $dataKategori->nama_kategori }}</strong>.
        </p>
    </div>

    {{-- ================================================================
         FORMULIR
         ================================================================ --}}
    <form method="POST" action="{{ route('admin.kategori.perbarui', $dataKategori->id) }}"
          class="mx-auto max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')

        <div class="space-y-4">

            {{-- Nama Kategori --}}
            <div>
                <label for="nama_kategori" class="mb-1 block text-sm font-medium text-slate-700">
                    Nama Kategori <span class="text-red-500">*</span>
                </label>
                <input type="text" id="nama_kategori" name="nama_kategori"
                       value="{{ old('nama_kategori', $dataKategori->nama_kategori) }}"
                       placeholder="Contoh: Salah Dosis, Salah Obat, Salah Pasien"
                       class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm placeholder-slate-400
                              focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30
                              @error('nama_kategori') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                @error('nama_kategori')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- ===== TOMBOL AKSI ===== --}}
        <div class="mt-6 flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
            <a href="{{ route('admin.kategori.index') }}"
               class="rounded-lg border border-slate-200 px-5 py-2.5 text-sm font-medium text-slate-600
                      transition-colors hover:bg-slate-50">
                Batal
            </a>
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white
                           shadow-sm transition-colors hover:bg-brand/90 focus:outline-none focus:ring-2 focus:ring-brand/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182"/>
                </svg>
                Perbarui Kategori
            </button>
        </div>
    </form>

@endsection
