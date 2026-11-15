<?php
// controllers/TransaksiController.php
// Controller Utama Point of Sales (POS) Kasir & Tagihan Warkop KolegaOps (Final Bersih)

require_once BASE_PATH . '/models/MenuModel.php';
require_once BASE_PATH . '/models/TransaksiModel.php';
require_once BASE_PATH . '/models/ShiftModel.php';
require_once BASE_PATH . '/models/StokBahanModel.php';

class TransaksiController
{
    private PDO $pdo;
    private MenuModel $menuModel;
    private TransaksiModel $transaksiModel;
    private ShiftModel $shiftModel;
    private StokBahanModel $stokBahanModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo            = $pdo;
        $this->menuModel      = new MenuModel($pdo);
        $this->transaksiModel = new TransaksiModel($pdo);
        $this->shiftModel     = new ShiftModel($pdo);
        $this->stokBahanModel = new StokBahanModel($pdo);
    }

    /**
     * Menampilkan Halaman Utama Point of Sales (POS) Kasir
     */
    public function pos(): void
    {
        require_role(['pegawai', 'kasir']);

        $idUser         = (int) (current_user('id_user') ?? 0);
        $namaKasir      = (string) (current_user('username') ?? $_SESSION['user']['username'] ?? $_SESSION['user']['nama'] ?? 'Kasir');
        $namaKasirLabel = ucfirst($namaKasir);
        $tanggalTeks    = format_tanggal(date('Y-m-d'));

        $shiftAktif = $this->shiftModel->getShiftKasirAktif($idUser);
        if (!$shiftAktif) {
            $jamSekarang       = (int) date('H');
            $namaShiftDefault = ($jamSekarang < 15) ? 'Pagi' : 'Sore';
            $this->shiftModel->bukaShiftBaru($idUser, $namaShiftDefault);
            $shiftAktif       = $this->shiftModel->getShiftKasirAktif($idUser);
        }
        $namaShift = $shiftAktif ? (string) $shiftAktif['nama_shift'] : 'Sore';
        $idShift   = $shiftAktif ? (int) $shiftAktif['id_shift'] : 0;

        $daftarMenu     = $this->menuModel->getAll();
        $daftarKategori = $this->menuModel->getKategoriAktif();

        $pesanSukses = get_flash('success');
        $pesanError  = get_flash('error');

        require_once BASE_PATH . '/views/pegawai/kasir.php';
    }

    /**
     * Memproses Penyimpanan Transaksi Penjualan Baru ke Database PostgreSQL
     */
    public function prosesCheckout(): void
    {
        require_role(['pegawai', 'kasir']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=transaksi&action=pos');
            exit;
        }

        if (isset($_POST['csrf_token']) && function_exists('csrf_verify')) {
            csrf_verify($_POST['csrf_token']);
        }

        $idUser = (int) (current_user('id_user') ?? 0);

        $shiftAktif = $this->shiftModel->getShiftKasirAktif($idUser);
        if (!$shiftAktif) {
            $jamSekarang       = (int) date('H');
            $namaShiftDefault = ($jamSekarang < 15) ? 'Pagi' : 'Sore';
            $this->shiftModel->bukaShiftBaru($idUser, $namaShiftDefault);
            $shiftAktif       = $this->shiftModel->getShiftKasirAktif($idUser);
        }
        $idShift = $shiftAktif ? (int) $shiftAktif['id_shift'] : 0;

        $itemsJson = trim($_POST['items_json'] ?? '');
        $itemsRaw  = [];
        if ($itemsJson !== '') {
            $decoded = json_decode($itemsJson, true);
            if (is_array($decoded)) {
                $itemsRaw = $decoded;
            }
        }

        if (empty($itemsRaw)) {
            set_flash('error', 'Keranjang belanja kosong! Silakan pilih menu terlebih dahulu.');
            header('Location: index.php?controller=transaksi&action=pos');
            exit;
        }

        $itemsBersih     = [];
        $subtotalServer  = 0.0;
        $totalItemServer = 0;

        foreach ($itemsRaw as $item) {
            $idMenu = (int) ($item['id_menu'] ?? $item['id'] ?? 0);
            $qty    = max(1, (int) ($item['qty'] ?? $item['jumlah'] ?? 1));
            $harga  = (float) ($item['harga'] ?? $item['harga_satuan'] ?? 0);
            $sub    = $harga * $qty; // DIPERBAIKI: Menggunakan operator perkalian yang benar

            $subtotalServer  += $sub;
            $totalItemServer += $qty;

            $itemsBersih[] = [
                'id_menu'      => $idMenu,
                'jumlah'       => $qty,
                'harga_satuan' => $harga,
                'subtotal'     => $sub,
                'catatan_item' => !empty($item['catatan']) ? htmlspecialchars(trim($item['catatan']), ENT_QUOTES, 'UTF-8') : null,
            ];
        }

        $metodeRaw   = strtolower(trim((string) ($_POST['metode_pembayaran'] ?? 'cash')));
        $metodeBayar = ($metodeRaw === 'qris') ? 'qris' : 'tunai';
        $diskon      = max(0, (float) ($_POST['diskon'] ?? 0));
        $total       = max(0, $subtotalServer - $diskon); // DIPERBAIKI: Menggunakan operator minus yang benar

        $nominalBayar = ($metodeBayar === 'qris') 
            ? $total 
            : max(0, (float) ($_POST['nominal_bayar'] ?? $total));

        if ($metodeBayar === 'tunai' && $nominalBayar < $total) {
            set_flash('error', 'Uang yang diterima kurang dari total tagihan pesanan!');
            header('Location: index.php?controller=transaksi&action=pos');
            exit;
        }

        $kembalian = max(0, $nominalBayar - $total); // DIPERBAIKI: Menggunakan operator minus yang benar

        $dataTransaksi = [
            'id_user'           => $idUser,
            'id_shift'          => $idShift,
            'nama_customer'     => !empty($_POST['nama_customer']) ? trim($_POST['nama_customer']) : null,
            'total_item'        => $totalItemServer,
            'subtotal'          => $subtotalServer,
            'diskon'            => $diskon,
            'total'             => $total,
            'metode_pembayaran' => $metodeBayar,
            'nominal_bayar'     => $nominalBayar,
            'kembalian'         => $kembalian,
            'catatan_transaksi' => !empty($_POST['catatan_transaksi']) ? trim($_POST['catatan_transaksi']) : null,
            'items'             => $itemsBersih,
        ];

        $hasil = $this->transaksiModel->simpanTransaksi($dataTransaksi);

        if ($hasil['success']) {
            $_SESSION['last_transaksi_id'] = $hasil['id_transaksi'];
            set_flash('success', "Transaksi {$hasil['kode_transaksi']} berhasil diproses dan stok bahan baku otomatis terpotong!");
            header('Location: index.php?controller=transaksi&action=tagihan&id=' . $hasil['id_transaksi']);
        } else {
            set_flash('error', 'Gagal memproses transaksi: ' . ($hasil['error'] ?? 'Terjadi kesalahan sistem.'));
            header('Location: index.php?controller=transaksi&action=pos');
        }
        exit;
    }

    /**
     * Menampilkan Halaman Tagihan & Pratinjau Struk
     */
    public function tagihan(): void
    {
        require_role(['pegawai', 'kasir']);

        $idTransaksi = (int) ($_GET['id'] ?? $_SESSION['last_transaksi_id'] ?? 0);

        if ($idTransaksi <= 0) {
            set_flash('error', 'Tidak ada data transaksi yang dapat ditampilkan.');
            header('Location: index.php?controller=transaksi&action=pos');
            exit;
        }

        $transaksi = $this->transaksiModel->getDetailTransaksi($idTransaksi);

        if (!$transaksi) {
            set_flash('error', 'Data transaksi tidak ditemukan di database.');
            header('Location: index.php?controller=transaksi&action=pos');
            exit;
        }

        $namaKasirLabel = ucfirst((string) ($transaksi['nama_kasir'] ?? current_user('username') ?? 'Kasir'));
        $tanggalTeks    = format_tanggal((string) ($transaksi['tanggal'] ?? date('Y-m-d')));
        $waktuTeks      = (string) ($transaksi['waktu'] ?? date('H:i'));

        require_once BASE_PATH . '/views/pegawai/tagihan.php';
    }

    public function index(): void
    {
        $this->pos();
    }
}