<?php

$appName = $appName ?? ($config['app']['name'] ?? 'FormFlow');
?>

<section class="overflow-hidden rounded-3xl bg-zinc-950 px-6 py-16 text-white sm:px-12">
    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-400">Self-hosted forms</p>
    <h1 class="mt-4 max-w-2xl text-4xl font-semibold tracking-tight sm:text-5xl">Collect submissions. Own the data.</h1>
    <p class="mt-4 max-w-xl text-base text-zinc-300">
        <?= e((string) $appName) ?> is a private form backend with a visual builder, spam protection, webhooks, analytics, and a REST API — no SaaS lock-in.
    </p>
    <div class="mt-8 flex flex-wrap gap-3">
        <a href="/login" class="rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-zinc-950 hover:bg-zinc-100">Open dashboard</a>
        <a href="/health" class="rounded-lg border border-white/20 px-5 py-2.5 text-sm font-medium text-white hover:bg-white/10">System status</a>
    </div>
</section>

<section class="mt-12 grid gap-4 sm:grid-cols-3">
    <div class="rounded-2xl border border-zinc-200 bg-white p-6">
        <h2 class="font-semibold text-zinc-900">Visual builder</h2>
        <p class="mt-2 text-sm text-zinc-600">Drag-and-drop fields, multi-step forms, conditional logic, and live theme controls.</p>
    </div>
    <div class="rounded-2xl border border-zinc-200 bg-white p-6">
        <h2 class="font-semibold text-zinc-900">Protected inbox</h2>
        <p class="mt-2 text-sm text-zinc-600">Honeypot, reCAPTCHA, rate limits, file uploads, and CSV export on every form.</p>
    </div>
    <div class="rounded-2xl border border-zinc-200 bg-white p-6">
        <h2 class="font-semibold text-zinc-900">API + analytics</h2>
        <p class="mt-2 text-sm text-zinc-600">API keys, conversion tracking, referrers, and webhooks for your own stack.</p>
    </div>
</section>
