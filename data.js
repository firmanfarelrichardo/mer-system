// Data bersama untuk semua halaman MER System
// Disimpan di localStorage agar persisten

const MER = {
    // Inisialisasi data awal
    init() {
        if (!localStorage.getItem('mer_laporan')) {
            localStorage.setItem('mer_laporan', JSON.stringify(this.defaultLaporan));
        }
        if (!localStorage.getItem('mer_users')) {
            localStorage.setItem('mer_users', JSON.stringify(this.defaultUsers));
        }
        if (!localStorage.getItem('mer_notifikasi')) {
            localStorage.setItem('mer_notifikasi', JSON.stringify(this.defaultNotifikasi));
        }
    },

    // Ambil data
    getLaporan() { return JSON.parse(localStorage.getItem('mer_laporan') || '[]'); },
    getUsers() { return JSON.parse(localStorage.getItem('mer_users') || '[]'); },
    getNotifikasi() { return JSON.parse(localStorage.getItem('mer_notifikasi') || '[]'); },

    // Simpan data
    saveLaporan(data) { localStorage.setItem('mer_laporan', JSON.stringify(data)); },
    saveUsers(data) { localStorage.setItem('mer_users', JSON.stringify(data)); },
    saveNotifikasi(data) { localStorage.setItem('mer_notifikasi', JSON.stringify(data)); },

    // Tambah laporan
    addLaporan(lap) {
        const data = this.getLaporan();
        lap.id = 'MER-2026-' + String(data.length + 1).padStart(3, '0');
        const now = new Date();
        lap.tanggalLapor = now.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        lap.waktuLapor = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        data.unshift(lap);
        this.saveLaporan(data);
        return lap;
    },

    // Update laporan
    updateLaporan(id, updates) {
        const data = this.getLaporan();
        const idx = data.findIndex(l => l.id === id);
        if (idx !== -1) { Object.assign(data[idx], updates); this.saveLaporan(data); }
    },

    // Hapus laporan
    hapusLaporan(id) {
        let data = this.getLaporan();
        data = data.filter(l => l.id !== id);
        this.saveLaporan(data);
    },

    // Tambah user
    addUser(user) {
        const data = this.getUsers();
        user.id = data.length + 1;
        data.push(user);
        this.saveUsers(data);
        return user;
    },

    // Update user
    updateUser(id, updates) {
        const data = this.getUsers();
        const idx = data.findIndex(u => u.id === id);
        if (idx !== -1) { Object.assign(data[idx], updates); this.saveUsers(data); }
    },

    // Hapus user
    hapusUser(id) {
        let data = this.getUsers();
        data = data.filter(u => u.id !== id);
        this.saveUsers(data);
    },

    // Tambah notifikasi
    addNotifikasi(notif) {
        const data = this.getNotifikasi();
        notif.id = data.length + 1;
        notif.tanggal = new Date().toLocaleDateString('id-ID');
        notif.dibaca = false;
        data.unshift(notif);
        this.saveNotifikasi(data);
    },

    // Status label
    statusLabel(s) {
        const map = { baru: 'Kasus Baru', ditinjau: 'Sedang Ditinjau', ditindak: 'Sedang Ditindak', selesai: 'Selesai' };
        return map[s] || s;
    },
    statusColor(s) {
        const map = { baru: 'bg-red-100 text-red-700', ditinjau: 'bg-orange-100 text-orange-700', ditindak: 'bg-orange-50 text-orange-600', selesai: 'bg-green-100 text-green-700' };
        return map[s] || 'bg-gray-100 text-gray-700';
    },
    jenisLabel(j) {
        const map = { salah_pasien: 'Salah Pasien', salah_obat: 'Salah Obat', salah_dosis: 'Salah Dosis', salah_rute: 'Salah Rute', obat_terlewat: 'Obat Terlewat', obat_kadaluarsa: 'Obat Kadaluarsa' };
        return map[j] || j;
    },
    tipeLabel(t) {
        const map = { 
            kpc: 'KPC (Kondisi Potensial Cedera)', 
            knc: 'KNC (Kejadian Nyaris Cedera)', 
            ktc: 'KTC (Kejadian Tidak Cedera)',
            ktd: 'KTD (Kejadian Tidak Diharapkan)',
            sentinel: 'Kejadian Sentinel'
        };
        return map[t] || t;
    },
    tipeDefinisi(t) {
        const map = {
            kpc: 'Situasi yang berpotensi menimbulkan cedera, tetapi belum terjadi insiden.',
            knc: 'Insiden yang belum sampai terpapar ke pasien karena terhentikan atau disadari sebelum tindakan.',
            ktc: 'Insiden sudah terpapar/terkena ke pasien, tetapi tidak menimbulkan cedera.',
            ktd: 'Insiden yang mengakibatkan cedera pada pasien akibat tindakan medis, bukan penyakit dasarnya.',
            sentinel: 'Kejadian Tidak Diharapkan yang mengakibatkan kematian, cedera permanen, atau cedera berat sementara.'
        };
        return map[t] || '';
    },

    // Data default laporan
    defaultLaporan: [
        { id:'MER-2026-010', pasien:'Tn. Agus Wijaya', rm:'RM-20260010', lokasi:'VIP', tanggalKejadian:'15 Feb 2026', waktu:'08:00', tipeInsiden:'kpc', jenisError:'salah_obat', cedera:'tidak_ada', obat:'Insulin Actrapid', kronologi:'Ditemukan stok insulin yang hampir kadaluarsa di lemari obat ruangan, segera diidentifikasi sebelum digunakan.', status:'baru', prioritas:'rendah', pelapor:'Ns. Linda Hartati', unit:'VIP', tanggalLapor:'15 Feb 2026', waktuLapor:'08:30', umpanBalik:'' },
        { id:'MER-2026-009', pasien:'An. Raka Pratama', rm:'RM-20260009', lokasi:'Anak', tanggalKejadian:'14 Feb 2026', waktu:'19:00', tipeInsiden:'sentinel', jenisError:'salah_dosis', cedera:'berat', obat:'Morphine 10mg IV', kronologi:'Pemberian dosis morphine yang terlalu tinggi menyebabkan depresi napas berat pada anak, memerlukan intubasi dan perawatan intensif.', status:'baru', prioritas:'kritis', pelapor:'Ns. Ayu Lestari', unit:'Anak', tanggalLapor:'14 Feb 2026', waktuLapor:'19:45', umpanBalik:'' },
        { id:'MER-2026-008', pasien:'Ny. Dewi Lestari', rm:'RM-20260008', lokasi:'Interna', tanggalKejadian:'13 Feb 2026', waktu:'10:00', tipeInsiden:'ktc', jenisError:'salah_obat', cedera:'tidak_ada', obat:'Paracetamol 500mg', kronologi:'Pasien menerima Paracetamol padahal seharusnya Ibuprofen, namun tidak menimbulkan efek negatif karena keduanya adalah analgesik.', status:'ditinjau', prioritas:'rendah', pelapor:'Ns. Maria Ulfa', unit:'Interna', tanggalLapor:'13 Feb 2026', waktuLapor:'11:00', umpanBalik:'' },
        { id:'MER-2026-007', pasien:'Tn. Ahmad Hidayat', rm:'RM-20260007', lokasi:'ICU', tanggalKejadian:'12 Feb 2026', waktu:'14:30', tipeInsiden:'ktd', jenisError:'salah_dosis', cedera:'ringan', obat:'Amoxicillin 500mg', kronologi:'Perawat memberikan dosis ganda Amoxicillin karena tidak mengecek catatan pemberian sebelumnya.', status:'baru', prioritas:'tinggi', pelapor:'Ns. Sari Dewi', unit:'ICU', tanggalLapor:'12 Feb 2026', waktuLapor:'15:05', umpanBalik:'' },
        { id:'MER-2026-006', pasien:'Ny. Ratna Sari', rm:'RM-20260006', lokasi:'ICU', tanggalKejadian:'10 Feb 2026', waktu:'09:15', tipeInsiden:'knc', jenisError:'salah_obat', cedera:'tidak_ada', obat:'Metformin 850mg', kronologi:'Nyaris memberikan Metformin kepada pasien yang tidak terjadwal. Kesalahan terdeteksi saat verifikasi akhir.', status:'ditindak', prioritas:'sedang', pelapor:'Ns. Sari Dewi', unit:'ICU', tanggalLapor:'10 Feb 2026', waktuLapor:'10:00', umpanBalik:'Lakukan double-check dengan metode read-back pada setiap pemberian obat.' },
        { id:'MER-2026-005', pasien:'Tn. Budi Santoso', rm:'RM-20260005', lokasi:'IGD', tanggalKejadian:'08 Feb 2026', waktu:'22:00', tipeInsiden:'ktc', jenisError:'salah_pasien', cedera:'tidak_ada', obat:'Ceftriaxone 1g IV', kronologi:'Obat disiapkan untuk pasien bed 3 dan diberikan ke pasien bed 4 karena nama yang mirip, namun tidak menimbulkan efek negatif.', status:'selesai', prioritas:'sedang', pelapor:'Ns. Dewi Anggraini', unit:'IGD', tanggalLapor:'08 Feb 2026', waktuLapor:'22:45', umpanBalik:'Tim sudah melakukan sosialisasi double-check identitas pasien.' },
        { id:'MER-2026-004', pasien:'An. Putri Ayu', rm:'RM-20260004', lokasi:'NICU', tanggalKejadian:'06 Feb 2026', waktu:'03:45', tipeInsiden:'knc', jenisError:'salah_dosis', cedera:'tidak_ada', obat:'Gentamicin 20mg', kronologi:'Dosis Gentamicin yang dihitung kurang tepat untuk berat badan neonatus. Terdeteksi oleh apoteker saat verifikasi.', status:'selesai', prioritas:'sedang', pelapor:'Ns. Rina Kartika', unit:'NICU', tanggalLapor:'06 Feb 2026', waktuLapor:'04:30', umpanBalik:'Kasus sudah ditindaklanjuti. Terima kasih atas laporannya.' },
        { id:'MER-2026-003', pasien:'Ny. Lestari', rm:'RM-20260003', lokasi:'Bedah', tanggalKejadian:'04 Feb 2026', waktu:'16:20', tipeInsiden:'ktd', jenisError:'obat_terlewat', cedera:'ringan', obat:'Ketorolac 30mg IV', kronologi:'Obat analgesik pasca operasi tidak diberikan sesuai jadwal, menyebabkan pasien mengeluh nyeri berlebih.', status:'selesai', prioritas:'sedang', pelapor:'Ns. Fitri Handayani', unit:'Bedah', tanggalLapor:'04 Feb 2026', waktuLapor:'17:00', umpanBalik:'Sudah dilakukan perbaikan jadwal pemberian obat.' },
        { id:'MER-2026-002', pasien:'Tn. Wahyu', rm:'RM-20260002', lokasi:'Interna', tanggalKejadian:'01 Feb 2026', waktu:'11:00', tipeInsiden:'knc', jenisError:'salah_rute', cedera:'tidak_ada', obat:'Omeprazole 40mg', kronologi:'Omeprazole yang seharusnya diberikan secara IV hendak diberikan per oral. Tertangkap saat pengecekan.', status:'selesai', prioritas:'rendah', pelapor:'Ns. Maria Ulfa', unit:'Interna', tanggalLapor:'01 Feb 2026', waktuLapor:'11:45', umpanBalik:'Perbaikan label rute pemberian telah dilakukan.' },
        { id:'MER-2026-001', pasien:'Ny. Siti Aminah', rm:'RM-20260001', lokasi:'ICU', tanggalKejadian:'28 Jan 2026', waktu:'07:30', tipeInsiden:'kpc', jenisError:'obat_kadaluarsa', cedera:'tidak_ada', obat:'NaCl 0.9%', kronologi:'Teridentifikasi infus NaCl yang sudah melewati tanggal kedaluwarsa 2 minggu di trolley emergency, segera disingkirkan sebelum digunakan.', status:'selesai', prioritas:'rendah', pelapor:'Ns. Sari Dewi', unit:'ICU', tanggalLapor:'28 Jan 2026', waktuLapor:'08:15', umpanBalik:'Rotasi stok farmasi sudah diperbaiki.' }
    ],

    // Data default users
    defaultUsers: [
        { id:1, nama:'Ns. Sari Dewi', nip:'NIP-2020001', role:'nakes', unit:'ICU', email:'sari@rs.id', status:'aktif', shift:'pagi' },
        { id:2, nama:'Ns. Dewi Anggraini', nip:'NIP-2020002', role:'nakes', unit:'IGD', email:'dewi@rs.id', status:'aktif', shift:'sore' },
        { id:3, nama:'Ns. Rina Kartika', nip:'NIP-2020003', role:'nakes', unit:'NICU', email:'rina@rs.id', status:'aktif', shift:'malam' },
        { id:4, nama:'Ns. Fitri Handayani', nip:'NIP-2020004', role:'nakes', unit:'Bedah', email:'fitri@rs.id', status:'aktif', shift:'pagi' },
        { id:5, nama:'Ns. Maria Ulfa', nip:'NIP-2020005', role:'nakes', unit:'Interna', email:'maria@rs.id', status:'aktif', shift:'sore' },
        { id:6, nama:'Ns. Ayu Lestari', nip:'NIP-2020006', role:'nakes', unit:'ICU', email:'ayu@rs.id', status:'cuti', shift:'pagi' },
        { id:7, nama:'Dr. Rina Kartika', nip:'NIP-2019001', role:'manager', unit:'ICU', email:'dr.rina@rs.id', status:'aktif', shift:'pagi' },
        { id:8, nama:'Dr. Budi Prakoso', nip:'NIP-2019002', role:'manager', unit:'IGD', email:'dr.budi@rs.id', status:'aktif', shift:'pagi' },
        { id:9, nama:'Dr. Hendra Wijaya', nip:'NIP-2018001', role:'komite', unit:'Semua', email:'dr.hendra@rs.id', status:'aktif', shift:'pagi' },
        { id:10, nama:'Prof. Dr. Bambang Sutrisno', nip:'NIP-2015001', role:'direktur', unit:'Semua', email:'direktur@rs.id', status:'aktif', shift:'pagi' },
        { id:11, nama:'Admin Sistem', nip:'NIP-2017001', role:'admin', unit:'IT', email:'admin@rs.id', status:'aktif', shift:'pagi' }
    ],

    // Data default notifikasi
    defaultNotifikasi: [
        { id:1, judul:'Umpan Balik Baru dari Kepala Ruang', pesan:'Dr. Rina Kartika memberikan umpan balik untuk laporan MER-2026-006.', tanggal:'10 Feb 2026', dibaca:false, tipe:'umpan_balik' },
        { id:2, judul:'Status Berubah: Sedang Ditindak', pesan:'Laporan MER-2026-006 telah diubah statusnya menjadi Sedang Ditindak oleh Dr. Rina Kartika.', tanggal:'10 Feb 2026', dibaca:false, tipe:'status' },
        { id:3, judul:'Laporan Berhasil Dikirim', pesan:'Laporan MER-2026-007 berhasil dikirim dan menunggu peninjauan.', tanggal:'12 Feb 2026', dibaca:false, tipe:'sukses' },
        { id:4, judul:'Laporan MER-2026-004 Selesai', pesan:'Laporan MER-2026-004 telah selesai ditindaklanjuti. Umpan balik final sudah tersedia.', tanggal:'07 Feb 2026', dibaca:true, tipe:'selesai' },
        { id:5, judul:'Umpan Balik dari Komite', pesan:'Dr. Hendra Wijaya memberikan rekomendasi untuk laporan MER-2026-003.', tanggal:'05 Feb 2026', dibaca:true, tipe:'umpan_balik' }
    ]
};

// Inisialisasi saat dimuat
MER.init();
