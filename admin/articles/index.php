<?php
require_once '../includes/header.php';
require_once dirname(__DIR__) . '/includes/db.php';

$articles = $pdo->query("SELECT a.*, c.name as category_name FROM articles a LEFT JOIN categories c ON a.category_id = c.id ORDER BY a.created_at DESC")->fetchAll();
?>

<div class="mb-8 flex justify-between items-center">
    <h1 class="text-3xl font-semibold mb-2">Articles</h1>
    <a href="/TheKingdomMinistry 2/admin/articles/create.php" class="btn-primary flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        Create Article
    </a>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-[#b89c5e]/20 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="py-4 px-6 font-medium text-sm text-gray-500 uppercase tracking-wider">Image</th>
                    <th class="py-4 px-6 font-medium text-sm text-gray-500 uppercase tracking-wider">Title</th>
                    <th class="py-4 px-6 font-medium text-sm text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="py-4 px-6 font-medium text-sm text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="py-4 px-6 font-medium text-sm text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="py-4 px-6 font-medium text-sm text-gray-500 uppercase tracking-wider text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (count($articles) > 0): ?>
                    <?php foreach ($articles as $article): ?>
                        <tr class="hover:bg-gray-50 transition-colors" id="row-<?php echo $article['id']; ?>">
                            <td class="py-4 px-6">
                                <?php if($article['image']): ?>
                                    <img src="/TheKingdomMinistry 2/<?php echo htmlspecialchars($article['image']); ?>" alt="Img" class="w-16 h-12 object-cover rounded">
                                <?php else: ?>
                                    <div class="w-16 h-12 bg-gray-200 rounded flex items-center justify-center text-xs text-gray-500">None</div>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-6 text-[#1b2b41] font-medium flex items-center gap-2">
                                <?php echo htmlspecialchars($article['title']); ?>
                                <?php if ($article['is_featured']): ?>
                                    <svg class="w-5 h-5 text-yellow-400 fill-current" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-6 text-gray-600">
                                <span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded-full border border-gray-200">
                                    <?php echo htmlspecialchars($article['category_name'] ?? 'Uncategorized'); ?>
                                </span>
                            </td>
                            <td class="py-4 px-6">
                                <?php if($article['status'] == 'Published'): ?>
                                    <span class="text-green-600 bg-green-50 px-2 py-1 rounded-full text-xs border border-green-200">Published</span>
                                <?php else: ?>
                                    <span class="text-orange-600 bg-orange-50 px-2 py-1 rounded-full text-xs border border-orange-200">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-6 text-gray-500 text-sm"><?php echo date('M d, Y', strtotime($article['created_at'])); ?></td>
                            <td class="py-4 px-6 text-right space-x-2">
                                <a href="/TheKingdomMinistry 2/admin/articles/edit.php?id=<?php echo $article['id']; ?>" class="text-[#b89c5e] hover:text-[#8a7242] transition-colors p-1" title="Edit">
                                    <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </a>
                                <button onclick="deleteArticle(<?php echo $article['id']; ?>)" class="text-red-500 hover:text-red-700 transition-colors p-1" title="Delete">
                                    <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="py-8 text-center text-gray-500">No articles found. Create your first article!</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function deleteArticle(id) {
    AdminApp.handleAjaxDelete('/TheKingdomMinistry 2/admin/ajax/articles.ajax.php', { action: 'delete', id: id }, function() {
        document.getElementById('row-' + id).remove();
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
