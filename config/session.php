
<?php
// config/session.php
// Manajemen Sesi Login & Kontrol Akses KolegaOps
// Role: super_admin, admin, kasir

// -----------------------------------------------------------------------------
// 1. Inisialisasi Sesi Secara Aman
// -----------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {

    $savePath = session_save_path();

    if (empty($savePath) || !is_writable($savePath)) {
        $sysTemp = sys_get_temp_dir();

        if (is_writable($sysTemp)) {
            session_save_path($sysTemp);
        }
    }

    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    );

    @session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => $isHttps,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => 1,
    ]);
}

// -----------------------------------------------------------------------------
// 2. Normalisasi Role
// -----------------------------------------------------------------------------
if (!function_exists('normalize_role')) {

    /**
     * Menyeragamkan nama role agar konsisten.
     *
     * owner lama akan dianggap sebagai admin.
     * pegawai lama akan dianggap sebagai kasir.
     */
    function normalize_role(?string $role): string
    {
        $role = strtolower(trim((string) $role));

        if ($role === 'owner') {
            return 'admin';
        }

        if ($role === 'pegawai') {
            return 'kasir';
        }

        return $role;
    }
}

// -----------------------------------------------------------------------------
// 3. Fungsi Login: set_user_session
// -----------------------------------------------------------------------------
if (!function_exists('set_user_session')) {

    /**
     * Menyimpan data pengguna ke session setelah login berhasil.
     */
    function set_user_session(array $user): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $role = normalize_role($user['role'] ?? '');

        $_SESSION['user'] = [
            'id_user'  => (int) ($user['id_user'] ?? $user['id'] ?? 0),
            'username' => trim((string) ($user['username'] ?? '')),
            'role'     => $role,
        ];

        // Shortcut session
        $_SESSION['id_user']  = $_SESSION['user']['id_user'];
        $_SESSION['username'] = $_SESSION['user']['username'];
        $_SESSION['role']     = $role;
    }
}

// -----------------------------------------------------------------------------
// 4. Fungsi Cek Login: is_logged_in
// -----------------------------------------------------------------------------
if (!function_exists('is_logged_in')) {

    function is_logged_in(): bool
    {
        return (
            !empty($_SESSION['user']['id_user'])
            || !empty($_SESSION['id_user'])
        );
    }
}

// -----------------------------------------------------------------------------
// 5. Mengambil Data Pengguna: current_user
// -----------------------------------------------------------------------------
if (!function_exists('current_user')) {

    /**
     * Mengambil informasi pengguna yang sedang login.
     *
     * Contoh:
     * current_user()
     * current_user('username')
     * current_user('role')
     */
    function current_user(?string $key = null)
    {
        if (!is_logged_in()) {
            return null;
        }

        $user = $_SESSION['user'] ?? [
            'id_user'  => $_SESSION['id_user'] ?? null,
            'username' => $_SESSION['username'] ?? '',
            'role'     => $_SESSION['role'] ?? '',
        ];

        // Normalisasi role session lama
        $user['role'] = normalize_role($user['role'] ?? '');

        if ($key !== null) {
            return $user[$key] ?? null;
        }

        return $user;
    }
}

// -----------------------------------------------------------------------------
// 6. Fungsi Cek Login: require_login
// -----------------------------------------------------------------------------
if (!function_exists('require_login')) {

    function require_login(
        string $loginUrl = 'index.php?controller=auth&action=login'
    ): void {

        if (!is_logged_in()) {
            header("Location: {$loginUrl}");
            exit;
        }
    }
}

// -----------------------------------------------------------------------------
// 7. Pemeriksaan Hak Akses: require_role
// -----------------------------------------------------------------------------
if (!function_exists('require_role')) {

    /**
     * Membatasi akses berdasarkan role.
     *
     * Aturan:
     *
     * SUPER ADMIN:
     * - Bisa mengakses halaman khusus super_admin.
     * - Bisa mengakses halaman admin.
     * - Bisa mengakses halaman operasional.
     *
     * ADMIN:
     * - Bisa mengakses halaman admin.
     * - Bisa mengakses halaman operasional.
     * - Tidak bisa mengakses halaman khusus super_admin.
     *
     * KASIR:
     * - Hanya bisa mengakses halaman yang mengizinkan kasir.
     *
     * Contoh:
     * require_role('super_admin');
     * require_role(['admin', 'owner']);
     * require_role(['kasir', 'pegawai']);
     */
    function require_role($allowed_roles, ?string $redirectUrl = null): void
    {
        require_login();

        $allowed = is_array($allowed_roles)
            ? $allowed_roles
            : [$allowed_roles];

        $allowed = array_map(
            function ($role) {
                return normalize_role((string) $role);
            },
            $allowed
        );

        $userRole = normalize_role(
            (string) current_user('role')
        );

        // Super Admin memiliki hak akses Admin dan operasional.
        if (
            $userRole === 'super_admin'
            && (
                in_array('admin', $allowed, true)
                || in_array('owner', $allowed, true)
            )
        ) {
            return;
        }

        // Admin tidak otomatis mendapatkan hak Super Admin.
        if (in_array($userRole, $allowed, true)) {
            return;
        }

        // Akses ditolak.
        if ($redirectUrl !== null) {
            header("Location: {$redirectUrl}");
            exit;
        }

        http_response_code(403);

        echo '403 - Anda tidak memiliki hak akses untuk halaman ini.';
        exit;
    }
}

// -----------------------------------------------------------------------------
// 8. Helper Pengecekan Role
// -----------------------------------------------------------------------------
if (!function_exists('is_super_admin')) {

    function is_super_admin(): bool
    {
        return current_user('role') === 'super_admin';
    }
}

if (!function_exists('is_admin')) {

    function is_admin(): bool
    {
        return current_user('role') === 'admin';
    }
}

if (!function_exists('is_kasir')) {

    function is_kasir(): bool
    {
        return current_user('role') === 'kasir';
    }
}

// -----------------------------------------------------------------------------
// 9. Fungsi Logout: destroy_session
// -----------------------------------------------------------------------------
if (!function_exists('destroy_session')) {

    function destroy_session(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}

// -----------------------------------------------------------------------------
// 10. Flash Message
// -----------------------------------------------------------------------------
if (!function_exists('set_flash')) {

    function set_flash(string $key, string $message): void
    {
        $_SESSION['flash'][$key] = $message;
    }
}

if (!function_exists('get_flash')) {

    function get_flash(string $key): ?string
    {
        if (isset($_SESSION['flash'][$key])) {

            $message = $_SESSION['flash'][$key];

            unset($_SESSION['flash'][$key]);

            return $message;
        }

        return null;
    }
}
