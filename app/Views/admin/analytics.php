<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-hero">
    <div>
        <h2 class="mb-0">Analytics</h2>
        <p>Inspect delivery throughput, earnings trend, and rider performance ranking.</p>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card chart-card">
            <div class="card-header fw-semibold">14-Day Delivery Trend</div>
            <div class="card-body">
                <canvas id="deliveryTrendChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card chart-card h-100">
            <div class="card-header fw-semibold">14-Day Earnings Trend</div>
            <div class="card-body">
                <canvas id="earningsTrendChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card rank-card h-100">
            <div class="card-header fw-semibold">Top Performing Riders</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Rider</th>
                            <th>Success %</th>
                            <th>Delivered</th>
                            <th>Earnings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topRiders as $index => $rider): ?>
                            <tr>
                                <td><?= $index + 1 ?>. <?= esc($rider['rider_code']) ?> - <?= esc($rider['name']) ?></td>
                                <td><?= number_format((float) $rider['success_rate'], 2) ?>%</td>
                                <td><?= (int) $rider['successful_total'] ?></td>
                                <td>PHP <?= number_format((float) $rider['earning_total'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($topRiders)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No rider performance data yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card rank-card h-100">
            <div class="card-header fw-semibold">Lower Performing Riders</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Rider</th>
                            <th>Success %</th>
                            <th>Delivered</th>
                            <th>Earnings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowRiders as $index => $rider): ?>
                            <tr>
                                <td><?= $index + 1 ?>. <?= esc($rider['rider_code']) ?> - <?= esc($rider['name']) ?></td>
                                <td><?= number_format((float) $rider['success_rate'], 2) ?>%</td>
                                <td><?= (int) $rider['successful_total'] ?></td>
                                <td>PHP <?= number_format((float) $rider['earning_total'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($lowRiders)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No rider performance data yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(() => {
    const labels = <?= json_encode($chartLabels) ?>;
    const allocated = <?= json_encode($allocatedSeries) ?>;
    const successful = <?= json_encode($successfulSeries) ?>;
    const earnings = <?= json_encode($earningsSeries) ?>;

    const deliveryCtx = document.getElementById('deliveryTrendChart');
    if (deliveryCtx) {
        new Chart(deliveryCtx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    { label: 'Allocated', data: allocated, borderColor: '#ef6c00', backgroundColor: 'rgba(239,108,0,0.12)', tension: 0.3, fill: true },
                    { label: 'Successful', data: successful, borderColor: '#c62828', backgroundColor: 'rgba(198,40,40,0.12)', tension: 0.3, fill: true }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    const earningsCtx = document.getElementById('earningsTrendChart');
    if (earningsCtx) {
        new Chart(earningsCtx, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    { label: 'Earnings', data: earnings, backgroundColor: '#c62828' }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }
})();
</script>
<?= $this->endSection() ?>
