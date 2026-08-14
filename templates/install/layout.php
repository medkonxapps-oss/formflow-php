<!DOCTYPE html>
<html lang="en" class="h-full antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= e($appName) ?> Installer</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                }
            }
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-full bg-zinc-50 font-sans text-zinc-900">
    <div class="mx-auto max-w-3xl px-4 py-10">
        <div class="mb-8 text-center">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-zinc-900 text-sm font-bold text-white">FF</div>
            <h1 class="text-2xl font-semibold tracking-tight">Install <?= e($appName) ?></h1>
            <p class="mt-1 text-sm text-zinc-500">Step <?= (int) $currentStep ?> of <?= count($steps) ?> — <?= e($pageTitle) ?></p>
        </div>

        <!-- Progress indicator -->
        <nav class="mb-8" aria-label="Install progress">
            <div class="flex items-center justify-between gap-2">
                <?php foreach ($steps as $num => $step): ?>
                    <?php
                    $done = $num < $currentStep;
                    $active = $num === $currentStep;
                    $slug = $step['slug'] === 'requirements' ? '' : $step['slug'];
                    $href = '/install/' . $slug;
                    $canVisit = $num <= \FormFlow\InstallState::maxAllowedStep();
                    ?>
                    <div class="flex flex-1 flex-col items-center">
                        <a href="<?= ($canVisit && !$active) ? e($href) : '#' ?>"
                           class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold
                               <?= $active ? 'bg-zinc-900 text-white ring-4 ring-zinc-200' : ($done ? 'bg-emerald-600 text-white' : 'bg-zinc-200 text-zinc-500') ?>
                               <?= (!$canVisit || $active) ? 'pointer-events-none' : '' ?>"
                           <?= $active ? 'aria-current="step"' : '' ?>>
                            <?= $done ? '✓' : (string) $num ?>
                        </a>
                        <span class="mt-1 hidden text-center text-[10px] font-medium text-zinc-500 sm:block"><?= e($step['title']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </nav>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8">
            <?php
            $success = flash('success');
            $error = flash('error');
            ?>
            <?php if ($success): ?>
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= e($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= e($error) ?></div>
            <?php endif; ?>
            <?= $content ?? '' ?>
        </div>
    </div>
</body>
</html>
