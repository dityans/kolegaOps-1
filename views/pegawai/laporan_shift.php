
<?php
// views/pegawai/laporan_shift.php
// Tampilan Laporan Shift Pegawai & Rekapitulasi Kasir Berjalan - KolegaOps POS Warkop
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Laporan Shift Pegawai - KolegaOps Warkop Kolega</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              navy: '#122B70',
              darkNavy: '#0A1B46',
              lightNavy: '#1D3B8A',
              skyBlue: '#38BDF8',
              bgLight: '#F8FAFC'
            }
          }
        }
      }
    }
  </script>
</head>
<body class="bg-brand-bgLight text-slate-800 min-h-screen flex text-base relative">

  <?php require_once BASE_PATH . '/views/layouts/sidebar.php'; ?>

  <main class="flex-1 p-8 overflow-y-auto min-h-screen">

    <header class="flex items-center justify-between pb-6 border-b border-slate-200 mb-6 bg-white px-8 py-6 rounded-2xl shadow-sm">
      <div>
        <h2 class="text-3xl font-extrabold text-slate-900">Laporan Shift Pegawai</h2>
        <p class="text-sm text-slate-500 mt-1">
          Rekapitulasi transaksi &amp; pengeluaran khusus shift aktif Anda &bull;
          <strong class="text-slate-700"><?= htmlspecialchars($namaKasirLabel ?? 'Kasir') ?></strong>
          (<?= htmlspecialchars($namaShiftAktif ?? 'Shift Berjalan') ?>)
        </p>
      </div>
      <div class="text-xs font-semibold text-slate-400">
        Diperbarui <?= htmlspecialchars($waktuUpdate ?? date('H:i')) ?> WIB
      </div>
    </header>

    <!-- Statistik Shift -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">

      <div class="bg-brand-navy text-white p-6 rounded-2xl shadow-sm flex flex-col justify-between">
        <p class="text-xs font-bold text-blue-200 uppercase tracking-wider">TOTAL TRANSAKSI (SHIFT BERJALAN)</p>
        <div class="my-2">
          <h3 class="text-4xl font-extrabold">
            <?= (int) ($jumlahTrxFilter ?? 0) ?>
            <span class="text-lg font-normal">Struk</span>
          </h3>
        </div>
        <p class="text-xs text-blue-200 truncate">
          <?= htmlspecialchars($filterTanggalTeks ?? '') ?> &bull;
          <?= htmlspecialchars($namaShiftAktif ?? 'Shift') ?>
        </p>
      </div>

      <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">TOTAL PENJUALAN (OMZET SHIFT INI)</p>
        <div class="my-2">
          <h3 class="text-3xl font-extrabold text-brand-navy">
            Rp <?= number_format((float) ($totalOmzetFilter ?? 0), 0, ',', '.') ?>
          </h3>
        </div>
        <p class="text-xs text-slate-500">Total kas kotor diterima (Cash + QRIS)</p>
      </div>

      <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">PENJUALAN &ndash; PENGELUARAN</p>
        <div class="my-2">
          <h3 class="text-3xl font-extrabold <?= ($labaBersih ?? 0) >= 0 ? 'text-brand-navy' : 'text-red-600' ?>">
            Rp <?= number_format((float) ($labaBersih ?? 0), 0, ',', '.') ?>
          </h3>
        </div>
        <p class="text-xs text-slate-500">
          Setelah beban operasional shift (Rp <?= number_format((float) ($totalPengeluaran ?? 0), 0, ',', '.') ?>)
        </p>
      </div>

    </div>

    <!-- Filter -->
    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-6 items-center">

      <div class="md:col-span-4">
        <input
          type="text"
          id="searchTrx"
          placeholder="Cari nomor transaksi shift ini..."
          class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3.5 text-sm focus:outline-none focus:border-brand-skyBlue shadow-sm"
        >
      </div>

      <div class="md:col-span-3">
        <input
          type="date"
          value="<?= htmlspecialchars((string) ($filterTanggal ?? date('Y-m-d'))) ?>"
          disabled
          title="Filter tanggal dikunci khusus untuk sesi shift hari ini"
          class="w-full bg-slate-100 border border-slate-200 rounded-xl px-4 py-3.5 text-sm text-slate-500 font-medium cursor-not-allowed"
        >
      </div>

      <div class="md:col-span-2">
        <select
          disabled
          title="Filter shift dikunci khusus untuk sesi Anda yang sedang aktif"
          class="w-full bg-slate-100 border border-slate-200 rounded-xl px-4 py-3.5 text-sm text-slate-500 font-medium cursor-not-allowed"
        >
          <option selected><?= htmlspecialchars($namaShiftAktif ?? 'Shift Aktif') ?></option>
        </select>
      </div>

      <div class="md:col-span-1">
        <div class="w-full bg-blue-50 border border-blue-200 text-brand-navy py-3 rounded-xl font-bold text-xs flex items-center justify-center">
          <span class="truncate">Shift Aktif</span>
        </div>
      </div>

      <div class="md:col-span-2 text-right">
        <button
          type="button"
          onclick="cetakLaporanExcel()"
          class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-3.5 px-4 rounded-xl font-bold text-sm shadow transition cursor-pointer flex items-center justify-center space-x-2"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
            <path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6"/>
            <rect x="6" y="14" width="12" height="8" rx="1"/>
          </svg>
          <span>Cetak Laporan</span>
        </button>
      </div>

    </div>

    <!-- Rekapitulasi Pembayaran dan Pengeluaran -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">

      <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
          <h3 class="font-bold text-slate-900 text-base">Rekapitulasi Shift Ini</h3>
          <span class="text-xs text-slate-500 font-semibold">
            <?= htmlspecialchars($namaShiftAktif ?? 'Shift') ?> &bull;
            <?= htmlspecialchars($namaKasirLabel ?? 'Kasir') ?>
          </span>
        </div>

        <table class="w-full text-left text-sm">
          <thead class="bg-brand-darkNavy text-white">
            <tr>
              <th class="p-3.5 pl-6">KOMPONEN PEMBAYARAN</th>
              <th class="p-3.5 pr-6 text-right">NOMINAL</th>
            </tr>
          </thead>
          <tbody class="font-medium text-slate-800 divide-y divide-slate-100">
            <tr class="bg-white">
              <td class="p-3.5 pl-6 text-brand-navy font-bold">Total Cash Masuk (Tunai)</td>
              <td class="p-3.5 pr-6 text-right font-bold">
                Rp <?= number_format((float) ($totalCash ?? 0), 0, ',', '.') ?>
              </td>
            </tr>
            <tr class="bg-blue-100/70">
              <td class="p-3.5 pl-6 text-brand-navy font-bold">Total QRIS Masuk</td>
              <td class="p-3.5 pr-6 text-right font-bold">
                Rp <?= number_format((float) ($totalQris ?? 0), 0, ',', '.') ?>
              </td>
            </tr>
            <tr class="bg-slate-50 font-bold">
              <td class="p-3.5 pl-6 text-slate-900">Total Omzet Shift Ini</td>
              <td class="p-3.5 pr-6 text-right text-brand-navy">
                Rp <?= number_format((float) ($totalOmzetFilter ?? 0), 0, ',', '.') ?>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between">
        <div>
          <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="font-bold text-slate-900 text-base">Pengeluaran Operasional Shift</h3>
            <span class="text-xs text-slate-400 font-semibold">
              <?= count($pengeluaranList ?? []) ?> pos biaya
            </span>
          </div>

          <table class="w-full text-left text-sm">
            <thead class="bg-brand-darkNavy text-white">
              <tr>
                <th class="p-3.5 pl-6">KEPERLUAN</th>
                <th class="p-3.5 pr-6 text-right">NOMINAL</th>
              </tr>
            </thead>
            <tbody class="font-medium text-slate-800 divide-y divide-slate-100">
              <?php if (empty($pengeluaranList)): ?>
                <tr>
                  <td colspan="2" class="p-4 pl-6 text-slate-400 italic text-center">
                    Belum ada catatan pengeluaran pada shift ini.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($pengeluaranList as $idx => $p):
                  $bgRow = ($idx % 2 === 1) ? 'bg-blue-100/70' : 'bg-white';
                ?>
                  <tr class="<?= $bgRow ?>">
                    <td class="p-3.5 pl-6 text-slate-700">
                      <?= htmlspecialchars($p['keterangan'] ?? '-') ?>
                      <?php if (!empty($p['waktu_catat'])): ?>
                        <span class="text-[10px] text-slate-400 font-normal">
                          (<?= date('H:i', strtotime($p['waktu_catat'])) ?>)
                        </span>
                      <?php endif; ?>
                    </td>
                    <td class="p-3.5 pr-6 text-right font-medium">
                      Rp <?= number_format((float) ($p['nominal'] ?? 0), 0, ',', '.') ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="p-3.5 px-6 bg-slate-50 border-t border-slate-100 flex justify-between items-center font-bold text-sm">
          <span class="text-slate-900">Total Pengeluaran Shift</span>
          <span class="text-red-600">
            Rp <?= number_format((float) ($totalPengeluaran ?? 0), 0, ',', '.') ?>
          </span>
        </div>
      </div>

    </div>

    <!-- Riwayat Transaksi -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-6">

      <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
        <h3 class="font-bold text-slate-900 text-base">Riwayat Transaksi Shift Berjalan</h3>
        <span class="text-xs text-slate-400 font-semibold">
          Klik "Detail / Struk" untuk melihat rincian item
        </span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm" id="tabelRiwayat">
          <thead class="bg-brand-darkNavy text-white">
            <tr>
              <th class="p-4 pl-6">NOMOR TRANSAKSI</th>
              <th class="p-4">WAKTU &amp; TANGGAL</th>
              <th class="p-4">SHIFT &amp; PEGAWAI</th>
              <th class="p-4">METODE</th>
              <th class="p-4">TOTAL</th>
              <th class="p-4">STATUS</th>
              <th class="p-4 pr-6 text-right">AKSI</th>
            </tr>
          </thead>

          <tbody id="tbodyRiwayat" class="font-medium text-slate-800 divide-y divide-slate-100">
            <?php if (!empty($filteredTransaksi)): ?>
              <?php foreach ($filteredTransaksi as $index => $trx):
                $bgRow = ($index % 2 === 1) ? 'bg-blue-100/70' : 'bg-white';
              ?>
                <tr
                  class="trx-row <?= $bgRow ?> hover:bg-blue-50/50 transition"
                  data-search="<?= htmlspecialchars(strtolower(
                    ($trx['id'] ?? '') . ' ' .
                    ($trx['waktu'] ?? '') . ' ' .
                    ($trx['tgl_teks'] ?? '') . ' ' .
                    ($trx['shift'] ?? '') . ' ' .
                    ($trx['metode'] ?? '') . ' ' .
                    ($trx['status'] ?? '')
                  ), ENT_QUOTES, 'UTF-8') ?>"
                >
                  <td class="p-4 pl-6 font-bold text-brand-navy">
                    <?= htmlspecialchars($trx['id'] ?? '-') ?>
                  </td>
                  <td class="p-4 text-slate-600">
                    <?= htmlspecialchars($trx['waktu'] ?? '-') ?> &bull;
                    <span class="text-xs text-slate-500 font-semibold">
                      <?= htmlspecialchars($trx['tgl_teks'] ?? '-') ?>
                    </span>
                  </td>
                  <td class="p-4 text-slate-700 font-semibold">
                    <?= htmlspecialchars($trx['shift'] ?? '-') ?>
                  </td>
                  <td class="p-4 text-slate-600">
                    <span class="px-2.5 py-1 rounded-md text-xs font-bold <?= ($trx['metode'] ?? '') === 'QRIS' ? 'bg-purple-100 text-purple-700' : 'bg-amber-100 text-amber-800' ?>">
                      <?= htmlspecialchars($trx['metode'] ?? '-') ?>
                    </span>
                  </td>
                  <td class="p-4 font-bold text-emerald-600">
                    Rp <?= number_format((float) ($trx['total'] ?? 0), 0, ',', '.') ?>
                  </td>
                  <td class="p-4">
                    <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-lg font-bold text-xs inline-block">
                      <?= htmlspecialchars($trx['status'] ?? '-') ?>
                    </span>
                  </td>
                  <td class="p-4 pr-6 text-right">
                    <button
                      type="button"
                      onclick='bukaStruk(<?= json_encode($trx, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                      class="text-brand-navy font-bold hover:underline bg-transparent border-none cursor-pointer inline-flex items-center space-x-1"
                    >
                      <span>Detail / Struk</span>
                      <span>&rarr;</span>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr id="barisKosong">
                <td colspan="7" class="p-8 text-center text-slate-400 font-medium italic">
                  Belum ada transaksi yang tercatat pada sesi shift Anda saat ini.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Navigasi Pagination -->
      <div class="px-6 py-4 bg-slate-50/50 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-4 text-sm">
        <span id="infoPagination" class="text-slate-500 font-medium">
          Menampilkan 0 dari 0 transaksi
        </span>

        <div class="flex items-center gap-2">
          <button
            type="button"
            id="btnSebelumnya"
            onclick="ubahHalaman(-1)"
            class="px-4 py-2 rounded-lg border border-slate-200 bg-white text-slate-600 font-semibold hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed"
          >
            Sebelumnya
          </button>

          <span id="nomorHalaman" class="px-3 py-2 font-bold text-brand-navy">
            1 / 1
          </span>

          <button
            type="button"
            id="btnBerikutnya"
            onclick="ubahHalaman(1)"
            class="px-4 py-2 rounded-lg border border-slate-200 bg-white text-slate-600 font-semibold hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed"
          >
            Berikutnya
          </button>
        </div>
      </div>

    </div>

  </main>

  <!-- Modal Detail Struk -->
  <div id="modalStruk" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-slate-200">
      <div class="bg-brand-darkNavy text-white px-6 py-4 flex justify-between items-center">
        <div>
          <h3 class="font-extrabold text-base">Struk Transaksi Warkop Kolega</h3>
          <p id="modalId" class="text-xs text-blue-300 font-mono mt-0.5"></p>
        </div>
        <button
          type="button"
          onclick="tutupStruk()"
          class="text-slate-300 hover:text-white font-bold text-2xl leading-none cursor-pointer focus:outline-none"
        >&times;</button>
      </div>

      <div class="p-6 space-y-4">
        <div class="border-b border-dashed border-slate-300 pb-3 text-xs text-slate-500 space-y-1.5">
          <div class="flex justify-between">
            <span>Waktu:</span>
            <span id="modalWaktu" class="font-semibold text-slate-800"></span>
          </div>
          <div class="flex justify-between">
            <span>Shift / Pegawai:</span>
            <span id="modalShift" class="font-semibold text-slate-800"></span>
          </div>
          <div class="flex justify-between">
            <span>Metode Bayar:</span>
            <span id="modalMetode" class="font-semibold text-slate-800"></span>
          </div>
          <div class="flex justify-between items-center">
            <span>Status:</span>
            <span id="modalStatus" class="font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded text-[11px]"></span>
          </div>
        </div>

        <div>
          <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
            Rincian Item Pembelian
          </h4>
          <div id="modalItemsList" class="space-y-2 max-h-48 overflow-y-auto pr-1"></div>
        </div>

        <div class="border-t border-slate-200 pt-3 flex justify-between items-center">
          <span class="font-bold text-slate-700 text-sm">TOTAL PEMBAYARAN</span>
          <span id="modalTotal" class="font-extrabold text-brand-navy text-xl"></span>
        </div>
      </div>

      <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex justify-end space-x-3">
        <button
          type="button"
          onclick="tutupStruk()"
          class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-5 py-2.5 rounded-xl font-bold text-xs transition cursor-pointer"
        >
          Tutup
        </button>
        <button
          type="button"
          onclick="window.print()"
          class="bg-brand-navy hover:bg-brand-lightNavy text-white px-5 py-2.5 rounded-xl font-bold text-xs transition cursor-pointer"
        >
          Cetak Struk
        </button>
      </div>
    </div>
  </div>

  <script>
    // Pagination riwayat transaksi: 10 baris per halaman.
    const jumlahPerHalaman = 10;
    let halamanAktif = 1;
    let transaksiTerfilter = [];

    const semuaBarisTransaksi = Array.from(
      document.querySelectorAll('.trx-row')
    );

    const inputPencarian = document.getElementById('searchTrx');
    const infoPagination = document.getElementById('infoPagination');
    const nomorHalaman = document.getElementById('nomorHalaman');
    const btnSebelumnya = document.getElementById('btnSebelumnya');
    const btnBerikutnya = document.getElementById('btnBerikutnya');

    function tampilkanHalaman() {
      const totalData = transaksiTerfilter.length;
      const totalHalaman = Math.max(1, Math.ceil(totalData / jumlahPerHalaman));

      if (halamanAktif > totalHalaman) {
        halamanAktif = totalHalaman;
      }

      const indeksAwal = (halamanAktif - 1) * jumlahPerHalaman;
      const indeksAkhir = indeksAwal + jumlahPerHalaman;

      semuaBarisTransaksi.forEach(function(baris) {
        baris.style.display = 'none';
      });

      transaksiTerfilter.forEach(function(baris, indeks) {
        baris.style.display =
          indeks >= indeksAwal && indeks < indeksAkhir ? '' : 'none';
      });

      const mulai = totalData === 0 ? 0 : indeksAwal + 1;
      const selesai = Math.min(indeksAkhir, totalData);

      infoPagination.textContent =
        'Menampilkan ' + mulai + '-' + selesai + ' dari ' + totalData + ' transaksi';

      nomorHalaman.textContent = halamanAktif + ' / ' + totalHalaman;

      btnSebelumnya.disabled = halamanAktif <= 1;
      btnBerikutnya.disabled = halamanAktif >= totalHalaman;

      let barisKosong = document.getElementById('barisKosong');

      if (barisKosong) {
        barisKosong.style.display = totalData === 0 ? '' : 'none';
      }

      let pesanPencarian = document.getElementById('pesanPencarian');

      if (totalData === 0 && semuaBarisTransaksi.length > 0) {
        if (!pesanPencarian) {
          pesanPencarian = document.createElement('tr');
          pesanPencarian.id = 'pesanPencarian';
          pesanPencarian.innerHTML =
            '<td colspan="7" class="p-8 text-center text-slate-400 font-medium italic">Transaksi tidak ditemukan.</td>';
          document.getElementById('tbodyRiwayat').appendChild(pesanPencarian);
        }
        pesanPencarian.style.display = '';
      } else if (pesanPencarian) {
        pesanPencarian.style.display = 'none';
      }
    }

    function filterTransaksi() {
      const keyword = inputPencarian.value.toLowerCase().trim();

      transaksiTerfilter = semuaBarisTransaksi.filter(function(baris) {
        const teks = baris.dataset.search || '';
        return teks.includes(keyword);
      });

      halamanAktif = 1;
      tampilkanHalaman();
    }

    function ubahHalaman(arah) {
      const totalHalaman = Math.max(
        1,
        Math.ceil(transaksiTerfilter.length / jumlahPerHalaman)
      );

      const halamanBaru = halamanAktif + arah;

      if (halamanBaru >= 1 && halamanBaru <= totalHalaman) {
        halamanAktif = halamanBaru;
        tampilkanHalaman();
      }
    }

    inputPencarian.addEventListener('input', filterTransaksi);

    // Inisialisasi tampilan awal.
    transaksiTerfilter = semuaBarisTransaksi.slice();
    tampilkanHalaman();

    // Membuka modal detail struk.
    function bukaStruk(trx) {
      document.getElementById('modalId').innerText = trx.id || '-';
      document.getElementById('modalWaktu').innerText =
        (trx.waktu || '-') + ' • ' + (trx.tgl_teks || '-');
      document.getElementById('modalShift').innerText = trx.shift || '-';
      document.getElementById('modalMetode').innerText = trx.metode || '-';
      document.getElementById('modalStatus').innerText = trx.status || '-';
      document.getElementById('modalTotal').innerText =
        'Rp ' + Number(trx.total || 0).toLocaleString('id-ID');

      const listContainer = document.getElementById('modalItemsList');
      listContainer.innerHTML = '';

      if (trx.items && trx.items.length > 0) {
        for (let i = 0; i < trx.items.length; i++) {
          const item = trx.items[i];

          const row = document.createElement('div');
          row.className =
            'flex justify-between items-center text-sm py-1.5 border-b border-slate-100';

          const namaItem = document.createElement('span');
          namaItem.className = 'text-slate-700 font-medium';
          namaItem.textContent =
            (item.nama || '-') + ' (' + (item.qty || 0) + 'x)';

          const subtotalItem = document.createElement('span');
          subtotalItem.className = 'text-slate-900 font-bold';
          subtotalItem.textContent =
            'Rp ' + Number(item.subtotal || 0).toLocaleString('id-ID');

          row.appendChild(namaItem);
          row.appendChild(subtotalItem);
          listContainer.appendChild(row);
        }
      } else {
        listContainer.innerHTML =
          '<p class="text-xs text-slate-400 italic py-2">Tidak ada rincian item pada transaksi ini.</p>';
      }

      const modal = document.getElementById('modalStruk');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }

    function tutupStruk() {
      const modal = document.getElementById('modalStruk');
      modal.classList.remove('flex');
      modal.classList.add('hidden');
    }

    window.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        tutupStruk();
      }
    });

    document.getElementById('modalStruk').addEventListener('click', function(e) {
      if (e.target === this) {
        tutupStruk();
      }
    });

    // Ekspor seluruh data transaksi yang sudah dimuat, bukan hanya halaman aktif.
    function cetakLaporanExcel() {
      const table = document.getElementById('tabelRiwayat');

      if (!table) {
        return;
      }

      const header = table.querySelector('thead').outerHTML;

      const barisData = semuaBarisTransaksi.map(function(baris) {
        return baris.outerHTML;
      }).join('');

      const html =
        '<html><head><meta charset="UTF-8"></head><body>' +
        '<table border="1">' +
        header +
        '<tbody>' + barisData + '</tbody>' +
        '</table></body></html>';

      const blob = new Blob([html], {
        type: 'application/vnd.ms-excel;charset=utf-8;'
      });

      const url = URL.createObjectURL(blob);
      const downloadLink = document.createElement('a');

      downloadLink.href = url;
      downloadLink.download =
        'Laporan-Shift-Warkop-Kolega-' +
        new Date().toISOString().slice(0, 10) +
        '.xls';

      document.body.appendChild(downloadLink);
      downloadLink.click();
      document.body.removeChild(downloadLink);

      URL.revokeObjectURL(url);
    }
  </script>

</body>
</html>
