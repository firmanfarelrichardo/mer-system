{{--
|--------------------------------------------------------------------------
| Filter Dialog Component  —  components/filter-dropdown.blade.php
|--------------------------------------------------------------------------
| Modal tertengah yang muncul di atas backdrop blur.
| Responsif di semua ukuran layar (mobile-first).
|
| Props:
|   $action   — form action URL            (default: current URL)
|   $method   — HTTP method                (default: 'GET')
|   $title    — heading teks di dialog     (default: 'Filter')
|   $maxWidth — max lebar panel Tailwind   (default: 'max-w-md')
|
| Contoh:
|   <x-filter-dropdown :action="route('laporan.index')" title="Filter Laporan">
|       <div><label>Status</label><select name="status">...</select></div>
|   </x-filter-dropdown>
|--------------------------------------------------------------------------
--}}

@props([
    'action'   => url()->current(),
    'method'   => 'GET',
    'title'    => 'Filter',
    'maxWidth' => 'max-w-md',
])

<div
    x-data="{ open: false }"
    x-effect="document.body.style.overflow = open ? 'hidden' : ''"
    @keydown.escape.window="open = false">

    {{-- Trigger Button --}}
    <button
        type="button"
        @click="open = true"
        :class="open ? 'border-brand ring-2 ring-brand/20' : 'border-slate-200 hover:bg-slate-50'"
        class="inline-flex items-center gap-2 rounded-lg border bg-white px-4 py-2.5 text-sm font-medium
               text-slate-700 shadow-sm transition focus:outline-none focus:ring-2 focus:ring-brand/20"
        aria-haspopup="dialog"
        :aria-expanded="open.toString()">

        <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/>
        </svg>
        <span>{{ $title }}</span>
        <svg class="h-3.5 w-3.5 text-slate-400 transition-transform duration-200"
             :class="open ? 'rotate-180' : ''"
             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
        </svg>
    </button>

    {{-- Backdrop --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="open = false"
        class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm"
        aria-hidden="true"
        style="display:none">
    </div>

    {{-- Dialog Panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        style="display:none">

        <div @click.stop class="w-full {{ $maxWidth }} rounded-2xl border border-slate-200 bg-white shadow-2xl">

            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-brand" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/>
                    </svg>
                    <h2 class="text-sm font-semibold text-slate-800">{{ $title }}</h2>
                </div>
                <button type="button" @click="open = false"
                        class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                        aria-label="Tutup filter">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Form --}}
            <form method="{{ strtoupper($method) === 'GET' ? 'GET' : 'POST' }}" action="{{ $action }}">
                @unless(strtoupper($method) === 'GET')
                    @csrf
                    @if(! in_array(strtoupper($method), ['GET', 'POST']))
                        @method($method)
                    @endif
                @endunless

                <div class="space-y-4 overflow-y-auto px-5 py-5" style="max-height: calc(100dvh - 12rem)">
                    {{ $slot }}
                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-between border-t border-slate-100 px-5 py-4">
                    <a href="{{ $action }}"
                       class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium
                              text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                        Reset
                    </a>
                    <button type="submit" @click="open = false"
                            class="inline-flex items-center gap-2 rounded-lg bg-brand px-5 py-2 text-xs
                                   font-semibold text-white shadow-sm transition hover:bg-brand/90">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/>
                        </svg>
                        Terapkan Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
