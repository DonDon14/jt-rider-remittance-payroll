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
        th { background: #f3f4f6; text-align: left; }
        .summary-table th { width: 45%; }
        .section { margin-top: 18px; }
    </style>
</head>
<body>
    <div class="branch-header">
        <div class="branch-title">J&amp;T Home Claveria Branch</div>
        <div class="branch-subtitle">Unpaid Salary Summary</div>
    </div>

    <h2>Unpaid Salary Summary</h2>
    <div>Coverage: <strong><?= esc($summary['start_date']) ?> to <?= esc($summary['end_date']) ?></strong></div>
    <div>Cutoff: <strong><?= esc($cutoffLabel) ?></strong></div>

    <table class="summary-table">
        <tr><th>Riders Pending</th><td><?= (int) ($summary['summary']['rider_count'] ?? 0) ?></td></tr>
        <tr><th>Gross Payables</th><td>PHP <?= number_format((float) ($summary['summary']['gross_total'] ?? 0), 2) ?></td></tr>
        <tr><th>Bonuses</th><td>PHP <?= number_format((float) ($summary['summary']['bonus_total'] ?? 0), 2) ?></td></tr>
        <tr><th>Deductions</th><td>PHP <?= number_format((float) ($summary['summary']['deduction_total'] ?? 0), 2) ?></td></tr>
        <tr><th>Shortage Deductions</th><td>PHP <?= number_format((float) ($summary['summary']['shortage_total'] ?? 0), 2) ?></td></tr>
        <tr><th>Repayments</th><td>PHP <?= number_format((float) ($summary['summary']['repayment_total'] ?? 0), 2) ?></td></tr>
        <tr><th>Net Payables</th><td><strong>PHP <?= number_format((float) ($summary['summary']['net_total'] ?? 0), 2) ?></strong></td></tr>
    </table>

    <div class="section">
        <h3>Rider Breakdown</h3>
        <table>
            <thead>
                <tr>
                    <th>Rider</th>
                    <th>Successful</th>
                    <th>Gross</th>
                    <th>Bonus</th>
                    <th>Deduction</th>
                    <th>Shortage</th>
                    <th>Repayments</th>
                    <th>Outstanding Shortage</th>
                    <th>Net Pay</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($summary['rows'] as $row): ?>
                    <tr>
                        <td><?= esc($row['rider_code']) ?> - <?= esc($row['name']) ?></td>
                        <td><?= (int) ($row['total_successful'] ?? 0) ?></td>
                        <td>PHP <?= number_format((float) ($row['gross_earnings'] ?? 0), 2) ?></td>
                        <td>PHP <?= number_format((float) ($row['bonus_total'] ?? 0), 2) ?></td>
                        <td>PHP <?= number_format((float) ($row['deduction_total'] ?? 0), 2) ?></td>
                        <td>PHP <?= number_format((float) ($row['shortage_deductions'] ?? 0), 2) ?></td>
                        <td>PHP <?= number_format((float) ($row['shortage_payments_received'] ?? 0), 2) ?></td>
                        <td>PHP <?= number_format((float) ($row['outstanding_shortage_balance'] ?? 0), 2) ?></td>
                        <td>PHP <?= number_format((float) ($row['net_pay'] ?? 0), 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($summary['rows'])): ?>
                    <tr><td colspan="9">No unpaid salary items were found for this cutoff.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
