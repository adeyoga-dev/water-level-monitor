const ALPHABET = "23456789CFGHJMPQRVWX"; // 20 karakter
const REF = { lat: -6.3215, lng: 107.3376 }; // titik referensi (pusat Karawang), untuk kode pendek
const PLUS_RE =
  /^\s*([23456789CFGHJMPQRVWX]{2,8}\+[23456789CFGHJMPQRVWX]{2,3})(?:\s|$)/i;

// 10 digit pertama = 5 pasang (lat, lng)
function decodePlusCode(digits) {
  let lat = -90,
    lng = -180,
    size = 20;
  for (let p = 0; p < 5; p++) {
    size = 20 / 20 ** p;
    lat += ALPHABET.indexOf(digits[p * 2]) * size;
    lng += ALPHABET.indexOf(digits[p * 2 + 1]) * size;
  }
  return { lat: lat + size / 2, lng: lng + size / 2 };
}

// Kode pendek -> lengkap: ambil prefix dari titik referensi
function prefixFromRef(pairs) {
  let lat = REF.lat + 90,
    lng = REF.lng + 180,
    size = 20,
    out = "";
  for (let p = 0; p < pairs; p++) {
    const la = Math.floor(lat / size),
      lo = Math.floor(lng / size);
    out += ALPHABET[la] + ALPHABET[lo];
    lat -= la * size;
    lng -= lo * size;
    size /= 20;
  }
  return out;
}

// Return "lat, lng" kalau input Plus Code, null kalau bukan Plus Code
function plusCodeToCoordinate(value) {
  const m = value.match(PLUS_RE);
  if (!m) return null;

  let digits = m[1].replace("+", "").toUpperCase();
  if (digits.length < 10) {
    if (digits.length % 2) throw new Error("Plus Code tidak valid.");
    digits = prefixFromRef((10 - digits.length) / 2) + digits;
  }
  const { lat, lng } = decodePlusCode(digits);
  return `${lat.toFixed(7)}, ${lng.toFixed(7)}`;
}

const input = document.getElementById("device-coordinates");

if (input) {
  input.addEventListener("input", () => input.setCustomValidity(""));

  input.form.addEventListener("submit", (e) => {
    try {
      const converted = plusCodeToCoordinate(input.value);
      if (converted) input.value = converted; // diganti sebelum form dikirim
    } catch (err) {
      e.preventDefault();
      input.setCustomValidity(err.message);
      input.reportValidity();
    }
  });
}
