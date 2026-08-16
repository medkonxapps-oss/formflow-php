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

<div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h2 class="text-3xl font-bold tracking-tight text-zinc-900">Dashboard</h2>
        <p class="mt-2 text-sm text-zinc-500">Overview of your forms and recent activity.</p>
    </div>
    <?php if ($isEditor): ?>
        <div class="flex items-center gap-3">
            <a href="/admin/templates" class="shadcn-btn shadcn-btn-outline gap-2">
                <i data-lucide="layout-template" class="h-4 w-4"></i>
                Templates
            </a>
            <a href="/admin/forms/new" class="shadcn-btn shadcn-btn-primary gap-2">
                <i data-lucide="plus" class="h-4 w-4"></i>
                New Form
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Stat Cards -->
<div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="rounded-xl border border-border bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-medium text-zinc-500 tracking-tight">Total Forms</h3>
            <i data-lucide="file-text" class="h-4 w-4 text-zinc-400"></i>
        </div>
        <div class="mt-4 flex items-baseline gap-2">
            <span class="text-3xl font-bold text-zinc-900"><?= (int) $summary['total_forms'] ?></span>
        </div>
        <p class="mt-1 text-xs text-zinc-400">Active and draft forms</p>
    </div>

    <div class="rounded-xl border border-border bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-medium text-zinc-500 tracking-tight">Total Submissions</h3>
            <i data-lucide="users" class="h-4 w-4 text-zinc-400"></i>
        </div>
        <div class="mt-4 flex items-baseline gap-2">
            <span class="text-3xl font-bold text-zinc-900"><?= (int) $summary['total_submissions'] ?></span>
        </div>
        <p class="mt-1 text-xs text-zinc-400">All-time entries</p>
    </div>

    <div class="rounded-xl border border-border bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-medium text-zinc-500 tracking-tight">This Week</h3>
            <i data-lucide="activity" class="h-4 w-4 text-zinc-400"></i>
        </div>
        <div class="mt-4 flex items-baseline gap-2">
            <span class="text-3xl font-bold text-zinc-900"><?= (int) $summary['submissions_this_week'] ?></span>
            <span class="flex items-center text-xs font-medium text-emerald-600">
                <i data-lucide="trending-up" class="mr-1 h-3 w-3"></i>
                Active
            </span>
        </div>
        <p class="mt-1 text-xs text-zinc-400">Submissions in last 7 days</p>
    </div>

    <div class="rounded-xl border border-border bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-medium text-zinc-500 tracking-tight">Spam Caught</h3>
            <i data-lucide="shield-alert" class="h-4 w-4 text-red-400"></i>
        </div>
        <div class="mt-4 flex items-baseline gap-2">
            <span class="text-3xl font-bold text-zinc-900"><?= (int) $summary['spam_caught'] ?></span>
        </div>
        <p class="mt-1 text-xs text-zinc-400">Blocked by spam filters</p>
    </div>
</div>

<div class="mb-8 grid gap-6 lg:grid-cols-7">
    <!-- Chart -->
    <div class="rounded-xl border border-border bg-white shadow-sm lg:col-span-4">
        <div class="border-b border-border p-6">
            <h3 class="text-base font-semibold leading-6 text-zinc-900">Overview</h3>
            <p class="text-sm text-zinc-500">Submissions over the last 30 days.</p>
        </div>
        <div class="p-6">
            <canvas id="dashboardChart" height="200"></canvas>
        </div>
    </div>

    <!-- Forms List -->
    <div class="rounded-xl border border-border bg-white shadow-sm lg:col-span-3">
        <div class="border-b border-border p-6">
            <h3 class="text-base font-semibold leading-6 text-zinc-900">Your Forms</h3>
            <p class="text-sm text-zinc-500">Quick access to your most active forms.</p>
        </div>
        <div class="p-2">
            <?php if ($formList === []): ?>
                <div class="flex flex-col items-center justify-center p-8 text-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100">
                        <i data-lucide="file-x" class="h-6 w-6 text-zinc-400"></i>
                    </div>
                    <p class="mt-4 text-sm text-zinc-500">No forms yet.</p>
                </div>
            <?php else: ?>
                <div class="space-y-1">
                    <?php foreach (array_slice($formList, 0, 6) as $form): ?>
                        <a href="/admin/forms/<?= (int) $form['id'] ?>/edit" class="flex items-center justify-between rounded-md p-3 hover:bg-zinc-50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-zinc-100 border border-zinc-200">
                                    <i data-lucide="layout" class="h-4 w-4 text-zinc-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-zinc-900"><?= e((string) $form['name']) ?></p>
                                    <p class="text-xs text-zinc-500">Created <?= date('M d, Y', strtotime((string)$form['created_at'])) ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-semibold text-zinc-800">
                                    <?= (int) ($form['submission_count'] ?? 0) ?> subs
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Submissions Table -->
<div class="rounded-xl border border-border bg-white shadow-sm">
    <div class="border-b border-border p-6">
        <h3 class="text-base font-semibold leading-6 text-zinc-900">Recent Submissions</h3>
        <p class="text-sm text-zinc-500">Latest entries across all your forms.</p>
    </div>
    <div class="p-0">
        <?php if ($recentSubs === []): ?>
            <div class="flex flex-col items-center justify-center p-12 text-center">
                <i data-lucide="inbox" class="h-10 w-10 text-zinc-300"></i>
                <p class="mt-4 text-sm text-zinc-500">No submissions found.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left whitespace-nowrap">
                    <thead class="bg-zinc-50 text-zinc-500">
                        <tr>
                            <th class="px-6 py-3 font-medium">Form Name</th>
                            <th class="px-6 py-3 font-medium">Preview Data</th>
                            <th class="px-6 py-3 font-medium">Date Received</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                            <th class="px-6 py-3 font-medium text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
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
                            <tr class="hover:bg-zinc-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2 font-medium text-zinc-900">
                                        <i data-lucide="file-text" class="h-4 w-4 text-zinc-400"></i>
                                        <?= e((string) ($sub['form_name'] ?? '')) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="max-w-[200px] truncate text-zinc-600">
                                        <?= e($preview ?: '(Empty)') ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-zinc-500">
                                    <?= date('M d, Y g:i A', strtotime((string)$sub['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($sub['is_spam'])): ?>
                                        <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20">Spam</span>
                                    <?php elseif (empty($sub['is_read'])): ?>
                                        <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">New</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center rounded-full bg-zinc-50 px-2 py-1 text-xs font-medium text-zinc-600 ring-1 ring-inset ring-zinc-500/10">Read</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="/admin/forms/<?= (int) $sub['form_id'] ?>/submissions/<?= (int) $sub['id'] ?>" class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900">
                                        View
                                        <i data-lucide="arrow-right" class="h-3 w-3"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const ctx = document.getElementById('dashboardChart');
  if (!ctx) return;

  // Chart gradient
  const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 400);
  gradient.addColorStop(0, 'rgba(24, 24, 27, 0.15)');
  gradient.addColorStop(1, 'rgba(24, 24, 27, 0)');

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= $chartLabels ?>,
      datasets: [{
        label: 'Submissions',
        data: <?= $chartCounts ?>,
        borderColor: '#18181b', // zinc-900
        backgroundColor: gradient,
        borderWidth: 2,
        pointBackgroundColor: '#fff',
        pointBorderColor: '#18181b',
        pointBorderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 6,
        fill: true,
        tension: 0.4, // Smooth curve
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { 
          legend: { display: false },
          tooltip: {
              backgroundColor: '#18181b',
              titleFont: { family: 'Inter', size: 13 },
              bodyFont: { family: 'Inter', size: 14, weight: 'bold' },
              padding: 12,
              cornerRadius: 8,
              displayColors: false
          }
      },
      scales: {
        y: { 
            beginAtZero: true, 
            ticks: { precision: 0, color: '#a1a1aa' },
            grid: { color: '#f4f4f5', drawBorder: false }
        },
        x: { 
            ticks: { maxTicksLimit: 8, color: '#a1a1aa' },
            grid: { display: false, drawBorder: false }
        },
      },
    },
  });
});
</script>
