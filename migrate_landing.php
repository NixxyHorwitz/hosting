<?php
require_once __DIR__ . '/config/database.php';

$sql = "CREATE TABLE IF NOT EXISTS landing_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_name VARCHAR(50) NOT NULL UNIQUE,
    content_json TEXT NOT NULL
);";

if (mysqli_query($conn, $sql)) {
    echo "Table landing_content created successfully.\n";

    // Insert default content if empty
    $check = mysqli_query($conn, "SELECT COUNT(*) as c FROM landing_content");
    $row = mysqli_fetch_assoc($check);
    
    if ($row['c'] == 0) {
        $hero_json = json_encode([
            'heading' => '#ThinkBig #GrowBigger',
            'subheading' => 'Onlinekan Bisnismu Sekarang dengan Web Hosting Indonesia',
            'description' => 'Buat website dan email untuk bisnismu dan mulai mendunia dengan layanan web hosting Indonesia. Dapatkan hosting dengan kecepatan dan keamanan terbaik hanya Rp 99.000 setahun.',
            'button_text' => 'MULAI',
            'image_url' => 'assets/images/hero-person.png',
            'bg_color_start' => '#0052D4',
            'bg_color_end' => '#4364F7'
        ]);
        
        $features_json = json_encode([
            [
                'title' => 'Pembuatan Website',
                'description' => 'Jasa pembuatan website instan hanya dalam 2 x 24 jam.',
                'icon' => 'bi-layout-text-window'
            ],
            [
                'title' => 'VPS',
                'description' => 'VPS dengan SSD/NVMe untuk performa terbaik.',
                'icon' => 'bi-server'
            ],
            [
                'title' => 'Email Bisnis',
                'description' => 'Email bisnis kapasitas besar yg dilengkapi tools kolaborasi.',
                'icon' => 'bi-envelope'
            ],
            [
                'title' => 'Dedicated Server',
                'description' => 'Layanan sewa server branded dengan spesifikasi terbaik.',
                'icon' => 'bi-cpu'
            ]
        ]);
        
        $stats_json = json_encode([
            'title' => '155.000+ Pelanggan memilih SobatHosting karena ...',
            'logos' => [
                ['name' => 'ICANN', 'img' => 'icann.png'],
                ['name' => 'Google Cloud', 'img' => 'gcp.png'],
                ['name' => 'cPanel', 'img' => 'cpanel.png'],
                ['name' => 'LiteSpeed', 'img' => 'litespeed.png']
            ]
        ]);
        
        mysqli_query($conn, "INSERT INTO landing_content (section_name, content_json) VALUES 
            ('hero', '$hero_json'),
            ('features', '$features_json'),
            ('stats', '$stats_json')
        ");
        echo "Default content inserted.\n";
    }
} else {
    echo "Error: " . mysqli_error($conn);
}
?>
