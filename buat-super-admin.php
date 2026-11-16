
<?php
// buat-super-admin.php
// Pembuatan akun Super Admin pertama KolegaOps

if (PHP_SAPI !== 'cli') {
    $alamat = $_SERVER['REMOTE_ADDR'] ?? '';

    if (!in_array($alamat, ['127.0.0.1', '::1'], true)) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
}

session_start();

if (empty($_SESSION['token_super_admin'])) {
    $_SESSION['token_super_admin'] = bin2hex(random_bytes(32));
}

$pesan = '';
$berhasil = false;
$pdo = null;

try {
    require __DIR__ . '/config/database.php';

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Koneksi database tidak tersedia.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['token'] ?? '';

        if (
            empty($_SESSION['token_super_admin']) ||
            !hash_equals($_SESSION['token_super_admin'], $token)
        ) {
            throw new RuntimeException('Permintaan tidak valid. Silakan muat ulang halaman.');
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $konfirmasi = $_POST['konfirmasi_password'] ?? '';

        if (strlen($username) < 3 || strlen($username) > 50) {
            throw new RuntimeException('Username harus terdiri dari 3 sampai 50 karakter.');
        }

        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            throw new RuntimeException('Username hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.');
        }

        if (strlen($password) < 8) {
            throw new RuntimeException('Password minimal 8 karakter.');
        }

        if ($password !== $konfirmasi) {
            throw new RuntimeException('Konfirmasi password tidak cocok.');
        }

        $pdo->beginTransaction();

        $cekUsername = $pdo->prepare(
            "SELECT COUNT(*) FROM users WHERE LOWER(username) = LOWER(:username)"
        );

        $cekUsername->execute([
            ':username' => $username
        ]);

        if ((int) $cekUsername->fetchColumn() > 0) {
            throw new RuntimeException('Username sudah digunakan.');
        }

        $cekSuperAdmin = $pdo->query(
            "SELECT COUNT(*) FROM users WHERE LOWER(role) = 'super_admin'"
        );

        if ((int) $cekSuperAdmin->fetchColumn() > 0) {
            throw new RuntimeException('Akun Super Admin sudah tersedia.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($passwordHash === false) {
            throw new RuntimeException('Password gagal diproses.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO users (
                username,
                password,
                role,
                status,
                create_at,
                update_at
            )
            VALUES (
                :username,
                :password,
                'super_admin',
                'aktif',
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ");

        $stmt->execute([
            ':username' => $username,
            ':password' => $passwordHash
        ]);

        $idBaru = (int) $pdo->lastInsertId('users_id_user_seq');

        $pdo->commit();

        $berhasil = true;
        $pesan = "Akun Super Admin berhasil dibuat.";

        unset($_SESSION['token_super_admin']);

    }

} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $pesan = $e->getMessage();
}

function eSuperAdmin(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Super Admin - KolegaOps Warkop Kolega</title>

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

<body class="bg-brand-darkNavy min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-4xl bg-white rounded-2xl shadow-2xl overflow-hidden grid grid-cols-1 md:grid-cols-12 min-h-[520px]">

        <!-- SISI KIRI: BRANDING -->
        <div class="md:col-span-5 bg-brand-navy p-8 text-white flex flex-col justify-between">

            <div>
                <div class="flex items-center space-x-2 text-2xl font-bold tracking-wide">
                    <h1>
                        <span class="text-white">Kolega</span><span class="text-brand-skyBlue">Ops</span>
                    </h1>
                </div>

                <p class="text-[11px] text-slate-300 tracking-widest font-semibold uppercase mt-1">
                    WARKOP KOLEGA
                </p>
            </div>

            <div class="my-auto py-8">
                <h2 class="text-2xl font-extrabold leading-tight">
                    Tiada Hari<br>Tanpa Ngopi
                </h2>

                <p class="text-xs text-slate-300 mt-2 leading-relaxed">
                    Sistem Pengelolaan Operasional terintegrasi untuk kasir, stok, dan laporan keuangan Warkop Kolega.
                </p>
            </div>

            <div class="text-[11px] text-slate-400">
                &copy; <?= date('Y') ?> Warkop Kolega. All rights reserved.
            </div>

        </div>

        <!-- SISI KANAN: FORM SUPER ADMIN -->
        <div class="md:col-span-7 p-8 md:p-10 flex flex-col justify-center bg-white">

            <div>
                <h3 class="text-2xl font-bold text-slate-900">
                    Buat Akun Super Admin
                </h3>

                <p class="text-xs text-slate-500 mt-1">
                    Buat akun utama untuk mengelola sistem KolegaOps.
                </p>
            </div>

            <!-- PESAN -->
            <?php if ($pesan !== ''): ?>
                <div class="mt-4 p-3 rounded-lg <?= $berhasil
                    ? 'bg-green-50 border border-green-200 text-green-700'
                    : 'bg-red-50 border border-red-200 text-red-600'
                ?> text-xs font-medium">
                    <?= eSuperAdmin($pesan) ?>
                </div>
            <?php endif; ?>

            <?php if ($berhasil): ?>

                <div class="mt-6 space-y-4">

                    <p class="text-xs text-slate-600 leading-relaxed">
                        Akun Super Admin berhasil dibuat dan sudah tersimpan di database.
                        Sekarang kamu dapat masuk menggunakan halaman login KolegaOps.
                    </p>

                    <a href="index.php?controller=auth&action=login"
                       class="block w-full rounded-lg bg-brand-navy py-3 text-center text-xs font-semibold text-white shadow-md hover:bg-brand-lightNavy transition">
                        Kembali ke Halaman Login
                    </a>

                    <div class="pt-4 border-t border-slate-100 text-center">
                        <p class="text-[11px] text-slate-400">
                            Hapus file buat-super-admin.php setelah proses pembuatan akun selesai.
                        </p>
                    </div>

                </div>

            <?php elseif ($pdo instanceof PDO): ?>

                <form method="POST" class="mt-6 space-y-4">

                    <input
                        type="hidden"
                        name="token"
                        value="<?= eSuperAdmin($_SESSION['token_super_admin']) ?>"
                    >

                    <!-- USERNAME -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Username Super Admin
                        </label>

                        <input
                            type="text"
                            name="username"
                            required
                            minlength="3"
                            maxlength="50"
                            autocomplete="username"
                            placeholder="Masukkan username"
                            class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-xs focus:border-brand-skyBlue focus:ring-1 focus:ring-brand-skyBlue outline-none transition"
                        >
                    </div>

                    <!-- PASSWORD -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Password
                        </label>

                        <div class="relative">
                            <input
                                type="password"
                                id="passwordInput"
                                name="password"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="Masukkan password"
                                class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-xs pr-10 focus:border-brand-skyBlue focus:ring-1 focus:ring-brand-skyBlue outline-none transition"
                            >

                            <button
                                type="button"
                                onclick="togglePassword('passwordInput', 'eyeOpen1', 'eyeClosed1')"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600"
                                aria-label="Lihat Password"
                            >
                                <svg id="eyeOpen1" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>

                                <svg id="eyeClosed1" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.032 10.032 0 013.856-5.18m3.08-1.562C10.22 5.067 11.102 5 12 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18" />
                                </svg>
                            </button>
                        </div>

                        <p class="text-[10px] text-slate-400 mt-1">
                            Gunakan minimal 8 karakter.
                        </p>
                    </div>

                    <!-- KONFIRMASI PASSWORD -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Konfirmasi Password
                        </label>

                        <div class="relative">
                            <input
                                type="password"
                                id="confirmPasswordInput"
                                name="konfirmasi_password"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="Masukkan ulang password"
                                class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-xs pr-10 focus:border-brand-skyBlue focus:ring-1 focus:ring-brand-skyBlue outline-none transition"
                            >

                            <button
                                type="button"
                                onclick="togglePassword('confirmPasswordInput', 'eyeOpen2', 'eyeClosed2')"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600"
                                aria-label="Lihat Konfirmasi Password"
                            >
                                <svg id="eyeOpen2" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>

                                <svg id="eyeClosed2" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.032 10.032 0 013.856-5.18m3.08-1.562C10.22 5.067 11.102 5 12 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-lg bg-brand-navy py-3 text-xs font-semibold text-white shadow-md hover:bg-brand-lightNavy transition mt-2 cursor-pointer"
                    >
                        Buat Akun Super Admin
                    </button>

                </form>

                <!-- INFORMASI -->
                <div class="mt-8 pt-4 border-t border-slate-100 text-center">
                    <p class="text-[11px] text-slate-400">
                        Gunakan akun ini sebagai akses utama pengelolaan sistem KolegaOps.
                    </p>
                </div>

            <?php endif; ?>

        </div>

    </div>

    <script>
        function togglePassword(inputId, eyeOpenId, eyeClosedId) {
            const input = document.getElementById(inputId);
            const eyeOpen = document.getElementById(eyeOpenId);
            const eyeClosed = document.getElementById(eyeClosedId);

            if (input.type === 'password') {
                input.type = 'text';
                eyeOpen.classList.add('hidden');
                eyeClosed.classList.remove('hidden');
            } else {
                input.type = 'password';
                eyeOpen.classList.remove('hidden');
                eyeClosed.classList.add('hidden');
            }
        }
    </script>

</body>
</html>
