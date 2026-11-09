(() => {
  const ALPHABET = "23456789CFGHJMPQRVWX";
  const input = document.getElementById("device-coordinates");
  if (!input) return;

  const C = "[23456789CFGHJMPQRVWX]";
  const GLOBAL_RE = new RegExp(`^${C}{8}\\+${C}{0,7}$`, "i");
  const SHORT_RE = new RegExp(`^(${C}{2,6}\\+${C}{2,})[,\\s]+(.+)$`, "i");
  const SHORT_ONLY_RE = new RegExp(`^${C}{2,6}\\+${C}{2,}$`, "i");

  let busy = false;
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

  // --- Decode kode global -> titik tengah cell ---
  function decode(code) {
    const c = code.replace("+", "").toUpperCase();
    let lat = -90,
      lng = -180,
      size = 20,
      latCell = 20,
      lngCell = 20;

    for (let i = 0; i < Math.min(c.length, 10); i += 2) {
      lat += ALPHABET.indexOf(c[i]) * size;
      lng += ALPHABET.indexOf(c[i + 1]) * size;
      latCell = lngCell = size;
      size /= 20;
    }

    // Grid refinement (karakter ke-11 dst): grid 5 baris x 4 kolom
    for (let i = 10; i < c.length; i++) {
      const d = ALPHABET.indexOf(c[i]);
      latCell /= 5;
      lngCell /= 4;
      lat += Math.floor(d / 4) * latCell;
      lng += (d % 4) * lngCell;
    }

    return { lat: lat + latCell / 2, lng: lng + lngCell / 2 };
  }

  // --- Prefix kode dari koordinat referensi ---
  function prefixOf(lat, lng, len) {
    lat = Math.min(Math.max(lat, -90), 90 - 1e-9) + 90;
    lng = (((lng + 180) % 360) + 360) % 360;
    let out = "",
      size = 20;
    for (let i = 0; i < len; i += 2) {
      const a = Math.floor(lat / size),
        b = Math.floor(lng / size);
      out += ALPHABET[a] + ALPHABET[b];
      lat -= a * size;
      lng -= b * size;
      size /= 20;
    }
    return out;
  }

  // --- Kode pendek + titik referensi -> koordinat ---
  function recoverNearest(short, refLat, refLng) {
    const padLen = 8 - short.indexOf("+");
    const res = 20 ** (2 - padLen / 2);
    let { lat, lng } = decode(
      prefixOf(refLat, refLng, padLen) + short.toUpperCase(),
    );

    if (lat - refLat > res / 2) lat -= res;
    else if (lat - refLat < -res / 2) lat += res;
    if (lng - refLng > res / 2) lng -= res;
    else if (lng - refLng < -res / 2) lng += res;

    return { lat, lng };
  }

  // --- Geocode bertahap: "Desa, Kab, Prov" -> "Kab, Prov" -> "Prov" ---
  async function geocodeLocality(name) {
    const parts = name
      .replace(/\.$/, "")
      .split(",")
      .map((s) => s.trim())
      .filter(Boolean);

    for (let i = 0; i < parts.length; i++) {
      if (i > 0) await sleep(1100); // patuhi rate limit Nominatim (1 req/detik)

      const q = parts.slice(i).join(", ");
      // countrycodes=id: batasi ke Indonesia, hapus kalau perangkat ada di negara lain
      const url =
        "https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=id&q=" +
        encodeURIComponent(q);

      const res = await fetch(url, { headers: { Accept: "application/json" } });
      if (!res.ok)
        throw new Error("Layanan pencarian lokasi sedang bermasalah.");

      const [hit] = await res.json();
      if (hit) return { lat: parseFloat(hit.lat), lng: parseFloat(hit.lon) };
    }
    throw new Error(`Lokasi "${name}" tidak ditemukan.`);
  }

  const fmt = ({ lat, lng }) => `${lat.toFixed(7)}, ${lng.toFixed(7)}`;

  function setBusy(state) {
    busy = state;
    input.readOnly = state;
  }

  async function convert() {
    if (busy) return;
    const value = input.value.trim();
    input.setCustomValidity("");

    try {
      if (GLOBAL_RE.test(value)) {
        input.value = fmt(decode(value));
      } else if (SHORT_RE.test(value)) {
        const [, code, locality] = value.match(SHORT_RE);
        setBusy(true);
        const ref = await geocodeLocality(locality);
        input.value = fmt(recoverNearest(code, ref.lat, ref.lng));
      } else if (SHORT_ONLY_RE.test(value)) {
        throw new Error(
          "Kode pendek butuh nama daerah. Contoh: V298+R3W Kabupaten Wajo",
        );
      }
      // selain itu: biarkan, validasi pattern bawaan HTML yang jalan
    } catch (err) {
      setBusy(false); // harus sebelum reportValidity (readOnly dilewati validasi)
      input.setCustomValidity(err.message);
      input.reportValidity();
    } finally {
      setBusy(false);
    }
  }

  // Paste: ambil baris yang berisi Plus Code, abaikan baris alamat
  input.addEventListener("paste", (e) => {
    const text = e.clipboardData?.getData("text") ?? "";
    const line = text
      .split(/\r?\n/)
      .map((s) => s.trim())
      .find(
        (l) => GLOBAL_RE.test(l) || SHORT_RE.test(l) || SHORT_ONLY_RE.test(l),
      );

    if (!line) return; // paste biasa ("lat, lng"), biarkan default
    e.preventDefault();
    input.value = line;
    convert();
  });

  input.addEventListener("change", convert);
  input.addEventListener("input", () => input.setCustomValidity(""));
})();
