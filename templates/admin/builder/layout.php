<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Form Builder') ?> — <?= e($appName ?? 'FormFlow') ?></title>
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
                        builder: {
                            bg: '#0f1117',
                            panel: '#161922',
                            panel2: '#1c2030',
                            border: '#2a2f3d',
                            muted: '#8b93a7',
                            accent: '#7c5cff',
                            canvas: '#12151e',
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="/assets/css/form-builder.css">
    <script src="/assets/js/form-builder.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="h-full overflow-hidden bg-builder-bg font-sans text-zinc-100 antialiased">
    <?php $success = flash('success'); $error = flash('error'); ?>
    <?php if ($success): ?>
        <div class="fixed left-1/2 top-4 z-50 -translate-x-1/2 rounded-lg border border-emerald-500/30 bg-emerald-950 px-4 py-2 text-sm text-emerald-200 shadow-lg"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="fixed left-1/2 top-4 z-50 -translate-x-1/2 rounded-lg border border-red-500/30 bg-red-950 px-4 py-2 text-sm text-red-200 shadow-lg"><?= e($error) ?></div>
    <?php endif; ?>
    <?= $content ?? '' ?>
</body>
</html>
