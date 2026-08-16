<?php

declare(strict_types=1);

use FormFlow\StatsRepository;

$userId = (int) ($currentUser['id'] ?? 0);
$stats = new StatsRepository($config);
$summary = $stats->dashboardSummary($userId);
$chartData = $stats->submissionsChart($userId, 30);
$byForm = $stats->submissionsByForm($userId);
$referrers = $stats->topReferrers($userId, 10);

$chartLabels = json_encode(array_column($chartData, 'date'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$chartCounts = json_encode(array_column($chartData, 'count'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$maxFormSubs = 0;
foreach ($byForm as $row) {
    $maxFormSubs = max($maxFormSubs, (int) $row['submissions']);
}
?>

<div class="mb-8">
    <h2 class="text-3xl font-bold tracking-tight text-zinc-900">Analytics</h2>
    <p class="mt-2 text-sm text-zinc-500">Submission trends across all of your forms.</p>
</div>

<div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="rounded-xl border border-border bg-white p-6 shadow-sm">
        <p class="text-sm font-medium text-zinc-500">Total forms</p>
        <p class="mt-3 text-3xl font-bold text-zinc-900"><?= (int) $summary['total_forms'] ?></p>
    </div>
    <div class="rounded-xl border border-border bg-white p-6 shadow-sm">
        <p class="text-sm font-medium text-zinc-500">Total submissions</p>
        <p class="mt-3 text-3xl font-bold text-zinc-900"><?= (int) $summary['total_submissions'] ?></p>
    </div>
    <div class="rounded-xl border border-border bg-white p-6 shadow-sm">
        <p class="text-sm font-medium text-zinc-500">This week</p>
        <p class="mt-3 text-3xl font-bold text-zinc-900"><?= (int) $summary['submissions_this_week'] ?></p>
    </div>
    <div class="rounded-xl border border-border bg-white p-6 shadow-sm">
        <p class="text-sm font-medium text-zinc-500">Spam caught</p>
        <p class="mt-3 text-3xl font-bold text-zinc-900"><?= (int) $summary['spam_caught'] ?></p>
    </div>
</div>

<div class="mb-8 grid gap-6 lg:grid-cols-3">
    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm lg:col-span-2">
        <h3 class="mb-4 text-sm font-semibold text-zinc-900">Submissions over the last 30 days</h3>
        <canvas id="analyticsOverviewChart" height="120"></canvas>
    </div>
    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold text-zinc-900">Top referrers</h3>
        <?php if ($referrers === []): ?>
            <p class="text-sm text-zinc-500">No referrer data yet.</p>
        <?php else: ?>
            <ul class="space-y-2 text-sm">
                <?php foreach ($referrers as $ref): ?>
                    <li class="flex justify-between gap-2">
                        <span class="truncate text-zinc-700"><?= e((string) $ref['domain']) ?></span>
                        <span class="shrink-0 font-medium text-zinc-900"><?= (int) $ref['count'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
    <h3 class="mb-4 text-sm font-semibold text-zinc-900">By form</h3>
    <?php if ($byForm === []): ?>
        <p class="text-sm text-zinc-500">No forms yet. Create a form to start collecting analytics.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-100 text-xs uppercase tracking-wide text-zinc-500">
                        <th class="pb-3 font-medium">Form</th>
                        <th class="pb-3 font-medium">Submissions</th>
                        <th class="pb-3 font-medium">Spam</th>
                        <th class="pb-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <?php foreach ($byForm as $row): ?>
                        <?php $pct = $maxFormSubs > 0 ? round(((int) $row['submissions'] / $maxFormSubs) * 100) : 0; ?>
                        <tr>
                            <td class="py-3 font-medium text-zinc-900"><?= e((string) $row['form_name']) ?></td>
                            <td class="py-3">
                                <div class="flex items-center gap-3">
                                    <span class="w-10 shrink-0 text-zinc-900"><?= (int) $row['submissions'] ?></span>
                                    <div class="h-2 w-32 overflow-hidden rounded-full bg-zinc-100">
                                        <div class="h-full rounded-full bg-zinc-900" style="width: <?= $pct ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 text-zinc-600"><?= (int) $row['spam'] ?></td>
                            <td class="py-3 text-right">
                                <a href="/admin/forms/<?= (int) $row['form_id'] ?>/analytics" class="text-zinc-500 hover:text-zinc-900 hover:underline">View details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
(() => {
  const ctx = document.getElementById('analyticsOverviewChart');
  if (!ctx || typeof Chart === 'undefined') return;
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= $chartLabels ?>,
      datasets: [{
        label: 'Submissions',
        data: <?= $chartCounts ?>,
        borderColor: '#18181b',
        backgroundColor: 'rgba(24, 24, 27, 0.08)',
        fill: true,
        tension: 0.3,
        pointRadius: 0,
        borderWidth: 2,
      }],
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
    },
  });
})();
</script>
