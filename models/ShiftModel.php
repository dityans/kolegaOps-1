
<?php
// models/ShiftModel.php
// Model Pengelolaan Shift Kasir & Rekapitulasi Operasional

class ShiftModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Mengambil waktu berdasarkan zona waktu Indonesia.
     */
    private function getWaktuIndonesia(): DateTimeImmutable
    {
        return new DateTimeImmutable(
            'now',
            new DateTimeZone('Asia/Jakarta')
        );
    }

    /**
     * Mengambil informasi shift terakhir pada hari ini.
     *
     * @return array|null
     */
    public function getShiftAktif(): ?array
    {
        $tanggalHariIni = $this->getWaktuIndonesia()->format('Y-m-d');

        $sql = "
            SELECT
                s.id_shift,
                s.nama_shift,
                s.status,
                s.waktu_buka,
                s.waktu_tutup,
                s.total_pengeluaran,
                s.saldo_akhir,
                COALESCE(u.username, 'Kasir') AS nama_kasir
            FROM shift s
            LEFT JOIN users u ON s.id_user = u.id_user
            WHERE s.tanggal = :tanggal
            ORDER BY s.id_shift DESC
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':tanggal' => $tanggalHariIni
        ]);

        $shift = $stmt->fetch(PDO::FETCH_ASSOC);

        return $shift ?: null;
    }

    /**
     * Mengambil shift aktif milik kasir pada hari ini.
     *
     * Shift dari tanggal sebelumnya tidak akan digunakan.
     * Jika shift hari ini sudah ditutup, data shift terakhir
     * tetap dikembalikan tanpa membuka shift baru otomatis.
     *
     * @param int $id_user
     * @return array|null
     */
    public function getShiftKasirAktif(int $id_user): ?array
    {
        $tanggalHariIni = $this->getWaktuIndonesia()->format('Y-m-d');

        // Mengambil shift yang masih terbuka pada hari ini.
        $sql = "
            SELECT
                s.id_shift,
                s.nama_shift,
                s.tanggal,
                s.status,
                s.waktu_buka,
                s.waktu_tutup,
                s.total_penjualan_tunai,
                s.total_penjualan_qris,
                s.total_pengeluaran,
                s.saldo_akhir,
                COALESCE(u.username, 'Kasir') AS nama_kasir
            FROM shift s
            LEFT JOIN users u ON s.id_user = u.id_user
            WHERE s.id_user = :id_user
              AND s.tanggal = :tanggal
              AND s.status = 'buka'
            ORDER BY s.id_shift DESC
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_user' => $id_user,
            ':tanggal' => $tanggalHariIni
        ]);

        $shift = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($shift) {
            return $shift;
        }

        // Jika tidak ada shift terbuka, ambil shift terakhir hari ini.
        $sqlHariIni = "
            SELECT
                s.id_shift,
                s.nama_shift,
                s.tanggal,
                s.status,
                s.waktu_buka,
                s.waktu_tutup,
                s.total_penjualan_tunai,
                s.total_penjualan_qris,
                s.total_pengeluaran,
                s.saldo_akhir,
                COALESCE(u.username, 'Kasir') AS nama_kasir
            FROM shift s
            LEFT JOIN users u ON s.id_user = u.id_user
            WHERE s.id_user = :id_user
              AND s.tanggal = :tanggal
            ORDER BY s.id_shift DESC
            LIMIT 1
        ";

        $stmtToday = $this->pdo->prepare($sqlHariIni);
        $stmtToday->execute([
            ':id_user' => $id_user,
            ':tanggal' => $tanggalHariIni
        ]);

        $shiftToday = $stmtToday->fetch(PDO::FETCH_ASSOC);

        return $shiftToday ?: null;
    }

    /**
     * Membuka shift baru untuk kasir.
     *
     * @param int $id_user
     * @param string $nama_shift
     * @return int
     */
    public function bukaShiftBaru(
        int $id_user,
        string $nama_shift = 'Pagi'
    ): int {
        try {
            $waktuSekarang = $this->getWaktuIndonesia();

            $tanggalHariIni = $waktuSekarang->format('Y-m-d');
            $jamSekarang = $waktuSekarang->format('Y-m-d H:i:s');

            $sql = "
                INSERT INTO shift (
                    nama_shift,
                    tanggal,
                    waktu_buka,
                    status,
                    id_user,
                    total_penjualan_tunai,
                    total_penjualan_qris,
                    total_pengeluaran,
                    saldo_akhir
                )
                VALUES (
                    :nama_shift,
                    :tanggal,
                    :waktu_buka,
                    'buka',
                    :id_user,
                    0,
                    0,
                    0,
                    0
                )
                RETURNING id_shift
            ";

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                ':nama_shift' => $nama_shift,
                ':tanggal' => $tanggalHariIni,
                ':waktu_buka' => $jamSekarang,
                ':id_user' => $id_user
            ]);

            return (int) $stmt->fetchColumn();

        } catch (PDOException $e) {
            error_log(
                '[ShiftModel::bukaShiftBaru] ' . $e->getMessage()
            );

            return 0;
        }
    }

    /**
     * Menutup shift kasir dan merekap saldo akhir.
     *
     * @param int $id_shift
     * @param float $saldo_akhir
     * @param float $total_tunai
     * @param float $total_qris
     * @param float $total_pengeluaran
     * @return bool
     */
    public function tutupShift(
        int $id_shift,
        float $saldo_akhir,
        float $total_tunai,
        float $total_qris,
        float $total_pengeluaran
    ): bool {
        if ($id_shift <= 0) {
            return false;
        }

        try {
            $waktuSekarang = $this->getWaktuIndonesia()
                ->format('Y-m-d H:i:s');

            $sql = "
                UPDATE shift
                SET
                    status = 'tutup',
                    waktu_tutup = :waktu_tutup,
                    saldo_akhir = :saldo_akhir,
                    total_penjualan_tunai = :total_tunai,
                    total_penjualan_qris = :total_qris,
                    total_pengeluaran = :total_pengeluaran,
                    update_at = :update_at
                WHERE id_shift = :id_shift
                  AND status = 'buka'
            ";

            $stmt = $this->pdo->prepare($sql);

            return $stmt->execute([
                ':waktu_tutup' => $waktuSekarang,
                ':saldo_akhir' => $saldo_akhir,
                ':total_tunai' => $total_tunai,
                ':total_qris' => $total_qris,
                ':total_pengeluaran' => $total_pengeluaran,
                ':update_at' => $waktuSekarang,
                ':id_shift' => $id_shift
            ]);

        } catch (PDOException $e) {
            error_log(
                '[ShiftModel::tutupShift] ' . $e->getMessage()
            );

            return false;
        }
    }

    /**
     * Mengambil ringkasan operasional harian untuk footer dashboard.
     *
     * @return string
     */
    public function getInfoOperasionalFooter(): string
    {
        $tanggalHariIni = $this->getWaktuIndonesia()->format('Y-m-d');

        $shift = $this->getShiftAktif();

        $stmtExp = $this->pdo->prepare("
            SELECT COALESCE(SUM(nominal), 0)
            FROM pengeluaran_shift
            WHERE DATE(create_at) = :tanggal
        ");

        $stmtExp->execute([
            ':tanggal' => $tanggalHariIni
        ]);

        $totalPengeluaran = (float) $stmtExp->fetchColumn();

        $nominalPengeluaran = number_format(
            $totalPengeluaran,
            0,
            ',',
            '.'
        );

        if ($shift) {
            $namaShift = htmlspecialchars(
                $shift['nama_shift'],
                ENT_QUOTES,
                'UTF-8'
            );

            $namaKasir = htmlspecialchars(
                $shift['nama_kasir'],
                ENT_QUOTES,
                'UTF-8'
            );

            $statusShift = ($shift['status'] === 'buka')
                ? 'sedang aktif'
                : 'telah ditutup';

            return "Operasional: Shift {$namaShift} {$statusShift} oleh {$namaKasir} · Pengeluaran hari ini Rp {$nominalPengeluaran}.";
        }

        return "Operasional: Belum ada shift kasir yang dibuka hari ini · Pengeluaran tercatat Rp {$nominalPengeluaran}.";
    }
}
