const ALPHABET = "23456789CFGHJMPQRVWX"; // 20 karakter

function decodePlusCode(code) {
  const digits = code.replace("+", "").toUpperCase();
  if (digits.length < 10)
    throw new Error("Butuh kode lengkap (min 10 digit), bukan kode pendek");

  let lat = -90,
    lng = -180,
    size = 20;

  for (let p = 0; p < 5; p++) {
    size = 20 / 20 ** p; // 20, 1, 0.05, 0.0025, 0.000125
    lat += ALPHABET.indexOf(digits[p * 2]) * size;
    lng += ALPHABET.indexOf(digits[p * 2 + 1]) * size;
  }

  return { lat: lat + size / 2, lng: lng + size / 2 }; // titik tengah kotak
}
