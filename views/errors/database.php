<div class="mx-auto max-w-lg text-center">
    <p class="text-6xl font-bold text-red-200">503</p>
    <h1 class="mt-4 text-2xl font-semibold text-gray-900">Database connection failed</h1>

    <?php if (FORMFLOW_DEBUG && isset($exception)): ?>
        <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-left">
            <p class="text-sm font-medium text-red-800">Debug details:</p>
            <pre class="mt-2 overflow-x-auto text-xs text-red-700"><?= htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') ?></pre>
        </div>
    <?php else: ?>
        <p class="mt-4 text-gray-600">
            We are unable to connect to the database. Please try again later or contact the site administrator.
        </p>
    <?php endif; ?>
</div>
