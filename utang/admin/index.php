<?php
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$saldo   = getSaldo($pdo);
$fees    = getAllFees($pdo);
$message = '';
$msgType = '';

// ── HANDLE POST ACTIONS ──────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $postAction = $_POST['post_action'] ?? '';

    // --- TAMBAH TRANSAKSI ---
    if ($postAction === 'add_transaction') {
        $amountInput = (float)str_replace(['.', ',', ' '], '', $_POST['amount']);
        $desc        = trim($_POST['description']);
        $txType      = $_POST['tx_type']; // bunga_bulanan | pay_debt
        $txDate      = $_POST['tx_date'] ?: date('Y-m-d');
        $txDatetime  = $txDate . ' ' . date('H:i:s');

        if ($amountInput > 0 && !empty($desc)) {
            if ($txType === 'bunga_bulanan') {
                $newSaldo = $saldo + $amountInput;
                $type     = "Bunga Bulanan: " . $desc;

                updateSaldo($pdo, $newSaldo);
                $stmt = $pdo->prepare("INSERT INTO transactions (date, type, amount, current_saldo, split_details) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$txDatetime, $type, $amountInput, $newSaldo, null]);

                $saldo   = $newSaldo;
                $message = "Bunga bulanan berhasil ditambahkan!";
                $msgType = "success";

            } elseif ($txType === 'pay_debt') {
                $pct        = ['fee_admin' => 0.05, 'fee_nama_baik' => 0.12, 'fee_akomodasi' => 0.03];
                $totalPct   = 0.20;
                $amountPaid = $amountInput * (1 - $totalPct); // 80%

                $newSaldo = $saldo - $amountPaid;
                updateSaldo($pdo, $newSaldo);
                $saldo = $newSaldo;

                $splitArr = [];
                foreach ($pct as $fKey => $pctVal) {
                    $feeAmt = $amountInput * $pctVal;
                    updateFee($pdo, $fKey, $fees[$fKey] + $feeAmt);
                    $splitArr[$fKey] = $feeAmt;
                }
                $fees = getAllFees($pdo);

                $type           = "Pembayaran: " . $desc;
                $amountToRecord = -$amountPaid;
                $splitDetails   = json_encode($splitArr);

                $stmt = $pdo->prepare("INSERT INTO transactions (date, type, amount, current_saldo, split_details) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$txDatetime, $type, $amountToRecord, $newSaldo, $splitDetails]);

                $message = "Pembayaran berhasil dicatat!";
                $msgType = "success";
            }
        } else {
            $message = "Nominal dan keterangan tidak boleh kosong!";
            $msgType = "error";
        }
    }

    // --- TOGGLE HIDE TRANSAKSI ---
    if ($postAction === 'toggle_hidden') {
        $trxId = (int)$_POST['trx_id'];
        $stmt  = $pdo->prepare("SELECT hidden FROM transactions WHERE id = ?");
        $stmt->execute([$trxId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            $newHidden = $row['hidden'] ? 0 : 1;
            $upd = $pdo->prepare("UPDATE transactions SET hidden = ? WHERE id = ?");
            $upd->execute([$newHidden, $trxId]);
            $message = $newHidden ? "Transaksi disembunyikan dari user." : "Transaksi ditampilkan ke user.";
            $msgType = "success";
        }
    }

    // --- HAPUS TRANSAKSI ---
    if ($postAction === 'delete_transaction') {
        $trxId    = (int)$_POST['trx_id'];
        $stmtGet  = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
        $stmtGet->execute([$trxId]);
        $trxToDelete = $stmtGet->fetch(PDO::FETCH_ASSOC);

        if ($trxToDelete) {
            $currentSaldo  = getSaldo($pdo);
            $reversedSaldo = $currentSaldo - $trxToDelete['amount'];
            updateSaldo($pdo, $reversedSaldo);
            $saldo = $reversedSaldo;

            if ($trxToDelete['amount'] < 0 && $trxToDelete['split_details']) {
                $splitArr = json_decode($trxToDelete['split_details'], true);
                if ($splitArr) {
                    foreach ($splitArr as $fKey => $feeAmt) {
                        updateFee($pdo, $fKey, max(0, getFee($pdo, $fKey) - $feeAmt));
                    }
                    $fees = getAllFees($pdo);
                }
            }

            $stmtDel = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
            $stmtDel->execute([$trxId]);

            $message = "Transaksi berhasil dihapus!";
            $msgType = "success";
        }
    }

    // --- EDIT TRANSAKSI ---
    if ($postAction === 'edit_transaction') {
        $trxId     = (int)$_POST['trx_id'];
        $newDesc   = trim($_POST['edit_description']);
        $newAmount = (float)str_replace(['.', ',', ' '], '', $_POST['edit_amount']);
        $newDate   = $_POST['edit_date'];

        $stmtGet = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
        $stmtGet->execute([$trxId]);
        $trxOld = $stmtGet->fetch(PDO::FETCH_ASSOC);

        if ($trxOld && $newAmount > 0 && !empty($newDesc)) {
            $isPayment = $trxOld['amount'] < 0;
            $oldAmount = $trxOld['amount'];

            if ($isPayment) {
                $newAmountSigned = -($newAmount * 0.8);
                $diff = $newAmountSigned - $oldAmount;

                $pct     = ['fee_admin' => 0.05, 'fee_nama_baik' => 0.12, 'fee_akomodasi' => 0.03];
                $oldFull = abs($oldAmount) / 0.8;
                $newFull = $newAmount;
                $splitArr = [];
                foreach ($pct as $fKey => $pctVal) {
                    updateFee($pdo, $fKey, max(0, getFee($pdo, $fKey) - $oldFull * $pctVal + $newFull * $pctVal));
                    $splitArr[$fKey] = $newFull * $pctVal;
                }
                $fees = getAllFees($pdo);
                $splitDetails = json_encode($splitArr);

                $typeParts = explode(':', $trxOld['type'], 2);
                $typeLabel = count($typeParts) > 1 ? trim($typeParts[0]) : 'Pembayaran';
                $newType   = $typeLabel . ": " . $newDesc;
            } else {
                $newAmountSigned = $newAmount;
                $diff = $newAmountSigned - $oldAmount;
                $splitDetails = null;
                $typeParts = explode(':', $trxOld['type'], 2);
                $typeLabel = count($typeParts) > 1 ? trim($typeParts[0]) : 'Bunga Bulanan';
                $newType   = $typeLabel . ": " . $newDesc;
            }

            $currentSaldo  = getSaldo($pdo);
            $adjustedSaldo = $currentSaldo + $diff;
            updateSaldo($pdo, $adjustedSaldo);
            $saldo = $adjustedSaldo;

            $stmtUpd = $pdo->prepare("UPDATE transactions SET date = ?, type = ?, amount = ?, current_saldo = ?, split_details = ? WHERE id = ?");
            $stmtUpd->execute([
                $newDate . ' ' . date('H:i:s'),
                $newType,
                $newAmountSigned,
                $adjustedSaldo,
                $splitDetails,
                $trxId
            ]);

            $message = "Transaksi berhasil diperbarui!";
            $msgType = "success";
        } else {
            $message = "Data edit tidak valid!";
            $msgType = "error";
        }
    }
}

// Fetch transactions
$stmt = $pdo->prepare("SELECT * FROM transactions ORDER BY id DESC");
$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
$saldo = getSaldo($pdo);
$fees  = getAllFees($pdo);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Dashboard - Tagihan Hoirul</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?= time() ?>">
    <style>
    /* ── BASE SETUP ── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    
    :root {
        --primary: #ff5e00;
        --secondary: #ff3b30;
        --bg: #f4f7fa;
        --card: #ffffff;
        --text-dark: #2d3436;
        --text-gray: #636e72;
        --success: #00b894;
        --danger: #d63031;
        --radius: 20px;
        --shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    }

    body {
        font-family: 'Inter', -apple-system, sans-serif;
        background: var(--bg);
        color: var(--text-dark);
        line-height: 1.6;
        display: flex;
        justify-content: center; /* Memastikan semua konten di tengah laptop */
        min-height: 100vh;
    }

    /* ── CONTAINER UTAMA (Kunci agar tidak meluber) ── */
    .admin-wrapper {
        width: 100%;
        max-width: 480px; /* Lebar maksimal seukuran HP agar konsisten */
        background: var(--bg);
        min-height: 100vh;
        position: relative;
        padding-bottom: 50px;
    }

    /* ── HEADER ── */
    .admin-header {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        padding: 30px 20px 45px;
        border-radius: 0 0 35px 35px;
        text-align: center;
        color: white;
        box-shadow: 0 10px 20px rgba(255, 94, 0, 0.2);
    }

    .admin-header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .admin-header h2 { font-size: 18px; font-weight: 800; }
    
    .btn-logout {
        background: rgba(255,255,255,0.2);
        padding: 6px 15px;
        border-radius: 12px;
        color: #fff;
        text-decoration: none;
        font-size: 12px;
        font-weight: 700;
    }

    .saldo-hero-label { font-size: 11px; opacity: 0.8; text-transform: uppercase; letter-spacing: 1px; }
    .saldo-hero-value { font-size: 32px; font-weight: 900; margin-top: 5px; }

    /* ── CARD STYLING ── */
    .a-card {
        background: var(--card);
        border-radius: var(--radius);
        margin: -25px 16px 20px; /* Efek menempel ke header */
        padding: 20px;
        box-shadow: var(--shadow);
        position: relative;
        z-index: 10;
    }

    .a-card:not(:first-of-type) { margin-top: 0; }

    .a-card-title {
        font-size: 13px;
        font-weight: 800;
        color: var(--text-gray);
        text-transform: uppercase;
        margin-bottom: 15px;
        display: block;
        border-bottom: 1px solid #f1f1f1;
        padding-bottom: 10px;
    }

    /* ── FEE GRID ── */
    .fee-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
    }
    .fee-box {
        background: #fafafa;
        padding: 10px 5px;
        border-radius: 15px;
        text-align: center;
        border: 1px solid #eee;
    }
    .fee-box-label { font-size: 9px; color: var(--text-gray); display: block; }
    .fee-box-value { font-size: 12px; font-weight: 800; color: var(--primary); }

    /* ── FORM ELEMENTS ── */
    .f-group { margin-bottom: 15px; }
    .f-group label { font-size: 12px; font-weight: 700; display: block; margin-bottom: 8px; }
    .f-input {
        width: 100%;
        padding: 12px 15px;
        border-radius: 12px;
        border: 2px solid #eee;
        font-family: inherit;
        font-size: 14px;
        transition: 0.3s;
    }
    .f-input:focus { border-color: var(--primary); outline: none; }

    .radio-group { display: flex; gap: 10px; }
    .radio-label {
        flex: 1; padding: 12px; border: 2px solid #eee; border-radius: 12px;
        text-align: center; font-size: 13px; font-weight: 700; cursor: pointer;
    }
    .radio-label.active-pay { border-color: var(--success); color: var(--success); background: #f0fff4; }
    .radio-label.active-debt { border-color: var(--primary); color: var(--primary); background: #fff5f0; }

    .btn-submit {
        width: 100%;
        padding: 15px;
        background: #1e272e;
        color: white;
        border: none;
        border-radius: 15px;
        font-weight: 800;
        font-size: 15px;
        cursor: pointer;
        margin-top: 10px;
    }

    /* ── TRANSACTION LIST (NOMINAL DI KANAN) ── */
    .trx-item {
        padding: 15px 0;
        border-bottom: 1px solid #f1f1f1;
    }
    .trx-top {
        display: flex;
        justify-content: space-between; /* Paksa nominal ke ujung kanan */
        align-items: center;
    }
    .trx-name { font-size: 14px; font-weight: 700; color: #2d3436; max-width: 60%; }
    .trx-amount { font-size: 15px; font-weight: 800; text-align: right; }
    
    .trx-amount.red { color: var(--danger); }
    .trx-amount.green { color: var(--success); }

    .trx-meta {
        font-size: 11px;
        color: var(--text-gray);
        display: flex;
        justify-content: space-between;
        margin-top: 5px;
        margin-bottom: 10px;
    }

    .trx-actions { display: flex; gap: 5px; }
    .btn-sm {
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 700;
        border: none;
        cursor: pointer;
    }
    .btn-edit { background: #e1f5fe; color: #039be5; }
    .btn-del { background: #ffebee; color: #e53935; }

    /* ── MODAL EDIT (FIX BIAR GAK BERANTAKAN) ── */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        display: none; /* Default sembunyi */
        align-items: flex-end; /* Muncul dari bawah di HP */
        justify-content: center;
        z-index: 1000;
    }
    .modal-overlay.active { display: flex; }
    
    .modal-box {
        background: white;
        width: 100%;
        max-width: 480px; /* Harus sama dengan container utama */
        padding: 25px;
        border-radius: 25px 25px 0 0;
        animation: slideUp 0.3s ease-out;
    }

    @keyframes slideUp {
        from { transform: translateY(100%); }
        to { transform: translateY(0); }
    }

    /* Responsif untuk layar lebar (Laptop) agar modal di tengah */
    @media (min-width: 481px) {
        .modal-overlay { align-items: center; }
        .modal-box { border-radius: 25px; }
    }
</style>
</head>
<body>

<div class="admin-wrapper">

    <!-- Header -->
    <div class="admin-header">
        <div class="admin-header-row">
            <h2>⚙️ Admin Panel</h2>
            <a href="logout.php" class="btn-logout">Keluar</a>
        </div>
        <div class="saldo-hero">
            <div class="saldo-hero-label">Total Sisa Tagihan Hoirul</div>
            <div class="saldo-hero-value"><?= formatRupiah($saldo) ?></div>
        </div>
    </div>

    <!-- Alert -->
    <?php if ($message): ?>
        <div class="alert <?= $msgType === 'success' ? 'alert-success' : 'alert-error' ?>" style="margin-top:16px;">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Breakdown Fee -->
    <div class="a-card">
        <div class="a-card-title">💰 Breakdown Biaya (20%)</div>
        <div class="fee-grid">
            <div class="fee-box">
                <div class="fee-box-label">Admin</div>
                <div class="fee-box-value"><?= formatRupiah($fees['fee_admin']) ?></div>
                <div class="fee-box-pct">5% per bayar</div>
            </div>
            <div class="fee-box">
                <div class="fee-box-label">Nama Baik</div>
                <div class="fee-box-value"><?= formatRupiah($fees['fee_nama_baik']) ?></div>
                <div class="fee-box-pct">12% per bayar</div>
            </div>
            <div class="fee-box">
                <div class="fee-box-label">Akomodasi</div>
                <div class="fee-box-value"><?= formatRupiah($fees['fee_akomodasi']) ?></div>
                <div class="fee-box-pct">3% per bayar</div>
            </div>
        </div>
    </div>

    <!-- Form Tambah Transaksi -->
    <div class="a-card">
        <div class="a-card-title">➕ Tambah Transaksi</div>
        <form method="POST" action="" class="a-form">
            <input type="hidden" name="post_action" value="add_transaction">

            <div class="f-group">
                <label>Jenis Transaksi</label>
                <div class="radio-group">
                    <label class="radio-label active-pay" id="lbl-pay">
                        <input type="radio" name="tx_type" value="pay_debt" checked onchange="switchType(this)">
                        ✅ Pembayaran
                    </label>
                    <label class="radio-label" id="lbl-debt">
                        <input type="radio" name="tx_type" value="bunga_bulanan" onchange="switchType(this)">
                        📈 Bunga Bulanan
                    </label>
                </div>
            </div>

            <div class="f-group">
                <label>Tanggal Transaksi</label>
                <input type="date" name="tx_date" class="f-input" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="f-group">
                <label>Nominal (Rp)</label>
                <input type="number" name="amount" class="f-input" required placeholder="Contoh: 500000">
            </div>

            <div class="f-group">
                <label>Keterangan</label>
                <input type="text" name="description" class="f-input" required placeholder="Contoh: Bayar Cicilan Bulan Ini">
            </div>

            <button type="submit" class="btn-submit">Simpan Transaksi</button>
        </form>
    </div>

    <!-- Riwayat Transaksi -->
    <div class="a-card">
        <div class="a-card-title">📋 Riwayat Transaksi</div>
        <div class="trx-list">
            <?php if (empty($transactions)): ?>
                <div style="text-align:center; padding: 30px; color:#ccc; font-size:13px;">Belum ada transaksi.</div>
            <?php else: ?>
                <?php foreach ($transactions as $trx): ?>
                    <?php
                        $isPayment = $trx['amount'] < 0;
                        $amtClass  = $isPayment ? 'green' : 'red';
                        $sign      = $isPayment ? '−' : '+';
                        $parts     = explode(': ', $trx['type'], 2);
                        $descLabel = count($parts) > 1 ? $parts[1] : $trx['type'];
                        $origAmt   = $isPayment ? abs($trx['amount']) / 0.8 : abs($trx['amount']);
                        $isHidden  = !empty($trx['hidden']);
                    ?>
                    <div class="trx-item <?= $isHidden ? 'is-hidden' : '' ?>">
                        <div class="trx-top">
                            <div class="trx-name <?= $isHidden ? 'is-hidden-label' : '' ?>">
                                <?= htmlspecialchars($trx['type']) ?>
                                <?php if ($isHidden): ?><span class="badge-hidden">Tersembunyi</span><?php endif; ?>
                            </div>
                            <div class="trx-amount <?= $amtClass ?>">
                                <?= $sign . formatRupiah(abs($trx['amount'])) ?>
                            </div>
                        </div>
                        <div class="trx-meta">
                            <span><?= date('d M Y, H:i', strtotime($trx['date'])) ?></span>
                            <span>Sisa: <?= formatRupiah($trx['current_saldo']) ?></span>
                        </div>
                        <div class="trx-actions">
                            <!-- Edit -->
                            <button class="btn-sm btn-edit"
                                onclick="openEdit(<?= $trx['id'] ?>, '<?= htmlspecialchars(addslashes($descLabel)) ?>', '<?= number_format($origAmt, 0, '', '') ?>', '<?= date('Y-m-d', strtotime($trx['date'])) ?>')">
                                ✏ Edit
                            </button>

                            <!-- Hide / Show -->
                            <form method="POST" action="" style="margin:0;">
                                <input type="hidden" name="post_action" value="toggle_hidden">
                                <input type="hidden" name="trx_id" value="<?= $trx['id'] ?>">
                                <button type="submit" class="btn-sm <?= $isHidden ? 'btn-show' : 'btn-hide' ?>">
                                    <?= $isHidden ? '👁 Tampilkan' : '🙈 Sembunyikan' ?>
                                </button>
                            </form>

                            <!-- Hapus -->
                            <form method="POST" action="" style="margin:0;" onsubmit="return confirm('Yakin hapus transaksi ini? Saldo akan disesuaikan.');">
                                <input type="hidden" name="post_action" value="delete_transaction">
                                <input type="hidden" name="trx_id" value="<?= $trx['id'] ?>">
                                <button type="submit" class="btn-sm btn-del">🗑 Hapus</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer-nav">
        <a href="../index.php">← Lihat Halaman User</a>
    </div>

</div>

<!-- Modal Edit -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-title">✏️ Edit Transaksi</div>
        <form method="POST" action="">
            <input type="hidden" name="post_action" value="edit_transaction">
            <input type="hidden" name="trx_id" id="edit_trx_id">
            <div class="f-group">
                <label>Keterangan</label>
                <input type="text" name="edit_description" id="edit_description" class="f-input" required>
            </div>
            <div class="f-group">
                <label>Nominal Asli (Rp)</label>
                <input type="number" name="edit_amount" id="edit_amount" class="f-input" required>
            </div>
            <div class="f-group">
                <label>Tanggal</label>
                <input type="date" name="edit_date" id="edit_date" class="f-input" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeEdit()">Batal</button>
                <button type="submit" class="btn-save">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
// Radio styling
function switchType(el) {
    document.getElementById('lbl-pay').className  = 'radio-label';
    document.getElementById('lbl-debt').className = 'radio-label';
    if (el.value === 'pay_debt') {
        document.getElementById('lbl-pay').classList.add('active-pay');
    } else {
        document.getElementById('lbl-debt').classList.add('active-debt');
    }
}

// Edit modal
function openEdit(id, desc, amount, date) {
    document.getElementById('edit_trx_id').value    = id;
    document.getElementById('edit_description').value = desc;
    document.getElementById('edit_amount').value    = amount;
    document.getElementById('edit_date').value      = date;
    document.getElementById('editModal').classList.add('active');
}
function closeEdit() {
    document.getElementById('editModal').classList.remove('active');
}
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEdit();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeEdit();
});
</script>

</body>
</html>
