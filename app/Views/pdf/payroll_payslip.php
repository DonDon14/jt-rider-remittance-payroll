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
        th, td { border: 1px solid #d1d5db; padding: 8px; }
        th { background: #f3f4f6; text-align: left; width: 45%; }
        .detail-table th, .detail-table td { width: auto; }
        .section { margin-top: 16px; }
    </style>
</head>
<body>
    <div class="branch-header">
        <div class="branch-title">J&amp;T Home Claveria Branch</div>
        <div class="branch-subtitle">Rider Payroll Record</div>
    </div>

    <h2>Payroll Payslip</h2>
    <div>Rider: <strong><?= esc($payroll['rider_code']) ?> - <?= esc($payroll['name']) ?></strong></div>
    <div>Coverage: <strong><?= esc($payroll['start_date'] ?? $payroll['month_year']) ?> to <?= esc($payroll['end_date'] ?? $payroll['month_year']) ?></strong></div>

    <table>
        <tr><th>Total Successful Deliveries</th><td><?= (int) $payroll['total_successful'] ?></td></tr>
        <tr><th>Commission Rate</th><td>PHP <?= number_format((float) $payroll['commission_rate'], 2) ?></td></tr>
        <tr><th>Gross Earnings</th><td>PHP <?= number_format((float) $payroll['gross_earnings'], 2) ?></td></tr>
        <tr><th>Salary Earnings Included</th><td>PHP <?= number_format((float) $payroll['total_due'], 2) ?></td></tr>
        <tr><th>Actual Remittance Recorded</th><td>PHP <?= number_format((float) $payroll['total_remitted'], 2) ?></td></tr>
        <tr><th>Remittance Variance Snapshot</th><td>PHP <?= number_format((float) $payroll['remittance_variance'], 2) ?></td></tr>
        <tr><th>Shortage Deductions Included</th><td>PHP <?= number_format((float) ($payroll['shortage_deductions'] ?? 0), 2) ?></td></tr>
        <tr><th>Shortage Repayments Included</th><td>PHP <?= number_format((float) ($payroll['shortage_payments_received'] ?? 0), 2) ?></td></tr>
        <tr><th>Bonuses Included</th><td>PHP <?= number_format((float) ($payroll['bonus_total'] ?? 0), 2) ?></td></tr>
        <tr><th>Deductions Included</th><td>PHP <?= number_format((float) ($payroll['deduction_total'] ?? 0), 2) ?></td></tr>
        <tr><th>Outstanding Shortage Balance</th><td>PHP <?= number_format((float) ($payroll['outstanding_shortage_balance'] ?? 0), 2) ?></td></tr>
        <tr><th>Net Pay</th><td><strong>PHP <?= number_format((float) $payroll['net_pay'], 2) ?></strong></td></tr>
    </table>

    <div class="section">
        <h3>Included Adjustment Details</h3>
        <?php if (! empty($adjustments)): ?>
            <table class="detail-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Batch</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($adjustments as $adjustment): ?>
                        <tr>
                            <td><?= esc($adjustment['type']) ?></td>
                            <td><?= esc($adjustment['description']) ?></td>
                            <td><?= esc($adjustment['batch_reference'] ?: '-') ?></td>
                            <td>PHP <?= number_format((float) $adjustment['amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div>No payroll adjustments were included in this payslip.</div>
        <?php endif; ?>
    </div>
</body>
</html>

