(() => {
  const selector = "button, a.btn, .pagination .page-link";
  const icons = [
    ["Tutup", "xmark"],
    ["Dasbor", "gauge-high"],
    ["Peta", "map-location-dot"],
    ["Masuk", "right-to-bracket"],
    ["Keluar", "right-from-bracket"],
    ["Pengunjung", "eye"],
    ["Tambah", "plus"],
    ["Ubah lokasi", "location-dot"],
    ["Ubah", "pen-to-square"],
    ["Lihat detail", "chart-line"],
    ["Lihat", "eye"],
    ["Tampilkan di peta", "location-dot"],
    ["Perkecil", "magnifying-glass-minus"],
    ["Perbesar", "magnifying-glass-plus"],
    ["Muat ulang", "rotate-right"],
    ["Perbarui", "arrows-rotate"],
    ["Terapkan", "filter"],
    ["Atur ulang", "rotate-left"],
    ["Hari ini", "calendar-day"],
    ["Kembali", "arrow-left"],
    ["Simpan", "floppy-disk"],
    ["Menyimpan", "floppy-disk"],
    ["Unggah", "upload"],
    ["Sebelumnya", "chevron-left"],
    ["Berikutnya", "chevron-right"],
  ];
  function decorate(element) {
    if (!element.matches(selector) || element.querySelector("[data-button-icon]")) return;
    const label = element.textContent.trim();
    if (!label) return;
    const name = icons.find(([prefix]) => label.startsWith(prefix))?.[1] || "arrow-right";
    const icon = document.createElement("i");
    icon.className = `fa-solid fa-${name} flow-button-icon`;
    icon.dataset.buttonIcon = "";
    icon.setAttribute("aria-hidden", "true");
    element.prepend(icon);
  }
  function scan(root) {
    if (!(root instanceof Element)) return;
    decorate(root);
    root.querySelectorAll(selector).forEach(decorate);
  }
  scan(document.body);
  // Polling panels, map popups and saving labels can replace button markup.
  const observer = new MutationObserver((records) => {
    for (const record of records) {
      const button = record.target.parentElement?.closest(selector);
      if (button) decorate(button);
      if (record.target instanceof Element) decorate(record.target);
      for (const node of record.addedNodes) scan(node);
    }
  });
  observer.observe(document.body, { childList: true, subtree: true });
})();
