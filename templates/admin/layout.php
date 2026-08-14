<!DOCTYPE html>
<html lang="en" class="h-full antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Admin') ?> — <?= e($appName ?? 'FormFlow') ?></title>
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
</head>
<body class="h-full bg-zinc-50 font-sans" x-data="{ sidebarOpen: true }">
    <div class="flex h-full min-h-screen">
        <aside
            class="w-64 shrink-0 border-r border-zinc-800 bg-zinc-900 text-zinc-100 transition-all duration-200"
            :class="sidebarOpen ? 'block' : 'hidden lg:block lg:w-16'"
        >
            <div class="flex h-16 items-center border-b border-zinc-800 px-4">
                <span class="text-lg font-semibold tracking-tight"><?= e($appName ?? 'FormFlow') ?></span>
            </div>
            <nav class="p-4 text-sm text-zinc-400">
                <p class="mb-2 text-xs uppercase tracking-wider text-zinc-500">Navigation</p>
                <ul class="space-y-1">
                    <li>
                        <a href="/admin" class="block rounded-md px-3 py-2 hover:bg-zinc-800 hover:text-zinc-100">Dashboard</a>
                    </li>
                    <li>
                        <a href="/admin/forms" class="block rounded-md px-3 py-2 hover:bg-zinc-800 hover:text-zinc-100">Forms</a>
                    </li>
                    <li>
                        <a href="/admin/templates" class="block rounded-md px-3 py-2 hover:bg-zinc-800 hover:text-zinc-100">Templates</a>
                    </li>
                    <?php if (!empty($currentUser) && (string) ($currentUser['role'] ?? '') === 'admin'): ?>
                    <li>
                        <a href="/admin/settings" class="block rounded-md px-3 py-2 hover:bg-zinc-800 hover:text-zinc-100">Settings</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-16 items-center justify-between border-b border-zinc-200 bg-white px-4 shadow-sm">
                <button
                    type="button"
                    class="rounded-md p-2 text-zinc-500 hover:bg-zinc-100 lg:hidden"
                    @click="sidebarOpen = !sidebarOpen"
                    aria-label="Toggle sidebar"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <h1 class="text-sm font-medium text-zinc-700"><?= e($pageTitle ?? 'Admin') ?></h1>
                <div class="flex items-center gap-4">
                    <?php if (!empty($currentUser)): ?>
                        <span class="hidden text-sm text-zinc-600 sm:inline"><?= e((string) $currentUser['name']) ?></span>
                        <form method="post" action="/logout" class="inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="rounded-md border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 shadow-sm hover:bg-zinc-50">
                                Sign out
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-6">
                <?php $success = flash('success'); $error = flash('error'); ?>
                <?php if ($success): ?>
                    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= e($success) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= e($error) ?></div>
                <?php endif; ?>
                <?= $content ?? '' ?>
            </main>

            <footer class="border-t border-zinc-200 bg-white px-4 py-3 text-center text-xs text-zinc-400">
                &copy; <?= date('Y') ?> <?= e($appName ?? 'FormFlow') ?>. All rights reserved.
            </footer>
        </div>
    </div>
</body>
</html>
