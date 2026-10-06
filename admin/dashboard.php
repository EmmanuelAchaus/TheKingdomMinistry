<?php
require_once 'includes/header.php';
require_once __DIR__ . '/includes/db.php';

// Fetch stats
$totalArticles = $pdo->query("SELECT COUNT(*) FROM articles")->fetchColumn();
$totalCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();

// Fetch recent articles
$recentArticles = $pdo->query("SELECT a.id, a.title, a.created_at, c.name as category_name 
                               FROM articles a 
                               LEFT JOIN categories c ON a.category_id = c.id 
                               ORDER BY a.created_at DESC LIMIT 5")->fetchAll();

// Fetch highlighted scripture
$settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('highlighted_verse', 'highlighted_details')");
$settings = [];
while ($row = $settings_stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<div class="mb-8 flex justify-between items-center">
    <h1 class="text-3xl font-semibold mb-2">Dashboard</h1>
    <div class="text-sm opacity-70">Welcome back, Admin</div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#b89c5e]/20 flex items-center gap-6">
        <div class="w-14 h-14 rounded-full bg-[#1b2b41]/10 flex items-center justify-center text-[#1b2b41]">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9.5L18.5 8H20"></path></svg>
        </div>
        <div>
            <div class="text-3xl font-semibold"><?php echo $totalArticles; ?></div>
            <div class="text-sm opacity-60 uppercase tracking-wider">Total Articles</div>
        </div>
    </div>
    
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#b89c5e]/20 flex items-center gap-6">
        <div class="w-14 h-14 rounded-full bg-[#b89c5e]/10 flex items-center justify-center text-[#b89c5e]">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
        </div>
        <div>
            <div class="text-3xl font-semibold"><?php echo $totalCategories; ?></div>
            <div class="text-sm opacity-60 uppercase tracking-wider">Categories</div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">
    <!-- Highlighted Scripture Management -->
    <div class="bg-white rounded-2xl shadow-sm border border-[#b89c5e]/20 p-6 flex flex-col">
        <h2 class="text-xl font-semibold mb-6 border-b border-[#b89c5e]/20 pb-4">Highlighted Scripture</h2>
        <form id="scriptureForm" class="space-y-4 flex-1">
            <input type="hidden" name="action" value="save_highlighted_scripture">
            <div>
                <label class="block text-xs font-medium mb-2 uppercase tracking-widest text-[#b89c5e]">Bible Verse (Quote)</label>
                <textarea name="highlighted_verse" rows="3" class="w-full p-3 border border-gray-200 rounded-lg focus:outline-none focus:border-[#b89c5e] text-sm" required><?php echo htmlspecialchars($settings['highlighted_verse'] ?? ''); ?></textarea>
            </div>
            <div>
                <label class="block text-xs font-medium mb-2 uppercase tracking-widest text-[#b89c5e]">Verse Details (e.g., John 3:16)</label>
                <input type="text" name="highlighted_details" value="<?php echo htmlspecialchars($settings['highlighted_details'] ?? ''); ?>" class="w-full p-3 border border-gray-200 rounded-lg focus:outline-none focus:border-[#b89c5e] text-sm" required>
            </div>
            <button type="submit" class="w-full bg-[#1b2b41] text-white py-3 rounded-lg hover:bg-[#b89c5e] transition-colors text-sm uppercase tracking-widest font-medium">Update Scripture</button>
        </form>
    </div>

    <!-- Quick Actions or Stats Placeholder -->
    <div class="bg-white rounded-2xl shadow-sm border border-[#b89c5e]/20 p-6">
        <h2 class="text-xl font-semibold mb-6 border-b border-[#b89c5e]/20 pb-4">Quick Links</h2>
        <div class="grid grid-cols-2 gap-4">
            <a href="/TheKingdomMinistry 2/admin/articles/create.php" class="flex flex-col items-center justify-center p-4 rounded-xl border border-dashed border-[#b89c5e]/40 hover:bg-[#b89c5e]/5 transition-colors group">
                <svg class="w-8 h-8 text-[#b89c5e] mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span class="text-xs uppercase tracking-wider font-semibold group-hover:text-[#b89c5e]">New Article</span>
            </a>
            <a href="/TheKingdomMinistry 2/admin/categories/index.php" class="flex flex-col items-center justify-center p-4 rounded-xl border border-dashed border-[#b89c5e]/40 hover:bg-[#b89c5e]/5 transition-colors group">
                <svg class="w-8 h-8 text-[#b89c5e] mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                <span class="text-xs uppercase tracking-wider font-semibold group-hover:text-[#b89c5e]">Categories</span>
            </a>
        </div>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-[#b89c5e]/20 overflow-hidden">
    <div class="p-6 border-b border-[#b89c5e]/20 flex justify-between items-center">
        <h2 class="text-xl font-semibold">Recent Articles</h2>
        <a href="/TheKingdomMinistry 2/admin/articles/index.php" class="text-sm text-[#b89c5e] hover:underline">View All</a>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="py-4 px-6 font-medium text-sm text-gray-500 uppercase tracking-wider">Title</th>
                    <th class="py-4 px-6 font-medium text-sm text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="py-4 px-6 font-medium text-sm text-gray-500 uppercase tracking-wider">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (count($recentArticles) > 0): ?>
                    <?php foreach ($recentArticles as $article): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="py-4 px-6 text-[#1b2b41] font-medium"><?php echo htmlspecialchars($article['title']); ?></td>
                            <td class="py-4 px-6 text-gray-600">
                                <span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded-full border border-gray-200">
                                    <?php echo htmlspecialchars($article['category_name'] ?? 'Uncategorized'); ?>
                                </span>
                            </td>
                            <td class="py-4 px-6 text-gray-500 text-sm"><?php echo date('M d, Y', strtotime($article['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="py-8 text-center text-gray-500">No articles found. Create your first article to see it here!</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.getElementById('scriptureForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    Swal.fire({
        title: 'Updating...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    fetch('/TheKingdomMinistry 2/admin/ajax/settings.ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            Swal.fire('Success', data.message, 'success');
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    })
    .catch(error => {
        console.error(error);
        Swal.fire('Error', 'An unexpected error occurred.', 'error');
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
