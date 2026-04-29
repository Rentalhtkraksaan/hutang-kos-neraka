<?php
require_once 'config.php';

$saldo = getSaldo($pdo);

// Hanya tampilkan transaksi yang tidak disembunyikan admin
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE hidden = 0 ORDER BY id DESC");
$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung total yang sudah dibayar
$totalDibayar = 0;
foreach ($transactions as $trx) {
    if ($trx['amount'] < 0) $totalDibayar += abs($trx['amount']);
}

// Data Peminjam (static, bisa dikembangkan ke DB)
$bulanId = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
$nextMonth      = (int)date('n') % 12; // 0-based index of next month (0=Jan)
$nextMonthYear  = (int)date('n') == 12 ? (int)date('Y') + 1 : (int)date('Y');
$jatuhTempoStr  = '06/' . $bulanId[$nextMonth] . '/' . $nextMonthYear;

$info = [
    'jatuh_tempo'     => $jatuhTempoStr,
    'total_pinjaman'  => 1500000,
    'jumlah_diterima' => 1500000,
    'bunga'           => 180000,
    'premi'           => 45000,
    'denda'           => 540000,
    'tanggal_pinjam'  => '07/Des/2025',
    'nomor_pinjaman'  => '0100701007067001125',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Tagihan Akulaku Khoirul</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* ── FLOATING CS BUTTON ── */
        .cs-fab {
            position: fixed;
            bottom: 24px;
            right: 20px;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: #25d366;
            color: #fff;
            border: none;
            box-shadow: 0 4px 16px rgba(37,211,102,0.45);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 400;
            transition: transform 0.2s;
        }
        .cs-fab:hover { transform: scale(1.1); }
        .cs-fab-label {
            position: fixed;
            bottom: 30px;
            right: 80px;
            background: #fff;
            color: #333;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.12);
            white-space: nowrap;
            z-index: 400;
            pointer-events: none;
        }

        /* ── FEE BREAKDOWN ── */
        .fee-section {
            background: #fff8f5;
            border: 1px solid #ffe0cc;
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 20px;
        }
        .fee-section-title {
            font-size: 12px;
            font-weight: 700;
            color: #ff5e00;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .fee-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 7px 0;
            border-bottom: 1px dashed #ffe0cc;
        }
        .fee-row:last-of-type { border-bottom: none; }
        .fee-row-label { font-size: 13px; color: #555; display: flex; align-items: center; gap: 6px; }
        .fee-row-pct { font-size: 13px; font-weight: 700; color: #ff5e00; }
        .fee-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            background: #ff5e00;
            border-radius: 10px;
            margin-top: 10px;
        }
        .fee-total-label { font-size: 13px; font-weight: 700; color: #fff; }
        .fee-total-pct { font-size: 18px; font-weight: 800; color: #fff; }

        /* ── PAYMENT MODAL ── */
        .pay-modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 600;
            align-items: flex-end;
            justify-content: center;
        }
        .pay-modal-backdrop.open { display: flex; }
        .pay-modal-sheet {
            background: #fff;
            width: 100%;
            max-width: 520px;
            border-radius: 20px 20px 0 0;
            padding: 0 0 30px;
            animation: slideUpPay 0.25s ease;
        }
        @keyframes slideUpPay {
            from { transform: translateY(40px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }
        .pay-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        .pay-modal-title { font-size: 16px; font-weight: 700; }
        .pay-close-btn {
            width: 30px; height: 30px;
            border-radius: 50%;
            background: #f0f0f0;
            border: none; font-size: 16px;
            cursor: pointer; display: flex;
            align-items: center; justify-content: center; color: #555;
        }
        .pay-quick-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            padding: 16px 20px 0;
        }
        .pay-quick-btn {
            padding: 14px;
            background: #fff5f0;
            border: 1.5px solid #ffe0cc;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            color: #ff5e00;
            cursor: pointer;
            transition: all 0.15s;
            font-family: inherit;
        }
        .pay-quick-btn:hover, .pay-quick-btn.selected {
            background: #ff5e00;
            color: #fff;
            border-color: #ff5e00;
        }
        .pay-custom-wrap {
            padding: 12px 20px 0;
        }
        .pay-custom-input {
            width: 100%;
            padding: 13px 16px;
            border-radius: 12px;
            border: 1.5px solid #f0f0f0;
            font-size: 15px;
            font-family: inherit;
            color: #333;
            outline: none;
            transition: border-color 0.2s;
        }
        .pay-custom-input:focus { border-color: #ff5e00; }
        .pay-preview {
            margin: 10px 20px 0;
            padding: 10px 14px;
            background: #f9f9f9;
            border-radius: 10px;
            font-size: 13px;
            color: #888;
            min-height: 40px;
        }
        .pay-preview span { color: #ff5e00; font-weight: 700; }
        .pay-submit-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: calc(100% - 40px);
            margin: 14px 20px 0;
            padding: 15px;
            background: linear-gradient(135deg, #25d366, #128c7e);
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            font-family: inherit;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f4f5f7;
            color: #222;
            min-height: 100vh;
        }

        /* ── LAYOUT WRAPPER ── */
        .page-wrapper {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding: 40px 16px;
        }

        .content-card {
            width: 100%;
            max-width: 520px;
            background: #fff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 4px 32px rgba(0,0,0,0.08);
        }

        /* ── HEADER ── */
        .card-header {
            background: linear-gradient(135deg, #ff5e00 0%, #ff3b30 100%);
            padding: 28px 24px 24px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .card-header::after {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 140px; height: 140px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        .app-name {
            font-size: 13px;
            font-weight: 600;
            color: rgba(255,255,255,0.8);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 6px;
        }
        .card-header h1 {
            font-size: 22px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 20px;
        }

        /* balance */
        .balance-label-hdr {
            font-size: 12px;
            color: rgba(255,255,255,0.75);
            font-weight: 500;
            margin-bottom: 4px;
        }
        .balance-amount {
            font-size: 38px;
            font-weight: 800;
            color: #fff;
            letter-spacing: -1px;
        }
        .due-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            color: #fff;
            font-weight: 500;
            margin-top: 14px;
        }

        /* ── BODY CONTENT ── */
        .card-body { padding: 20px 24px; }

        /* quick stats row */
        .stats-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }
        .stat-box {
            background: #f9f9f9;
            border-radius: 14px;
            padding: 14px 16px;
            border: 1px solid #f0f0f0;
        }
        .stat-box-label { font-size: 11px; color: #999; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .stat-box-value { font-size: 16px; font-weight: 700; color: #333; }
        .stat-box-value.green { color: #2e7d32; }

        /* data peminjam button */
        .btn-peminjam {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 13px;
            background: #fff;
            border: 1.5px solid #ff5e00;
            color: #ff5e00;
            font-size: 14px;
            font-weight: 700;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.15s;
            margin-bottom: 20px;
            font-family: inherit;
        }
        .btn-peminjam:hover { background: #fff5f0; }
        .btn-peminjam svg { flex-shrink: 0; }

        /* section title */
        .sec-title {
            font-size: 14px;
            font-weight: 700;
            color: #333;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .sec-count {
            font-size: 12px;
            font-weight: 500;
            color: #aaa;
        }

        /* transaction list */
        .trx-list { display: flex; flex-direction: column; gap: 0; }
        .trx-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 13px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        .trx-item:last-child { border-bottom: none; }
        .trx-left { flex: 1; min-width: 0; }
        .trx-name {
            font-size: 13px;
            font-weight: 600;
            color: #333;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .trx-date { font-size: 11px; color: #bbb; margin-top: 2px; }
        .trx-right { text-align: right; flex-shrink: 0; margin-left: 12px; }
        .trx-amount { font-size: 14px; font-weight: 700; }
        .trx-amount.red { color: #e53935; }
        .trx-amount.green { color: #2e7d32; }
        .trx-sisa { font-size: 11px; color: #bbb; margin-top: 2px; }

        .empty-state {
            text-align: center;
            padding: 30px 0;
            color: #ccc;
            font-size: 13px;
        }

        /* trx item clickable */
        .trx-item {
            cursor: pointer;
            transition: background 0.15s;
            border-radius: 10px;
            padding: 13px 8px;
            margin: 0 -8px;
        }
        .trx-item:hover { background: #f9f9f9; }
        .trx-item .trx-tap-hint {
            font-size: 10px;
            color: #ccc;
            margin-top: 2px;
            display: flex;
            align-items: center;
            gap: 3px;
        }

        /* ── RECEIPT MODAL ── */
        .rcpt-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 900;
            align-items: flex-end;
            justify-content: center;
        }
        .rcpt-backdrop.open { display: flex; }
        .rcpt-sheet {
            background: #fff;
            width: 100%;
            max-width: 480px;
            border-radius: 24px 24px 0 0;
            padding-bottom: 30px;
            animation: slideUpRcpt 0.25s ease;
            max-height: 90vh;
            overflow-y: auto;
        }
        @keyframes slideUpRcpt {
            from { transform: translateY(40px); opacity: 0; }
            to   { transform: translateY(0); opacity: 1; }
        }
        .rcpt-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        .rcpt-header-title { font-size: 15px; font-weight: 700; }
        .rcpt-close {
            width: 30px; height: 30px; border-radius: 50%;
            background: #f0f0f0; border: none;
            font-size: 16px; cursor: pointer;
            display: flex; align-items: center; justify-content: center; color: #555;
        }
        /* success hero */
        .rcpt-hero {
            text-align: center;
            padding: 28px 20px 20px;
        }
        .rcpt-check {
            width: 64px; height: 64px;
            border-radius: 50%;
            background: #e6f4ea;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 14px;
        }
        .rcpt-status-title { font-size: 18px; font-weight: 800; color: #1e8e3e; margin-bottom: 4px; }
        .rcpt-status-sub { font-size: 13px; color: #aaa; }
        .rcpt-big-amount {
            font-size: 32px; font-weight: 800; color: #222;
            margin: 12px 0 4px;
        }
        /* receipt rows */
        .rcpt-divider {
            height: 1px; background: #f0f0f0;
            margin: 4px 20px;
        }
        .rcpt-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 10px 20px;
        }
        .rcpt-row-label { font-size: 13px; color: #888; }
        .rcpt-row-value { font-size: 13px; font-weight: 600; color: #333; text-align: right; max-width: 55%; word-break: break-all; }
        .rcpt-row-value.red { color: #e53935; }
        .rcpt-row-value.green { color: #1e8e3e; }
        /* section header inside modal */
        .rcpt-section-label {
            font-size: 11px; font-weight: 700; color: #bbb;
            text-transform: uppercase; letter-spacing: 1px;
            padding: 12px 20px 4px;
        }
        /* payment number box */
        .rcpt-number-box {
            margin: 12px 20px;
            background: #f4f5f7;
            border-radius: 12px;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        .rcpt-number-label { font-size: 12px; color: #999; margin-bottom: 3px; }
        .rcpt-number-value { font-size: 12px; font-weight: 700; color: #555; font-family: monospace; word-break: break-all; }
        /* close btn */
        .rcpt-close-full {
            display: block;
            width: calc(100% - 40px);
            margin: 16px 20px 0;
            padding: 14px;
            background: #f4f5f7;
            border: none;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 700;
            color: #555;
            cursor: pointer;
            font-family: inherit;
        }
        @media (max-width: 480px) {
            .rcpt-backdrop { align-items: flex-end; padding: 0; }
            .rcpt-sheet { max-height: 95vh; }
        }

        /* ── MODAL ── */
        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 500;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-backdrop.open { display: flex; }

        .modal-box {
            background: #fff;
            border-radius: 20px;
            width: 100%;
            max-width: 480px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            animation: slideUp 0.25s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }

        .modal-header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 20px;
            border-bottom: 1px solid #f0f0f0;
            position: sticky;
            top: 0;
            background: #fff;
            border-radius: 20px 20px 0 0;
            z-index: 10;
        }
        .modal-title { font-size: 16px; font-weight: 700; }
        .modal-close-btn {
            width: 30px; height: 30px;
            border-radius: 50%;
            background: #f0f0f0;
            border: none;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #555;
        }

        /* ── DETAIL TAGIHAN MODAL STYLE ── */

        /* Top section: jatuh tempo + jumlah harus dibayar */
        .dt-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 18px 20px 14px;
            border-bottom: 8px solid #f4f5f7;
        }
        .dt-top-left .dt-label { font-size: 12px; color: #999; margin-bottom: 3px; }
        .dt-top-left .dt-date  { font-size: 18px; font-weight: 800; color: #222; }
        .dt-top-right { text-align: right; }
        .dt-top-right .dt-label { font-size: 12px; color: #999; margin-bottom: 3px; }
        .dt-top-right .dt-amount { font-size: 22px; font-weight: 800; color: #e53935; }

        /* Divider section */
        .dt-section {
            border-bottom: 8px solid #f4f5f7;
            padding: 4px 0;
        }
        .dt-section:last-of-type { border-bottom: none; }

        /* Row */
        .dt-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
            border-bottom: 1px solid #f4f5f7;
        }
        .dt-row:last-child { border-bottom: none; }
        .dt-row-label {
            font-size: 14px;
            color: #444;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .dt-row-value {
            font-size: 14px;
            font-weight: 600;
            color: #222;
        }
        .dt-row-value.red   { color: #e53935; }
        .dt-row-value.muted { font-size: 12px; color: #999; }

        /* Badge kedaluwarsa */
        .badge {
            display: inline-block;
            background: #e8f0fe;
            color: #1a73e8;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 8px;
        }

        /* Nomor pinjaman copy row */
        .dt-no-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
        }
        .dt-no-label { font-size: 14px; color: #444; }
        .dt-no-val-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .dt-no-val {
            font-size: 12px;
            color: #999;
            font-family: monospace;
            max-width: 140px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .dt-copy-btn {
            background: #f4f5f7;
            border: none;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 11px;
            color: #555;
            cursor: pointer;
            font-family: inherit;
            font-weight: 600;
            white-space: nowrap;
        }
        .dt-copy-btn:hover { background: #e8e8e8; }

        /* Sticky bayar button */
        .dt-pay-bar {
            position: sticky;
            bottom: 0;
            background: #fff;
            padding: 14px 20px 20px;
            border-top: 1px solid #f0f0f0;
        }
        .dt-pay-btn {
            display: block;
            width: 100%;
            padding: 16px;
            background: #e53935;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            font-family: inherit;
            letter-spacing: 0.3px;
            transition: background 0.15s;
        }
        .dt-pay-btn:hover { background: #c62828; }

        /* ── RESPONSIVE ── */
        @media (min-width: 600px) {
            .page-wrapper { padding: 60px 24px; }
            .balance-amount { font-size: 44px; }
        }

        @media (max-width: 480px) {
            .page-wrapper { padding: 0; align-items: stretch; }
            .content-card { border-radius: 0; box-shadow: none; min-height: 100vh; }

            /* Modal full bottom-sheet on mobile */
            .modal-box {
                border-radius: 20px 20px 0 0;
                max-height: 92vh;
                position: fixed;
                bottom: 0; left: 0; right: 0;
                max-width: 100%;
            }
            .modal-backdrop { align-items: flex-end; padding: 0; }

            /* Card header smaller on mobile */
            .card-header { padding: 20px 18px 18px; }
            .card-header h1 { font-size: 17px; }
            .balance-amount { font-size: 30px; }
            .card-body { padding: 14px 16px; }

            /* Stats row single col on very small */
            .stats-row { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-box-value { font-size: 14px; }

            /* Transaction list */
            .trx-name { font-size: 13px; }
            .trx-amount { font-size: 13px; }

            /* FAB lower on mobile */
            .cs-fab { bottom: 16px; right: 14px; }
            .cs-fab-label { bottom: 22px; right: 72px; }

            /* Detail tagihan modal */
            .dt-top { flex-direction: column; gap: 10px; }
            .dt-top-right { text-align: left; }
            .dt-amount { font-size: 20px; }
            .dt-date { font-size: 16px; }
        }
    </style>
</head>
<body>

<div class="page-wrapper">
    <div class="content-card">

        <!-- Header -->
        <div class="card-header">
            <div class="app-name">Akulaku</div>
            <h1>Tagihan Akulaku Khoirul</h1>
            <div class="balance-label-hdr">Jumlah yang Harus Dibayar</div>
            <div class="balance-amount"><?= formatRupiah($saldo) ?></div>
            <div class="due-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Jatuh Tempo: <?= $info['jatuh_tempo'] ?>
            </div>
        </div>

        <!-- Body -->
        <div class="card-body">

            <!-- Quick Stats -->
            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-box-label">Total Tagihan</div>
                    <div class="stat-box-value"><?= formatRupiah($saldo + $totalDibayar) ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-label">Sudah Dibayar</div>
                    <div class="stat-box-value green"><?= formatRupiah($totalDibayar) ?></div>
                </div>
            </div>

            <!-- Fee Breakdown 20% -->
            <div class="fee-section">
                <div class="fee-section-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    Setiap pembayaran dipotong 20%
                </div>
                <div class="fee-row">
                    <span class="fee-row-label">💼 Admin</span>
                    <span class="fee-row-pct">5%</span>
                </div>
                <div class="fee-row">
                    <span class="fee-row-label">🏅 Keterlambatan</span>
                    <span class="fee-row-pct">12%</span>
                </div>
                <div class="fee-row">
                    <span class="fee-row-label">🏠 Premi</span>
                    <span class="fee-row-pct">3%</span>
                </div>
                <div class="fee-total-row">
                    <span class="fee-total-label">Total Potongan</span>
                    <span class="fee-total-pct">20%</span>
                </div>
            </div>

            <!-- Tombol Data Peminjam -->
            <button class="btn-peminjam" onclick="openModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Lihat Detail Data Peminjam
            </button>

            <div class="sec-title">
    Riwayat Transaksi
    <span class="sec-count"><?= count($transactions) ?> entri</span>
</div>
<div class="trx-list">
    <?php if (empty($transactions)): ?>
        <div class="empty-state">Belum ada transaksi tercatat.</div>
    <?php else: ?>
        <?php foreach ($transactions as $trx): ?>
            <?php
                $isPayment = $trx['amount'] < 0;
                $sign      = $isPayment ? '−' : '+';
                $cls       = $isPayment ? 'green' : 'red';

                $trxDateObj  = strtotime($trx['date']);
                $noTrx       = 'TRX-' . $trx['id'] . '-' . date('dmy', $trxDateObj) . '-' . date('His', $trxDateObj);

                $origAmount  = $isPayment ? round(abs($trx['amount']) / 0.8) : 0;
                $feeAdmin    = round($origAmount * 0.05);
                $feeNama     = round($origAmount * 0.12);
                $feeAkom     = round($origAmount * 0.03);
                $totalFee    = $feeAdmin + $feeNama + $feeAkom;
                $masuk       = abs($trx['amount']); 

                $trxData = json_encode([
                    'id'          => $trx['id'],
                    'type'        => $trx['type'],
                    'date'        => date('d M Y, H:i:s', $trxDateObj),
                    'isPayment'   => $isPayment,
                    'amount'      => abs($trx['amount']),
                    'origAmount'  => $origAmount,
                    'feeAdmin'    => $feeAdmin,
                    'feeNama'     => $feeNama,
                    'feeAkom'     => $feeAkom,
                    'totalFee'    => $totalFee,
                    'masuk'       => $masuk,
                    'saldo'       => $trx['current_saldo'],
                    'noTrx'       => $noTrx,
                ], JSON_HEX_APOS | JSON_HEX_QUOT);
            ?>
            
            <div class="trx-item" onclick='openReceipt(<?= $trxData ?>)' style="cursor: pointer; padding: 12px; border-bottom: 1px solid #eee;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; width: 100%;">
                    
                    <div class="trx-left" style="flex: 1;">
                        <div class="trx-name" style="font-weight: bold;"><?= htmlspecialchars($trx['type']) ?></div>
                        <div class="trx-date" style="font-size: 0.85em; color: #666;"><?= date('d M Y, H:i', $trxDateObj) ?></div>
                        <div class="trx-tap-hint" style="font-size: 0.75em; color: #999; display: flex; align-items: center; gap: 4px;">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            Tap untuk detail
                        </div>
                    </div>

                    <div class="trx-right" style="text-align: right; min-width: 100px;">
                        <div class="trx-amount <?= $cls ?>" style="font-weight: bold; font-size: 1.1em;">
                            <?= $sign . ' ' . formatRupiah(abs($trx['amount'])) ?>
                        </div>
                        <div class="trx-sisa" style="font-size: 0.85em; color: #888;">
                            Sisa <?= formatRupiah($trx['current_saldo']) ?>
                        </div>
                    </div>

                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ── FLOATING CS BUTTON ── -->
<div class="cs-fab-label">Bayar via WhatsApp</div>
<button class="cs-fab" onclick="openPayModal()" title="Hubungi Admin / Bayar">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
</button>

<!-- ── RECEIPT MODAL (klik transaksi) ── -->
<div class="rcpt-backdrop" id="rcptModal" onclick="if(event.target===this)closeReceipt()">
    <div class="rcpt-sheet">
        <div class="rcpt-header">
            <span class="rcpt-header-title">Detail Transaksi</span>
            <button class="rcpt-close" onclick="closeReceipt()">✕</button>
        </div>
        <div id="rcptBody"></div>
    </div>
</div>

<!-- ── MODAL DATA PEMINJAM (Detail Tagihan) ── -->
<div class="modal-backdrop" id="modal" onclick="handleBackdrop(event)">
    <div class="modal-box" id="modalBox">

        <!-- Header -->
        <div class="modal-header-bar">
            <button class="modal-close-btn" onclick="closeModal()" style="background:transparent;font-size:20px;color:#333;">←</button>
            <span class="modal-title">Detail Tagihan</span>
            <div style="width:30px;"></div>
        </div>

        <!-- Top: Jatuh Tempo + Jumlah Harus Dibayar -->
        <div class="dt-top">
            <div class="dt-top-left">
                <div class="dt-label">Jatuh Tempo</div>
                <div class="dt-date"><?= $info['jatuh_tempo'] ?></div>
            </div>
            <div class="dt-top-right">
                <div class="dt-label">Jumlah yang Harus Dibayar</div>
                <div class="dt-amount"><?= formatRupiah($saldo) ?></div>
            </div>
        </div>

        <!-- Section 1: Tagihan Ringkasan -->
        <div class="dt-section">
            <div class="dt-row">
                <span class="dt-row-label">Total Tagihan</span>
                <span class="dt-row-value"><?= formatRupiah($saldo + $totalDibayar) ?></span>
            </div>
            <div class="dt-row">
                <span class="dt-row-label">Jumlah yang Sudah Dibayar</span>
                <span class="dt-row-value"><?= formatRupiah($totalDibayar) ?></span>
            </div>
        </div>

        <!-- Section 2: Rincian Pinjaman -->
        <div class="dt-section">
            <div class="dt-row">
                <span class="dt-row-label">Total pinjaman</span>
                <span class="dt-row-value"><?= formatRupiah($info['total_pinjaman']) ?></span>
            </div>
            <div class="dt-row">
                <span class="dt-row-label">Jumlah diterima</span>
                <span class="dt-row-value"><?= formatRupiah($info['jumlah_diterima']) ?></span>
            </div>
            <div class="dt-row">
                <span class="dt-row-label">Bunga</span>
                <span class="dt-row-value"><?= formatRupiah($info['bunga']) ?></span>
            </div>
            <div class="dt-row">
                <span class="dt-row-label">Premi <span class="badge">Kedaluwarsa</span></span>
                <span class="dt-row-value"><?= formatRupiah($info['premi']) ?></span>
            </div>
            <div class="dt-row">
                <span class="dt-row-label">Denda keterlambatan</span>
                <span class="dt-row-value red"><?= formatRupiah($info['denda']) ?></span>
            </div>
        </div>

        <!-- Section 3: Informasi Tanggal & Nomor -->
        <div class="dt-section">
            <div class="dt-row">
                <span class="dt-row-label">Tanggal pinjam</span>
                <span class="dt-row-value"><?= $info['tanggal_pinjam'] ?></span>
            </div>
            <div class="dt-row">
                <span class="dt-row-label">Tanggal jatuh tempo</span>
                <span class="dt-row-value"><?= $info['jatuh_tempo'] ?></span>
            </div>
            <div class="dt-no-row">
                <span class="dt-no-label">Nomor Pinjaman</span>
                <div class="dt-no-val-wrap">
                    <span class="dt-no-val" id="noPinjaman"><?= $info['nomor_pinjaman'] ?></span>
                    <button class="dt-copy-btn" onclick="copyNoPinjaman()">Salin</button>
                </div>
            </div>
        </div>

        <!-- Sticky Bayar Sekarang -->
        <div class="dt-pay-bar">
            <button class="dt-pay-btn" onclick="closeModal(); openPayModal();">Bayar Sekarang</button>
        </div>

    </div>
</div>

<!-- ── PAYMENT MODAL ── -->
<div class="pay-modal-backdrop" id="payModal" onclick="handlePayBackdrop(event)">
    <div class="pay-modal-sheet" id="paySheet">
        <div class="pay-modal-header">
            <span class="pay-modal-title">💬 Pilih Nominal Bayar</span>
            <button class="pay-close-btn" onclick="closePayModal()">✕</button>
        </div>
        <div class="pay-quick-grid">
            <button class="pay-quick-btn" onclick="selectAmount(20000)">Rp 20.000</button>
            <button class="pay-quick-btn" onclick="selectAmount(50000)">Rp 50.000</button>
            <button class="pay-quick-btn" onclick="selectAmount(100000)">Rp 100.000</button>
            <button class="pay-quick-btn" onclick="selectAmount(200000)">Rp 200.000</button>
        </div>
        <div class="pay-custom-wrap">
            <input type="number" id="customAmount" class="pay-custom-input" placeholder="Atau masukkan nominal lain (Rp)" oninput="onCustomInput()">
        </div>
        <div class="pay-preview" id="payPreview">Pilih nominal atau ketik jumlah di atas...</div>
        <button class="pay-submit-btn" onclick="sendToWA()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            Kirim ke WhatsApp Admin
        </button>
    </div>
</div>

<script>
var selectedAmount = 0;

function openModal() {
    document.getElementById('modal').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal() {
    document.getElementById('modal').classList.remove('open');
    document.body.style.overflow = '';
}
function handleBackdrop(e) {
    if (e.target === document.getElementById('modal')) closeModal();
}

function openPayModal() {
    document.getElementById('payModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closePayModal() {
    document.getElementById('payModal').classList.remove('open');
    document.body.style.overflow = '';
    selectedAmount = 0;
    document.getElementById('customAmount').value = '';
    document.getElementById('payPreview').innerHTML = 'Pilih nominal atau ketik jumlah di atas...';
    document.querySelectorAll('.pay-quick-btn').forEach(b => b.classList.remove('selected'));
}
function handlePayBackdrop(e) {
    if (e.target === document.getElementById('payModal')) closePayModal();
}

function formatRp(n) {
    return 'Rp ' + Number(n).toLocaleString('id-ID');
}

function selectAmount(amount) {
    selectedAmount = amount;
    document.getElementById('customAmount').value = '';
    document.querySelectorAll('.pay-quick-btn').forEach(b => b.classList.remove('selected'));
    event.target.classList.add('selected');
    updatePreview(amount);
}

function onCustomInput() {
    var val = parseInt(document.getElementById('customAmount').value);
    document.querySelectorAll('.pay-quick-btn').forEach(b => b.classList.remove('selected'));
    if (val && val > 0) {
        selectedAmount = val;
        updatePreview(val);
    } else {
        selectedAmount = 0;
        document.getElementById('payPreview').innerHTML = 'Pilih nominal atau ketik jumlah di atas...';
    }
}

function updatePreview(amount) {
    var dipotong = Math.round(amount * 0.2);
    var masuk    = amount - dipotong;
    document.getElementById('payPreview').innerHTML =
        'Bayar <span>' + formatRp(amount) + '</span> → dipotong 20% (<span>' + formatRp(dipotong) + '</span>) → tagihan berkurang <span>' + formatRp(masuk) + '</span>';
}

function sendToWA() {
    if (!selectedAmount || selectedAmount <= 0) {
        alert('Pilih atau masukkan nominal terlebih dahulu!');
        return;
    }
    var nominal  = formatRp(selectedAmount);
    var pesan    = 'halo admin, saya mau bayar tagihan sebesar ' + nominal;
    var waNumber = '6285176871609';
    var url      = 'https://wa.me/' + waNumber + '?text=' + encodeURIComponent(pesan);
    window.open(url, '_blank');
    closePayModal();
}

function formatRp(n) {
    if (typeof n !== 'number') n = parseFloat(n);
    return 'Rp ' + n.toLocaleString('id-ID');
}

function closeReceipt() {
    document.getElementById('rcptModal').classList.remove('open');
    document.body.style.overflow = '';
}

function openReceipt(data) {
    var html = '';

    if (data.isPayment) {
        // ── PAYMENT RECEIPT ──
        html += '<div class="rcpt-hero">';
        html += '<div class="rcpt-check">';
        html += '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#1e8e3e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
        html += '</div>';
        html += '<div class="rcpt-status-title">Pembayaran Diterima</div>';
        html += '<div class="rcpt-status-sub">' + data.date + '</div>';
        html += '<div class="rcpt-big-amount">' + formatRp(data.origAmount) + '</div>';
        html += '<div style="font-size:12px;color:#aaa;">Jumlah yang ditransfer</div>';
        html += '</div>';

        html += '<div class="rcpt-divider"></div>';

        html += '<div class="rcpt-section-label">Rincian Potongan (20%)</div>';
        html += '<div class="rcpt-row"><span class="rcpt-row-label">💼 Admin (5%)</span><span class="rcpt-row-value red">− ' + formatRp(data.feeAdmin) + '</span></div>';
        html += '<div class="rcpt-row"><span class="rcpt-row-label">🏅 Keterlambatan (12%)</span><span class="rcpt-row-value red">− ' + formatRp(data.feeNama) + '</span></div>';
        html += '<div class="rcpt-row"><span class="rcpt-row-label">🏠 Premi (3%)</span><span class="rcpt-row-value red">− ' + formatRp(data.feeAkom) + '</span></div>';
        html += '<div class="rcpt-row" style="border-top:1px dashed #f0f0f0;margin-top:4px;padding-top:8px;">';
        html += '<span class="rcpt-row-label" style="font-weight:700;">Total Potongan</span>';
        html += '<span class="rcpt-row-value red" style="font-size:14px;">− ' + formatRp(data.totalFee) + '</span>';
        html += '</div>';

        html += '<div class="rcpt-divider"></div>';

        html += '<div class="rcpt-section-label">Hasil Pembayaran</div>';
        html += '<div class="rcpt-row"><span class="rcpt-row-label">Jumlah Masuk ke Tagihan</span><span class="rcpt-row-value green">− ' + formatRp(data.masuk) + '</span></div>';
        html += '<div class="rcpt-row"><span class="rcpt-row-label">Tagihan Terpotong</span><span class="rcpt-row-value green">− ' + formatRp(data.masuk) + '</span></div>';
        html += '<div class="rcpt-row"><span class="rcpt-row-label">Sisa Tagihan Setelah Bayar</span><span class="rcpt-row-value" style="color:#e53935;font-size:15px;">' + formatRp(parseFloat(data.saldo)) + '</span></div>';

    } else {
        // ── DEBT ADDITION ──
        html += '<div class="rcpt-hero">';
        html += '<div class="rcpt-check" style="background:#fce8e6;">';
        html += '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#e53935" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
        html += '</div>';
        html += '<div class="rcpt-status-title" style="color:#e53935;">Penambahan Utang</div>';
        html += '<div class="rcpt-status-sub">' + data.date + '</div>';
        html += '<div class="rcpt-big-amount">+ ' + formatRp(data.amount) + '</div>';
        html += '<div style="font-size:12px;color:#aaa;">Jumlah ditambahkan ke tagihan</div>';
        html += '</div>';

        html += '<div class="rcpt-divider"></div>';

        html += '<div class="rcpt-section-label">Info Transaksi</div>';
        html += '<div class="rcpt-row"><span class="rcpt-row-label">Keterangan</span><span class="rcpt-row-value">' + data.type + '</span></div>';
        html += '<div class="rcpt-row"><span class="rcpt-row-label">Nominal Ditambahkan</span><span class="rcpt-row-value red">+ ' + formatRp(data.amount) + '</span></div>';
        html += '<div class="rcpt-row"><span class="rcpt-row-label">Total Tagihan Setelah</span><span class="rcpt-row-value red">' + formatRp(parseFloat(data.saldo)) + '</span></div>';
        html += '<div class="rcpt-row"><span class="rcpt-row-label" style="font-size:11px;color:#bbb;">Tidak ada potongan untuk penambahan utang</span></div>';
    }

    // Payment number box
    html += '<div class="rcpt-number-box">';
    html += '<div><div class="rcpt-number-label">Nomor Transaksi</div><div class="rcpt-number-value">' + data.noTrx + '</div></div>';
    html += '</div>';

    // Close button
    html += '<button class="rcpt-close-full" onclick="closeReceipt()">Tutup &amp; Lihat Riwayat Lainnya</button>';

    document.getElementById('rcptBody').innerHTML = html;
    document.getElementById('rcptModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function copyNoPinjaman() {
    var el  = document.getElementById('noPinjaman');
    var btn = el.nextElementSibling;
    navigator.clipboard.writeText(el.textContent.trim()).then(function() {
        btn.textContent = 'Tersalin!';
        btn.style.background = '#e6f4ea';
        btn.style.color = '#1e8e3e';
        setTimeout(function() {
            btn.textContent = 'Salin';
            btn.style.background = '';
            btn.style.color = '';
        }, 2000);
    });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeModal(); closePayModal(); closeReceipt(); }
});
</script>
</body>
</html>
