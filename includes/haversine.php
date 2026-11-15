<?php
// includes/haversine.php - Server-Side Geofencing Verification

/**
 * Menghitung jarak antara 2 titik koordinat latitude/longitude menggunakan rumus Haversine
 * Output dalam satuan: Meter
 */
function calculateHaversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // Radius rata-rata bumi dalam meter

    $latFrom = deg2rad((float)$lat1);
    $lonFrom = deg2rad((float)$lon1);
    $latTo   = deg2rad((float)$lat2);
    $lonTo   = deg2rad((float)$lon2);

    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;

    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
        cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

    return round($angle * $earthRadius, 2);
}