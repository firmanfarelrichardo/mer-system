<?php
$files = ['resources/views/laporan/buat.blade.php', 'resources/views/laporan/edit.blade.php'];
foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Find the start of Baris Tombol Navigasi
    $startStr = '{{-- Baris Tombol Navigasi --}}';
    $endStr = '</form>';
    
    $startPos = strpos($content, $startStr);
    if ($startPos === false) continue;
    
    $endPos = strpos($content, $endStr, $startPos);
    if ($endPos === false) continue;

    $replacement = <<<EOD
{{-- Baris Tombol Navigasi --}}
            <div class="mt-4 flex flex-col-reverse gap-3 sm:mt-6 sm:flex-row sm:items-center sm:justify-between">
                {{-- Tombol Kembali --}}
                <div class="flex w-full sm:w-auto">
                    <button type="button" id="btn-kembali"
                            class="hidden w-full items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-5 py-2.5
                                   text-sm font-medium text-slate-600 shadow-sm transition-colors hover:bg-slate-50 sm:w-auto sm:inline-flex"
                            onclick="ubahTahap(-1)">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                        </svg>
                        Kembali
                    </button>
                </div>

                {{-- Tombol Aksi Utama (Simpan Draf, Selanjutnya, Kirim) --}}
                <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
                    <button type="submit" name="action" value="simpan_draf" id="btn-draf"
                            class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-brand bg-white px-5 py-2.5
                                   text-sm font-medium text-brand shadow-sm transition-colors hover:bg-brand/5 sm:w-auto">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z"/>
                        </svg>
                        Simpan Draf
                    </button>

                    <button type="button" id="btn-selanjutnya"
                            class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-brand px-5 py-2.5 text-sm font-medium
                                   text-white shadow-sm transition-colors hover:bg-brand-hover focus:outline-none focus:ring-2
                                   focus:ring-brand/50 focus:ring-offset-2 sm:w-auto"
                            onclick="ubahTahap(1)">
                        Selanjutnya
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                        </svg>
                    </button>

                    <button type="submit" name="action" value="kirim_laporan" id="btn-kirim"
                            class="hidden w-full items-center justify-center gap-1.5 rounded-lg bg-brand px-5 py-2.5 text-sm font-medium
                                   text-white shadow-sm transition-colors hover:bg-brand-hover focus:outline-none focus:ring-2
                                   focus:ring-brand/50 focus:ring-offset-2 sm:w-auto">
                        Kirim Laporan
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </form>
EOD;

    // We replace from "Baris Tombol Navigasi" up to and including "</form>" by subtracting the positions.
    // Wait, the original code had </div></div></form> before the next section.
    // In our replacement string, we need to match the outer divs.
    $newContent = substr($content, 0, $startPos) . $replacement . substr($content, $endPos + strlen($endStr));
    file_put_contents($file, $newContent);
    echo "Updated \$file\n";
}
