<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? $appName ?? 'FormFlow', ENT_QUOTES, 'UTF-8') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="flex min-h-screen flex-col bg-white text-gray-900">
    <!-- Public header placeholder -->
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
            <a href="/" class="text-lg font-semibold tracking-tight text-gray-900">
                <?= htmlspecialchars($appName ?? 'FormFlow', ENT_QUOTES, 'UTF-8') ?>
            </a>
            <nav class="text-sm text-gray-600">
                <a href="/health" class="hover:text-gray-900">System Status</a>
            </nav>
        </div>
    </header>

    <!-- Main content -->
    <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-10">
        <?= $content ?? '' ?>
    </main>

    <!-- Public footer placeholder -->
    <footer class="border-t border-gray-200 bg-gray-50">
        <div class="mx-auto max-w-5xl px-4 py-6 text-center text-sm text-gray-500">
            Powered by <?= htmlspecialchars($appName ?? 'FormFlow', ENT_QUOTES, 'UTF-8') ?>
        </div>
    </footer>
</body>
</html>
