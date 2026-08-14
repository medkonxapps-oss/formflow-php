<?php

declare(strict_types=1);

use FormFlow\FormRepository;
use FormFlow\StatsRepository;

$userId = (int) ($currentUser['id'] ?? 0);
$statsRepo = new StatsRepository($config);
$summary = $statsRepo->dashboardSummary($userId);
$recentSubs = $statsRepo->recentSubmissions($userId, 10);
$chartData = $statsRepo->submissionsChart($userId, 30);
$forms = new FormRepository($config);
$formList = $forms->listForUser($userId);
$isEditor = in_array((string) ($currentUser['role'] ?? ''), ['admin', 'editor'], true);

$chartLabels = json_encode(array_column($chartData, 'date'));
$chartCounts = json_encode(array_column($chartData, 'count'));
?>

<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h2 class="text-2xl font-semibold text-zinc-900">Dashboard</h2>
        <p class="mt-1 text-sm text-zinc-500">Overview of your forms and recent activity.</p>
    </div>
    <?php if ($isEditor): ?>
        <div class="flex gap-2">
            <a href="/admin/templates" class="inline-flex h-10 items-center rounded-md border border-zinc-200 bg-white px-4 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Templates</a>
            <a href="/admin/forms/new" class="inline-flex h-10 items-center rounded-md bg-zinc-900 px-4 text-sm font-medium text-white hover:bg-zinc-800">+ New Form</a>
        </div>
    <?php endif; ?>
</div>

<div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-zinc-500">Total Forms</p>
        <p class="mt-1 text-3xl font-semibold text-zinc-900"><?= (int) $summary['total_forms'] ?></p>
    </div>
    <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-zinc-500">Total Submissions</p>
        <p class="mt-1 text-3xl font-semibold text-zinc-900"><?= (int) $summary['total_submissions'] ?></p>
    </div>
    <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-zinc-500">This Week</p>
        <p class="mt-1 text-3xl font-semibold text-zinc-900"><?= (int) $summary['submissions_this_week'] ?></p>
    </div>
    <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-zinc-500">Spam Caught</p>
        <p class="mt-1 text-3xl font-semibold text-amber-700"><?= (int) $summary['spam_caught'] ?></p>
    </div>
</div>

<div class="mb-8 grid gap-6 lg:grid-cols-3">
    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm lg:col-span-2">
        <h3 class="mb-4 text-sm font-semibold text-zinc-900">Submissions — last 30 days</h3>
        <canvas id="dashboardChart" height="120"></canvas>
    </div>
    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold text-zinc-900">Your forms</h3>
        <?php if ($formList === []): ?>
            <p class="text-sm text-zinc-500">No forms yet.</p>
        <?php else: ?>
            <ul class="divide-y divide-zinc-100 text-sm">
                <?php foreach (array_slice($formList, 0, 6) as $form): ?>
                    <li class="flex items-center justify-between py-2">
                        <a href="/admin/forms/<?= (int) $form['id'] ?>/edit" class="font-medium text-zinc-900 hover:underline"><?= e((string) $form['name']) ?></a>
                        <span class="text-xs text-zinc-400"><?= (int) ($form['submission_count'] ?? 0) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
    <h3 class="mb-4 text-sm font-semibold text-zinc-900">Recent submissions</h3>
    <?php if ($recentSubs === []): ?>
        <p class="text-sm text-zinc-500">No submissions yet.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-100 text-left text-zinc-500">
                        <th class="pb-2 pr-4 font-medium">Form</th>
                        <th class="pb-2 pr-4 font-medium">Preview</th>
                        <th class="pb-2 pr-4 font-medium">When</th>
                        <th class="pb-2 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-50">
                    <?php foreach ($recentSubs as $sub): ?>
                        <?php
                        $data = is_array($sub['data'] ?? null) ? $sub['data'] : [];
                        $preview = '';
                        foreach ($data as $val) {
                            if (is_string($val) && $val !== '') {
                                $preview = $val;
                                break;
                            }
                        }
                        ?>
                        <tr>
                            <td class="py-2 pr-4">
                                <a href="/admin/forms/<?= (int) $sub['form_id'] ?>/submissions/<?= (int) $sub['id'] ?>" class="font-medium text-zinc-900 hover:underline">
                                    <?= e((string) ($sub['form_name'] ?? '')) ?>
                                </a>
                            </td>
                            <td class="max-w-xs truncate py-2 pr-4 text-zinc-600"><?= e($preview) ?></td>
                            <td class="py-2 pr-4 text-zinc-500"><?= e((string) ($sub['created_at'] ?? '')) ?></td>
                            <td class="py-2">
                                <?php if (!empty($sub['is_spam'])): ?>
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">spam</span>
                                <?php elseif (empty($sub['is_read'])): ?>
                                    <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-800">new</span>
                                <?php else: ?>
                                    <span class="text-xs text-zinc-400">read</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(() => {
  const ctx = document.getElementById('dashboardChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= $chartLabels ?>,
      datasets: [{
        label: 'Submissions',
        data: <?= $chartCounts ?>,
        borderColor: '#18181b',
        backgroundColor: 'rgba(24,24,27,0.08)',
        fill: true,
        tension: 0.3,
      }],
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } },
        x: { ticks: { maxTicksLimit: 8 } },
      },
    },
  });
})();
</script>
