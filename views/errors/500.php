<div class="text-center">
    <p class="text-6xl font-bold text-gray-300">500</p>
    <h1 class="mt-4 text-2xl font-semibold text-gray-900">Internal Server Error</h1>
    <p class="mt-2 text-gray-600">Something went wrong. Please try again later.</p>
    <?php if (FORMFLOW_DEBUG && !empty($errorMessage)): ?>
        <pre class="mt-4 mx-auto max-w-lg overflow-auto text-left text-sm text-red-600 bg-red-50 p-4 rounded"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></pre>
    <?php endif; ?>
    <a href="/" class="mt-6 inline-block text-sm font-medium text-gray-900 hover:underline">Back to home</a>
</div>
