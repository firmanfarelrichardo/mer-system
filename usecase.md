# Use Case Diagram: MER System

Berikut adalah representasi Use Case Diagram untuk Medical Error Reporting (MER) System menggunakan PlantUML, melibatkan 3 role utama (Tenaga Kesehatan, Kepala Ruangan, dan Komite), serta menerapkan konsep `<<include>>` dan `<<extend>>`.

```plantuml
@startuml
left to right direction
skinparam packageStyle rectangle

actor "Tenaga Kesehatan" as nakes
actor "Kepala Ruangan" as karu
actor "Komite Mutu" as komite

rectangle "Sistem Pelaporan Insiden (MER System)" {
    
    usecase "Otentikasi (Login)" as login
    
    ' Fitur Nakes
    usecase "Lapor Insiden" as lapor
    usecase "Pantau Status" as pantau
    usecase "Lampirkan Berkas" as lampir
    
    ' Fitur Karu
    usecase "Validasi Laporan" as validasi
    usecase "Investigasi Awal" as inv_awal
    usecase "Tentukan Grading" as grading_karu
    
    ' Fitur Komite
    usecase "Verifikasi Laporan" as verifikasi
    usecase "Investigasi RCA" as rca
    usecase "Tentukan Grading Akhir" as grading_komite
    usecase "Kelola Data Master" as master
    usecase "Unduh Statistik" as unduh
    usecase "Pantau Log Sistem" as log
    
    ' --- Relasi Actor ke Use Case ---
    nakes --> lapor
    nakes --> pantau
    
    karu --> pantau
    karu --> validasi
    
    komite --> pantau
    komite --> verifikasi
    komite --> master
    komite --> unduh
    komite --> log
    
    ' --- Relasi Include (Syarat Wajib) ---
    lapor ..> login : <<include>>
    pantau ..> login : <<include>>
    validasi ..> login : <<include>>
    verifikasi ..> login : <<include>>
    master ..> login : <<include>>
    unduh ..> login : <<include>>
    log ..> login : <<include>>
    
    ' --- Relasi Extend (Fitur Opsional / Tambahan saat kondisi tertentu) ---
    lampir .up.> lapor : <<extend>>
    
    inv_awal .up.> validasi : <<extend>>
    grading_karu .up.> validasi : <<extend>>
    
    rca .up.> verifikasi : <<extend>>
    grading_komite .up.> verifikasi : <<extend>>

}
@enduml
```
