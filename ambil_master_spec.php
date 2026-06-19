<?php
include 'koneksi.php';

if (isset($_POST['engine_model'])) {
    // Set header agar output dikenali sebagai JSON oleh client/AJAX
    header('Content-Type: application/json');

    $model = mysqli_real_escape_string($koneksi, $_POST['engine_model']);
    
    // Ambil seluruh parameter spec untuk model engine yang dipilih
    $query = mysqli_query($koneksi, "SELECT main_data, standard FROM master_engine_spec WHERE engine_model = '$model'");
    
    // Siapkan array penampung default kosong
    $spec = array(
        'cont_power'  => '',
        'max_power'   => '',
        'hi_idle'     => '',
        'output'      => '',
        'torque'      => '',
        'load'        => '',
        'exhaust'     => '',
        'oil_temp'    => '',
        'lo'          => '',
        'correct_co'  => '', 
        'fic'         => '',
        
        // --- PARAMETER FUEL & SD ---
        'fuel_mm3'    => '', // Untuk unit mm³/st (Fuel Cons 1)
        'fuel_gkwh'   => '', // Untuk unit g/kWh (Fuel Cons 2)
        'sd_bsu'      => '', // Untuk unit BSU (SD)

        // ========================================================
        // KUNCI PERBAIKAN: Wadah Penampung Teks Speed Didaftarkan di Sini
        // ========================================================
        'speed1'      => '',
        'speed2'      => '',
        'speed3'      => ''
    );
    
    // Mapping antara nilai di database (UPPERCASE) dengan key array $spec
    $map = array(
        'CONT POWER'  => 'cont_power',
        'MAX POWER'   => 'max_power',
        'HI IDLE'     => 'hi_idle',
        'OUTPUT'      => 'output',
        'TORQUE'      => 'torque',
        'LOAD'        => 'load',
        'EXHAUST'     => 'exhaust',
        'OIL TEMP'    => 'oil_temp', 
        'LO'          => 'lo',
        'CORRECT CO'  => 'correct_co', 
        'FIC'         => 'fic',
        
        // --- TAMBAHAN MAPPING FUEL & SD ---
        'FUEL CONS 1' => 'fuel_mm3',  
        'FUEL CONS 2' => 'fuel_gkwh', 
        'SD'          => 'sd_bsu', 
        
        // --- MAPPING SPEED ENGINE ---
        'SPEED1'      => 'speed1',  
        'SPEED2'      => 'speed2',
        'SPEED3'      => 'speed3'     
    );
    
    // Petakan data dari database secara dinamis
    while ($row = mysqli_fetch_assoc($query)) {
        // strtoupper dan trim untuk mengantisipasi perbedaan spasi/huruf kapital di DB
        $mainData = strtoupper(trim($row['main_data']));
        $standardVal = $row['standard'];
        
        // Jika main_data terdaftar di dalam map, langsung masukkan ke array spec
        if (array_key_exists($mainData, $map)) {
            $key = $map[$mainData];
            $spec[$key] = $standardVal;
        }
    }
    
    // Kirim data kembali dalam bentuk JSON format
    echo json_encode($spec);
    exit; // Pastikan script berhenti di sini agar tidak ada output tambahan
}
?>