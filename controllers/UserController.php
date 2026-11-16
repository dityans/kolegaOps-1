
<?php
// controllers/UserController.php
// Controller Pengelolaan Akun untuk KolegaOps POS Warkop
// Role: super_admin, admin, kasir

require_once BASE_PATH . '/models/UserModel.php';

class UserController
{
    private PDO $pdo;
    private UserModel $userModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->userModel = new UserModel($pdo);
    }

    // -------------------------------------------------------------------------
    // 1. Helper Role
    // -------------------------------------------------------------------------

    /**
     * Mengambil role pengguna yang sedang login.
     */
    private function getActorRole(): string
    {
        return normalize_role((string) current_user('role'));
    }

    /**
     * Mengubah input role dari form menjadi role database.
     */
    private function normalizeInputRole(string $role): string
    {
        $role = strtolower(trim($role));

        if (in_array($role, ['owner', 'admin'], true)) {
            return 'admin';
        }

        if (in_array($role, ['pegawai', 'kasir'], true)) {
            return 'kasir';
        }

        return $role;
    }

    /**
     * Mengarahkan kembali ke halaman Kelola Pegawai.
     */
    private function redirectIndex(): void
    {
        header('Location: index.php?controller=pegawai&action=index');
        exit;
    }

    /**
     * Memeriksa request POST dan token CSRF.
     */
    private function verifyPostRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirectIndex();
        }

        if (function_exists('csrf_verify')) {
            csrf_verify($_POST['csrf_token'] ?? '');
        }
    }

    // -------------------------------------------------------------------------
    // 2. Halaman Kelola Pegawai
    // -------------------------------------------------------------------------

    /**
     * Menampilkan daftar akun sesuai hak akses pengguna.
     *
     * Super Admin:
     * - Melihat akun Admin dan Pegawai.
     *
     * Admin:
     * - Hanya melihat akun Pegawai.
     */
    public function index(): void
    {
        require_role(['admin', 'owner']);

        $actorRole = $this->getActorRole();

        // Mengambil akun berdasarkan role pengguna yang login.
        $daftarPegawai = $this->userModel->getAllUsers($actorRole);

        // Statistik akun.
        $totalAkun = count($daftarPegawai);

        $totalAktif = count(array_filter(
            $daftarPegawai,
            fn($u) => ($u['status'] ?? '') === 'Aktif'
        ));

        $totalNonaktif = count(array_filter(
            $daftarPegawai,
            fn($u) => ($u['status'] ?? '') === 'Nonaktif'
        ));

        $totalAdmin = count(array_filter(
            $daftarPegawai,
            fn($u) => ($u['role'] ?? '') === 'Admin'
        ));

        $totalKasir = count(array_filter(
            $daftarPegawai,
            fn($u) => ($u['role'] ?? '') === 'Pegawai'
        ));

        // Untuk kompatibilitas dengan view lama.
        $totalOwner = $totalAdmin;

        // Daftar pengguna aktif.
        $namaAktifArr = array_column(
            array_filter(
                $daftarPegawai,
                fn($u) => ($u['status'] ?? '') === 'Aktif'
            ),
            'nama'
        );

        $namaAktifTeks = !empty($namaAktifArr)
            ? implode(', ', array_slice($namaAktifArr, 0, 4))
            : 'Belum ada akun aktif';

        // Informasi role yang sedang login untuk kebutuhan view.
        $isSuperAdmin = ($actorRole === 'super_admin');
        $isAdmin = ($actorRole === 'admin');

        // Notifikasi.
        $pesanSukses = get_flash('success');
        $pesanError = get_flash('error');

        require BASE_PATH . '/views/owner/kelola-pegawai.php';
    }

    // -------------------------------------------------------------------------
    // 3. Tambah dan Edit Akun
    // -------------------------------------------------------------------------

    /**
     * Memproses tambah akun baru atau memperbarui akun.
     */
    public function simpan(): void
    {
        require_role(['admin', 'owner']);

        $this->verifyPostRequest();

        $actorRole = $this->getActorRole();

        $idUser = (int) ($_POST['id_user'] ?? 0);
        $username = trim((string) ($_POST['username'] ?? ''));
        $role = $this->normalizeInputRole(
            (string) ($_POST['role'] ?? 'kasir')
        );
        $status = strtolower(trim(
            (string) ($_POST['status'] ?? 'aktif')
        ));
        $password = (string) ($_POST['password'] ?? '');

        // Validasi username.
        if ($username === '') {
            set_flash('error', 'Username tidak boleh kosong!');
            $this->redirectIndex();
        }

        if (strlen($username) > 50) {
            set_flash('error', 'Username maksimal terdiri dari 50 karakter!');
            $this->redirectIndex();
        }

        // Validasi role.
        if (!in_array($role, ['admin', 'kasir'], true)) {
            set_flash('error', 'Role akun yang dipilih tidak valid!');
            $this->redirectIndex();
        }

        // Admin hanya boleh mengelola Pegawai/Kasir.
        if ($actorRole === 'admin' && $role !== 'kasir') {
            set_flash(
                'error',
                'Admin hanya diperbolehkan mengelola akun Pegawai/Kasir!'
            );
            $this->redirectIndex();
        }

        // Validasi status.
        if (!in_array($status, ['aktif', 'nonaktif'], true)) {
            set_flash('error', 'Status akun tidak valid!');
            $this->redirectIndex();
        }

        // Periksa username duplikat.
        $existing = $this->userModel->findByUsername($username);

        if ($existing && (int) $existing['id_user'] !== $idUser) {
            set_flash(
                'error',
                "Username '{$username}' sudah digunakan oleh pengguna lain!"
            );
            $this->redirectIndex();
        }

        // ---------------------------------------------------------------------
        // MODE EDIT
        // ---------------------------------------------------------------------

        if ($idUser > 0) {

            $target = $this->userModel->findById($idUser);

            if (!$target) {
                set_flash('error', 'Akun yang akan diperbarui tidak ditemukan!');
                $this->redirectIndex();
            }

            $targetRole = normalize_role((string) ($target['role'] ?? ''));

            // Akun Super Admin tidak boleh diedit.
            if ($targetRole === 'super_admin') {
                set_flash(
                    'error',
                    'Akun Super Admin dilindungi dan tidak dapat diubah!'
                );
                $this->redirectIndex();
            }

            // Admin hanya dapat mengedit Pegawai.
            if (
                $actorRole === 'admin'
                && $targetRole !== 'kasir'
            ) {
                set_flash(
                    'error',
                    'Admin hanya dapat mengubah akun Pegawai/Kasir!'
                );
                $this->redirectIndex();
            }

            // Validasi password jika diisi.
            if ($password !== '' && strlen($password) < 4) {
                set_flash(
                    'error',
                    'Password akun minimal terdiri dari 4 karakter!'
                );
                $this->redirectIndex();
            }

            $dataUpdate = [
                'username' => $username,
                'role' => $role,
                'status' => $status,
                'password' => $password,
            ];

            $sukses = $this->userModel->updateUser(
                $idUser,
                $dataUpdate,
                $actorRole
            );

            if ($sukses) {
                set_flash(
                    'success',
                    "Akun '{$username}' berhasil diperbarui!"
                );
            } else {
                set_flash(
                    'error',
                    'Gagal memperbarui akun. Periksa kembali hak akses dan data yang dimasukkan.'
                );
            }

            $this->redirectIndex();
        }

        // ---------------------------------------------------------------------
        // MODE TAMBAH BARU
        // ---------------------------------------------------------------------

        if ($password === '') {
            set_flash(
                'error',
                'Password wajib diisi untuk membuat akun baru!'
            );
            $this->redirectIndex();
        }

        if (strlen($password) < 4) {
            set_flash(
                'error',
                'Password akun minimal terdiri dari 4 karakter!'
            );
            $this->redirectIndex();
        }

        // Role Super Admin tidak dapat dibuat melalui form Kelola Pegawai.
        if ($role === 'super_admin') {
            set_flash(
                'error',
                'Pembuatan akun Super Admin tidak diperbolehkan melalui form ini!'
            );
            $this->redirectIndex();
        }

        $dataBaru = [
            'username' => $username,
            'password' => $password,
            'role' => $role,
            'status' => $status,
        ];

        $newId = $this->userModel->simpanUser(
            $dataBaru,
            $actorRole
        );

        if ($newId) {
            set_flash(
                'success',
                "Akun baru '{$username}' berhasil ditambahkan!"
            );
        } else {
            set_flash(
                'error',
                'Gagal menambahkan akun. Periksa hak akses atau data yang dimasukkan.'
            );
        }

        $this->redirectIndex();
    }

    // -------------------------------------------------------------------------
    // 4. Menghapus Akun
    // -------------------------------------------------------------------------

    /**
     * Menghapus akun sesuai hak akses.
     *
     * Super Admin:
     * - Dapat menghapus Admin dan Pegawai.
     *
     * Admin:
     * - Hanya dapat menghapus Pegawai.
     *
     * Akun Super Admin tidak dapat dihapus.
     */
    public function hapus($id = null): void
    {
        require_role(['admin', 'owner']);

        $id = (int) ($id ?? $_GET['id'] ?? 0);

        $actorRole = $this->getActorRole();

        if ($id <= 0) {
            set_flash('error', 'ID akun tidak valid!');
            $this->redirectIndex();
        }

        // Cegah menghapus akun sendiri.
        $currentUserId = (int) (current_user('id_user') ?? 0);

        if ($id === $currentUserId) {
            set_flash(
                'error',
                'Anda tidak dapat menghapus akun sendiri yang sedang digunakan!'
            );
            $this->redirectIndex();
        }

        // Periksa akun target.
        $user = $this->userModel->findById($id);

        if (!$user) {
            set_flash('error', 'Akun yang akan dihapus tidak ditemukan!');
            $this->redirectIndex();
        }

        $targetRole = normalize_role((string) ($user['role'] ?? ''));

        // Lindungi akun Super Admin.
        if ($targetRole === 'super_admin') {
            set_flash(
                'error',
                'Akun Super Admin dilindungi dan tidak dapat dihapus!'
            );
            $this->redirectIndex();
        }

        // Admin tidak boleh menghapus Admin.
        if ($actorRole === 'admin' && $targetRole !== 'kasir') {
            set_flash(
                'error',
                'Admin hanya diperbolehkan menghapus akun Pegawai/Kasir!'
            );
            $this->redirectIndex();
        }

        $username = $user['username'] ?? 'Pengguna';

        $berhasil = $this->userModel->hapusUser(
            $id,
            $actorRole
        );

        if ($berhasil) {
            set_flash(
                'success',
                "Akun '{$username}' berhasil dihapus."
            );
        } else {
            set_flash(
                'error',
                'Gagal menghapus akun. Akun mungkin masih memiliki riwayat transaksi atau shift. Sebaiknya ubah statusnya menjadi Nonaktif.'
            );
        }

        $this->redirectIndex();
    }
}
