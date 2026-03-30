<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1, h2, h3 { margin: 0 0 8px 0; }
        .branch-header { margin-bottom: 14px; }
        .branch-title { font-size: 18px; font-weight: 700; }
        .branch-subtitle { color: #4b5563; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; }
        th { background: #f3f4f6; }
        .summary { margin-top: 12px; }
        .badge { display: inline-block; padding: 4px 7px; border-radius: 8px; font-size: 11px; }
        .short { background: #fee2e2; color: #991b1b; }
        .over { background: #dcfce7; color: #166534; }
        .balanced { background: #e0f2fe; color: #075985; }
        .pending { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    <div class="branch-header">
        <div class="branch-title">J&amp;T Home Claveria Branch</div>
        <div class="branch-subtitle">Rider Remittance Record</div>
    </div>

    <h2>Remittance Receipt</h2>
    <div>Rider: <strong><?= esc($record['rider_code']) ?> - <?= esc($record['name']) ?></strong></div>
    <div>Date: <strong><?= esc($record['delivery_date']) ?></strong></div>

    <table>
        <thead><tr><th>Denomination</th><th>Quantity</th><th>Amount</th></tr></thead>
        <tbody>
            <?php foreach ($denominations as $field => $value): ?>
                <?php $qty = (int) ($record[$field] ?? 0); ?>
                <tr>
                    <td><?= $value < 1 ? '25c' : 'PHP ' . number_format($value, 2) ?></td>
                    <td><?= $qty ?></td>
                    <td>PHP <?= number_format($qty * $value, 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="summary">Expected Remittance: <strong>PHP <?= number_format((float) ($record['supposed_remittance'] ?? 0), 2) ?></strong></div>
    <div class="summary">Salary Earning Snapshot: <strong>PHP <?= number_format((float) $record['total_due'], 2) ?></strong></div>
    <div class="summary">Total Remitted: <strong>PHP <?= number_format((float) $record['total_remitted'], 2) ?></strong></div>
    <?php if (! empty($record['actual_remitted'])): ?>
        <div class="summary">Actual Remitted (Manual): <strong>PHP <?= number_format((float) $record['actual_remitted'], 2) ?></strong></div>
    <?php endif; ?>
    <div class="summary">
        Status:
        <span class="badge <?= strtolower($record['variance_type']) ?>">
            <?= esc($record['variance_type']) ?> (PHP <?= number_format((float) $record['variance_amount'], 2) ?>)
        </span>
    </div>
</body>
</html>
