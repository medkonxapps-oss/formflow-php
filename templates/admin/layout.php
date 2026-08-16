<!DOCTYPE html>
<html lang="en" class="h-full antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Admin') ?> — <?= e($appName ?? 'FormFlow') ?></title>
    
    <!-- Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                    colors: {
                        background: 'hsl(0, 0%, 100%)',
                        foreground: 'hsl(240, 10%, 3.9%)',
                        muted: 'hsl(240, 4.8%, 95.9%)',
                        'muted-foreground': 'hsl(240, 3.8%, 46.1%)',
                        border: 'hsl(240, 5.9%, 90%)',
                        primary: 'hsl(240, 5.9%, 10%)',
                        'primary-foreground': 'hsl(0, 0%, 98%)',
                    },
                    boxShadow: {
                        'sm': '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
                        DEFAULT: '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1)',
                        'md': '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1)',
                    }
                }
            }
        }
    </script>
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style type="text/tailwindcss">
        /* Base styles */
        body { @apply bg-zinc-50 text-zinc-950; }
        
        .shadcn-btn {
            @apply inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors h-9 px-4;
        }
        .shadcn-btn-primary { 
            @apply bg-zinc-900 text-zinc-50 hover:bg-zinc-900/90 shadow; 
        }
        .shadcn-btn-outline { 
            @apply border border-zinc-200 bg-white hover:bg-zinc-100 hover:text-zinc-900 shadow-sm; 
        }
        
        .shadcn-input {
            @apply flex h-9 w-full rounded-md border border-zinc-200 bg-transparent px-3 py-1 text-sm transition-colors focus:outline-none focus:ring-1 focus:ring-zinc-400;
        }
        
        .nav-item {
            @apply flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-zinc-600 transition-all;
        }
        .nav-item:hover, .nav-item.active { 
            @apply bg-zinc-100 text-zinc-900; 
        }
        .nav-icon { @apply h-5 w-5; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="h-full bg-zinc-50 font-sans" x-data="{ sidebarOpen: false }">
    
    <!-- Mobile sidebar backdrop -->
    <div x-show="sidebarOpen" class="fixed inset-0 z-40 bg-zinc-950/50 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false" x-cloak></div>

    <div class="flex h-full min-h-screen">
        
        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-64 transform flex-col border-r border-border bg-white transition-transform duration-200 ease-in-out lg:static lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-14 items-center border-b border-border px-6">
                <a href="/admin" class="flex items-center gap-2 font-bold tracking-tight text-zinc-900">
                    <i data-lucide="layers" class="h-5 w-5"></i>
                    <?= e($appName ?? 'FormFlow') ?>
                </a>
                <button type="button" @click="sidebarOpen = false" class="ml-auto text-zinc-500 lg:hidden">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            
            <nav class="flex-1 space-y-1 p-4">
                <p class="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-zinc-400">Overview</p>
                
                <?php $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); ?>
                
                <a href="/admin" class="nav-item <?= $path === '/admin' ? 'active' : '' ?>">
                    <i data-lucide="layout-dashboard" class="nav-icon"></i>
                    Dashboard
                </a>
                <a href="/admin/forms" class="nav-item <?= str_starts_with($path, '/admin/forms') && !str_contains($path, '/analytics') ? 'active' : '' ?>">
                    <i data-lucide="file-text" class="nav-icon"></i>
                    Forms
                </a>
                <a href="/admin/submissions" class="nav-item <?= str_starts_with($path, '/admin/submissions') ? 'active' : '' ?>">
                    <i data-lucide="inbox" class="nav-icon"></i>
                    Submissions
                    <?php
                    $unreadNav = 0;
                    if (!empty($currentUser['id']) && isset($config) && is_array($config)) {
                        try {
                            $unreadNav = (new \FormFlow\SubmissionRepository($config))->unreadCount((int) $currentUser['id']);
                        } catch (\Throwable $e) {
                            $unreadNav = 0;
                        }
                    }
                    ?>
                    <?php if ($unreadNav > 0): ?>
                        <span class="ml-auto rounded-full bg-zinc-900 px-1.5 py-0.5 text-[10px] font-semibold text-white"><?= $unreadNav > 99 ? '99+' : $unreadNav ?></span>
                    <?php endif; ?>
                </a>
                <a href="/admin/analytics" class="nav-item <?= $path === '/admin/analytics' || str_contains($path, '/analytics') ? 'active' : '' ?>">
                    <i data-lucide="bar-chart-3" class="nav-icon"></i>
                    Analytics
                </a>
                <a href="/admin/templates" class="nav-item <?= str_starts_with($path, '/admin/templates') ? 'active' : '' ?>">
                    <i data-lucide="layout-template" class="nav-icon"></i>
                    Templates
                </a>

                <?php if (!empty($currentUser) && (string) ($currentUser['role'] ?? '') === 'admin'): ?>
                <div class="pt-4">
                    <p class="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-zinc-400">Settings</p>
                    <a href="/admin/settings" class="nav-item <?= str_starts_with($path, '/admin/settings') ? 'active' : '' ?>">
                        <i data-lucide="settings" class="nav-icon"></i>
                        Settings
                    </a>
                </div>
                <?php endif; ?>
            </nav>
            
            <?php if (!empty($currentUser)): ?>
            <div class="border-t border-border p-4">
                <div class="flex items-center gap-3 rounded-lg border border-border bg-zinc-50 p-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-200 text-sm font-medium text-zinc-700">
                        <?= e(strtoupper(substr((string) $currentUser['name'], 0, 1))) ?>
                    </div>
                    <div class="flex-1 overflow-hidden">
                        <p class="truncate text-sm font-medium text-zinc-900"><?= e((string) $currentUser['name']) ?></p>
                        <p class="truncate text-xs text-zinc-500"><?= e((string) ($currentUser['role'] ?? 'User')) ?></p>
                    </div>
                    <form method="post" action="/logout">
                        <?= csrf_field() ?>
                        <button type="submit" class="text-zinc-400 hover:text-zinc-700" title="Sign out">
                            <i data-lucide="log-out" class="h-4 w-4"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </aside>

        <!-- Main Content -->
        <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
            <!-- Header -->
            <header class="flex h-14 shrink-0 items-center gap-4 border-b border-border bg-white px-6">
                <button type="button" class="text-zinc-500 lg:hidden" @click="sidebarOpen = true">
                    <i data-lucide="menu" class="h-5 w-5"></i>
                </button>
                
                <h1 class="text-sm font-semibold text-zinc-900"><?= e($pageTitle ?? 'Admin') ?></h1>
                <div class="ml-auto flex items-center gap-2">
                    <button type="button" class="hidden rounded-md border border-zinc-200 px-2.5 py-1 text-xs text-zinc-500 sm:inline-flex" onclick="document.dispatchEvent(new KeyboardEvent('keydown',{key:'k',ctrlKey:true}))">
                        Ctrl+K
                    </button>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-4 md:p-8">
                <div class="mx-auto max-w-6xl">
                    <?php $success = flash('success'); $error = flash('error'); ?>
                    
                    <?php if ($success): ?>
                        <div class="mb-6 flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 shadow-sm">
                            <i data-lucide="check-circle-2" class="h-5 w-5 shrink-0 text-emerald-600"></i>
                            <div class="text-sm font-medium"><?= e($success) ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="mb-6 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800 shadow-sm">
                            <i data-lucide="alert-circle" class="h-5 w-5 shrink-0 text-red-600"></i>
                            <div class="text-sm font-medium"><?= e($error) ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Content injected here -->
                    <?= $content ?? '' ?>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Command palette -->
    <div x-data="{ open: false, q: '' }"
         @keydown.window.prevent.ctrl.k="open = true; $nextTick(() => $refs.cmd.focus())"
         @keydown.window.prevent.meta.k="open = true; $nextTick(() => $refs.cmd.focus())"
         @keydown.escape.window="open = false">
        <div x-show="open" x-cloak class="fixed inset-0 z-[80] bg-black/40" @click="open = false"></div>
        <div x-show="open" x-cloak class="fixed left-1/2 top-24 z-[90] w-[min(32rem,calc(100%-2rem))] -translate-x-1/2 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-2xl">
            <input x-ref="cmd" x-model="q" type="search" placeholder="Jump to…" class="h-12 w-full border-b border-zinc-100 px-4 text-sm outline-none">
            <div class="max-h-72 overflow-y-auto p-2 text-sm">
                <template x-for="item in [
                    { href: '/admin', label: 'Dashboard' },
                    { href: '/admin/forms', label: 'Forms' },
                    { href: '/admin/forms/new', label: 'New form' },
                    { href: '/admin/submissions', label: 'Submissions' },
                    { href: '/admin/analytics', label: 'Analytics' },
                    { href: '/admin/templates', label: 'Templates' },
                    { href: '/admin/settings', label: 'Settings' }
                ].filter(i => i.label.toLowerCase().includes(q.toLowerCase()))" :key="item.href">
                    <a :href="item.href" class="block rounded-md px-3 py-2 hover:bg-zinc-100" x-text="item.label" @click="open=false"></a>
                </template>
            </div>
        </div>
    </div>

    <!-- Initialize Lucide Icons -->
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
