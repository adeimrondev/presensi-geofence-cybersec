// assets/js/geolocation.js - Geolocation Helper Utilities

/**
 * Mendapatkan posisi koordinat terkini perangkat pengguna.
 * @returns {Promise<{latitude: number, longitude: number}>}
 */
function getPreciseCoordinates() {
  return new Promise((resolve, reject) => {
    if (!("geolocation" in navigator)) {
      reject(new Error("Peramban Anda tidak mendukung fitur Geolocation."));
      return;
    }

    const options = {
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 0,
    };

    navigator.geolocation.getCurrentPosition(
      (position) => {
        resolve({
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
          accuracy: position.coords.accuracy,
        });
      },
      (error) => {
        let errorMsg = "Gagal membaca sinyal lokasi.";
        switch (error.code) {
          case error.PERMISSION_DENIED:
            errorMsg = "Izin akses lokasi (GPS) ditolak oleh pengguna.";
            break;
          case error.POSITION_UNAVAILABLE:
            errorMsg = "Informasi lokasi GPS tidak tersedia.";
            break;
          case error.TIMEOUT:
            errorMsg = "Batas waktu permintaan lokasi berakhir.";
            break;
        }
        reject(new Error(errorMsg));
      },
      options,
    );
  });
}
