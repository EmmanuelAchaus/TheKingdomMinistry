<?php 
include 'includes/header.php'; 

// Fetch categories that have published articles
$cat_stmt = $pdo->query("SELECT DISTINCT c.id, c.name FROM categories c JOIN articles a ON c.id = a.category_id WHERE a.status = 'Published' ORDER BY c.name ASC");
$active_categories = $cat_stmt->fetchAll();

// Get current category filter
$current_cat_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
// Get current search query
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination Logic
$limit = 9; // Number of articles per page
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Count total published articles for the current filter
$count_query = "SELECT COUNT(*) FROM articles WHERE status = 'Published'";
$count_params = [];
if ($current_cat_id > 0) {
    $count_query .= " AND category_id = ?";
    $count_params[] = $current_cat_id;
}
if (!empty($search_query)) {
    $count_query .= " AND (title LIKE ? OR excerpt LIKE ? OR body LIKE ?)";
    $count_params[] = "%$search_query%";
    $count_params[] = "%$search_query%";
    $count_params[] = "%$search_query%";
}
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_articles = $count_stmt->fetchColumn();
$total_pages = ceil($total_articles / $limit);
?>

<!-- BEGIN: SearchAndFilters -->
<section class="max-w-6xl mx-auto px-6 mt-12 mb-16" data-purpose="filtering-controls">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-8">
        <!-- Search Component -->
        <div class="w-full md:max-w-md">
            <label class="block text-sm font-medium mb-2 opacity-70" for="search-articles">Search the Word</label>
            <div class="relative">
                <input class="search-input w-full bg-transparent border-b border-sacred-navy/30 border-t-0 border-x-0 focus:ring-0 px-0 py-3 text-xl font-light placeholder:text-sacred-navy/20" id="search-articles" name="search" placeholder="Search topics, verses..." type="text" value="<?php echo htmlspecialchars($search_query); ?>"/>
                <svg class="absolute right-0 top-4 h-5 w-5 opacity-40" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                    <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path>
                </svg>
            </div>
        </div>
        
        <!-- Category Navigation Carousel -->
        <div class="relative w-full md:max-w-xl mt-4 md:mt-0 px-12 group" data-purpose="category-carousel-wrapper">
            <!-- Left Arrow -->
            <button id="scroll-left" class="absolute left-0 top-1/2 -translate-y-1/2 z-30 p-2 text-sacred-gold hover:scale-110 transition-transform" aria-label="Scroll Left" style="background: var(--sacred-parchment);">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M15 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"></path></svg>
            </button>
            
            <nav id="category-carousel" class="flex overflow-x-auto gap-6 md:gap-10 pb-2 scroll-smooth no-scrollbar relative z-10" data-purpose="category-filters">
                <a href="articles.php" class="<?php echo $current_cat_id === 0 ? 'text-sacred-navy font-medium border-b-2 border-sacred-gold' : 'text-sacred-navy/60 hover:text-sacred-navy'; ?> pb-2 px-1 transition-colors whitespace-nowrap">All Articles</a>
                
                <?php foreach ($active_categories as $cat): ?>
                    <a href="articles.php?category=<?php echo $cat['id']; ?>" 
                       class="<?php echo $current_cat_id === (int)$cat['id'] ? 'text-sacred-navy font-medium border-b-2 border-sacred-gold' : 'text-sacred-navy/60 hover:text-sacred-navy'; ?> pb-2 px-1 transition-colors whitespace-nowrap">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            
            <!-- Right Arrow -->
            <button id="scroll-right" class="absolute right-0 top-1/2 -translate-y-1/2 z-30 p-2 text-sacred-gold hover:scale-110 transition-transform" aria-label="Scroll Right" style="background: var(--sacred-parchment);">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"></path></svg>
            </button>
        </div>


    </div>
</section>
<!-- END: SearchAndFilters -->

<!-- BEGIN: ArticleGrid -->
<main id="article-listing-container" class="max-w-6xl mx-auto px-6 pb-24" data-purpose="article-listing">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-12 gap-y-16">
        <?php
        // Fetch published articles with optional category filter
        $query = "SELECT a.*, c.name as category_name FROM articles a LEFT JOIN categories c ON a.category_id = c.id WHERE a.status = 'Published'";
        $params = [];
        
        if ($current_cat_id > 0) {
            $query .= " AND a.category_id = ?";
            $params[] = $current_cat_id;
        }
        
        if (!empty($search_query)) {
            $query .= " AND (a.title LIKE ? OR a.excerpt LIKE ? OR a.body LIKE ?)";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
        }
        
        $query .= " ORDER BY a.created_at DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $all_articles = $stmt->fetchAll();
        
        if (empty($all_articles)):
        ?>
            <div class="col-span-full py-20 text-center opacity-50 italic">
                No articles found in this category yet.
            </div>
        <?php else: ?>
            <?php foreach ($all_articles as $article): ?>
            <article class="article-card group flex flex-col" data-purpose="article-item">
                <a href="article.php?slug=<?php echo urlencode($article['slug']); ?>" class="block">
                    <div class="relative overflow-hidden rounded-custom mb-6 aspect-[4/3] bg-sacred-navy/5">
                        <!-- Blurred Background Layer -->
                        <div class="absolute inset-0 bg-cover bg-center blur-2xl opacity-30 scale-110 md:grayscale transition-all duration-1000 group-hover:grayscale-0" style="background-image: url('<?php echo htmlspecialchars($article['image']); ?>');"></div>
                        <!-- Foreground Full Image -->
                        <img alt="<?php echo htmlspecialchars($article['title']); ?>" class="relative z-10 w-full h-full object-cover md:grayscale transition-all duration-1000 group-hover:grayscale-0 group-hover:scale-105" src="<?php echo htmlspecialchars($article['image']); ?>"/>
                    </div>
                    <div class="space-y-3">
                        <span class="text-xs uppercase tracking-widest text-sacred-gold font-bold"><?php echo htmlspecialchars($article['category_name'] ?: 'Uncategorized'); ?></span>
                        <h2 class="article-title text-2xl font-light leading-snug">
                            <?php echo htmlspecialchars($article['title']); ?>
                        </h2>
                        <p class="text-sacred-navy/70 line-clamp-3 font-light leading-relaxed">
                            <?php 
                            if (!empty($article['excerpt'])) {
                                echo htmlspecialchars($article['excerpt']);
                            } else {
                                // Fallback: Take the first 150 characters of the body, stripped of HTML tags
                                $excerpt = strip_tags($article['body']);
                                if (strlen($excerpt) > 150) {
                                    $excerpt = substr($excerpt, 0, 150) . '...';
                                }
                                echo htmlspecialchars($excerpt);
                            }
                            ?>
                        </p>
                        <div class="pt-4 flex items-center text-xs text-sacred-navy/50 tracking-wide">
                            <span>
                                <?php 
                                $date = new DateTime($article['created_at']);
                                echo $date->format('F d, Y'); 
                                ?>
                            </span>
                            <span class="mx-2">•</span>
                            <span>Read full article</span>
                        </div>
                    </div>
                </a>
            </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="mt-20 border-t border-sacred-navy/10 pt-10 flex items-center justify-between">
        <?php
        $pagination_base_url = "articles.php?";
        if ($current_cat_id > 0) $pagination_base_url .= "category=" . $current_cat_id . "&";
        if (!empty($search_query)) $pagination_base_url .= "search=" . urlencode($search_query) . "&";
        ?>
        <?php if ($page > 1): ?>
        <a href="<?php echo $pagination_base_url; ?>page=<?php echo $page - 1; ?>" class="text-sacred-navy/50 hover:text-sacred-navy transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M15 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path></svg>
            Newer Articles
        </a>
        <?php else: ?>
        <div class="text-sacred-navy/20 flex items-center gap-2 cursor-not-allowed">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M15 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path></svg>
            Newer Articles
        </div>
        <?php endif; ?>

        <div class="flex gap-4">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="<?php echo $pagination_base_url; ?>page=<?php echo $i; ?>" 
                   class="<?php echo $i === $page ? 'text-sacred-navy font-bold' : 'text-sacred-navy/40 hover:text-sacred-navy'; ?> transition-colors">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>

        <?php if ($page < $total_pages): ?>
        <a href="<?php echo $pagination_base_url; ?>page=<?php echo $page + 1; ?>" class="text-sacred-navy hover:text-sacred-gold transition-colors flex items-center gap-2">
            Older Articles
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path></svg>
        </a>
        <?php else: ?>
        <div class="text-sacred-navy/20 flex items-center gap-2 cursor-not-allowed">
            Older Articles
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path></svg>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>
<!-- END: ArticleGrid -->

<?php include 'includes/footer.php'; ?>

