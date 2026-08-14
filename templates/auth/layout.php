<!DOCTYPE html>
<html lang="en" class="h-full antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Sign In') ?> — <?= e($appName ?? 'FormFlow') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                    colors: {
                        border: 'hsl(240 5.9% 90%)',
                        input: 'hsl(240 5.9% 90%)',
                        ring: 'hsl(240 5.9% 10%)',
                        background: 'hsl(0 0% 100%)',
                        foreground: 'hsl(240 10% 3.9%)',
                        muted: { DEFAULT: 'hsl(240 4.8% 95.9%)', foreground: 'hsl(240 3.8% 46.1%)' },
                        card: { DEFAULT: 'hsl(0 0% 100%)', foreground: 'hsl(240 10% 3.9%)' },
                        destructive: { DEFAULT: 'hsl(0 84.2% 60.2%)', foreground: 'hsl(0 0% 98%)' },
                        primary: { DEFAULT: 'hsl(240 5.9% 10%)', foreground: 'hsl(0 0% 98%)' },
                    },
                    borderRadius: { lg: '0.5rem', md: 'calc(0.5rem - 2px)', sm: 'calc(0.5rem - 4px)' },
                }
            }
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-full bg-zinc-50 font-sans text-foreground">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-12 sm:px-6 lg:px-8">
        <div class="mb-8 text-center">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-zinc-900 text-lg font-bold text-white shadow-sm">
                FF
            </div>
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900"><?= e($appName ?? 'FormFlow') ?></h1>
            <p class="mt-1 text-sm text-muted-foreground"><?= e($pageTitle ?? '') ?></p>
        </div>

        <div class="w-full max-w-md">
            <div class="rounded-xl border border-border bg-card p-6 shadow-sm sm:p-8">
                <?= $content ?? '' ?>
            </div>

            <p class="mt-6 text-center text-xs text-muted-foreground">
                &copy; <?= date('Y') ?> <?= e($appName ?? 'FormFlow') ?>. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
