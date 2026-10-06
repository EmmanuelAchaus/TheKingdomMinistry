<?php include 'includes/header.php'; ?>

<!-- BEGIN: MainContent -->
<?php
// Fetch article by slug
$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("SELECT a.*, c.name as category_name FROM articles a LEFT JOIN categories c ON a.category_id = c.id WHERE a.slug = ? AND a.status = 'Published'");
$stmt->execute([$slug]);
$article = $stmt->fetch();

if (!$article) {
    // Basic 404 handling
    echo "<main class='article-container mx-auto px-6 py-16 text-center'><h1 class='text-4xl'>Article not found.</h1><a href='articles.php' class='text-gold-accent mt-4 inline-block'>Return to articles</a></main>";
    include 'includes/footer.php';
    exit;
}

// Fetch previous and next articles in the same category
$current_category_id = $article['category_id'];
$current_created_at = $article['created_at'];
$current_id = $article['id'];

// Previous article (cycling)
$stmt_prev = $pdo->prepare("SELECT title, slug FROM articles WHERE category_id = ? AND status = 'Published' AND (created_at < ? OR (created_at = ? AND id < ?)) ORDER BY created_at DESC, id DESC LIMIT 1");
$stmt_prev->execute([$current_category_id, $current_created_at, $current_created_at, $current_id]);
$prev_article = $stmt_prev->fetch();

if (!$prev_article) {
    // Cycle to the last one
    $stmt_last = $pdo->prepare("SELECT title, slug FROM articles WHERE category_id = ? AND status = 'Published' ORDER BY created_at DESC, id DESC LIMIT 1");
    $stmt_last->execute([$current_category_id]);
    $prev_article = $stmt_last->fetch();
}

// Next article (cycling)
$stmt_next = $pdo->prepare("SELECT title, slug FROM articles WHERE category_id = ? AND status = 'Published' AND (created_at > ? OR (created_at = ? AND id > ?)) ORDER BY created_at ASC, id ASC LIMIT 1");
$stmt_next->execute([$current_category_id, $current_created_at, $current_created_at, $current_id]);
$next_article = $stmt_next->fetch();

if (!$next_article) {
    // Cycle to the first one
    $stmt_first = $pdo->prepare("SELECT title, slug FROM articles WHERE category_id = ? AND status = 'Published' ORDER BY created_at ASC, id ASC LIMIT 1");
    $stmt_first->execute([$current_category_id]);
    $next_article = $stmt_first->fetch();
}

// Ensure we don't show the same article if it's the only one in the category
if ($prev_article && $prev_article['slug'] === $article['slug']) {
    $prev_article = null;
}
if ($next_article && $next_article['slug'] === $article['slug']) {
    $next_article = null;
}
?>
<main class="article-container mx-auto px-6 py-8 md:py-12">
    <!-- BEGIN: HeaderSection -->
    <header class="mb-16 text-center" data-purpose="article-header">
        <div class="text-[#b89c5e] text-xs uppercase tracking-[0.3em] font-medium mb-4"><?php echo htmlspecialchars($article['category_name'] ?: 'Uncategorized'); ?></div>
        <h1 class="text-4xl md:text-6xl mb-6 leading-tight"><?php echo htmlspecialchars($article['title']); ?></h1>
        <div class="flex items-center justify-center gap-4 text-sm italic opacity-60">
            <?php $date = new DateTime($article['created_at']); ?>
            <time datetime="<?php echo $date->format('Y-m-d'); ?>"><?php echo $date->format('F d, Y'); ?></time>
        </div>
    </header>
    <!-- END: HeaderSection -->

    <!-- BEGIN: FeaturedImage -->
    <div class="mb-16 rounded-2xl overflow-hidden border border-[#b89c5e]/10 relative bg-sacred-navy/5 aspect-[16/9]" data-purpose="featured-image">
        <!-- Blurred Background Layer -->
        <div class="absolute inset-0 bg-cover bg-center blur-2xl opacity-40 scale-110" style="background-image: url('<?php echo htmlspecialchars($article['image']); ?>');"></div>
        <!-- Foreground Full Image -->
        <img alt="<?php echo htmlspecialchars($article['title']); ?>" class="relative z-10 w-full h-full object-cover" src="<?php echo htmlspecialchars($article['image']); ?>"/>
    </div>
    <!-- END: FeaturedImage -->

    <!-- BEGIN: ArticleBody -->
    <article class="prose prose-lg max-w-none prose-sacred-navy" data-purpose="readable-text">
        <?php
        $body = $article['body'];
        // Inject dropcap class onto the very first <p> tag
        $body = preg_replace_callback('/<p(\b[^>]*)?>/', function($matches) {
            $attrs = $matches[1] ?? '';
            if (strpos($attrs, 'class=') !== false) {
                // Prepend dropcap to an existing class attribute
                $attrs = preg_replace('/class="([^"]*)"/', 'class="dropcap $1"', $attrs);
            } else {
                $attrs = ' class="dropcap"' . $attrs;
            }
            return '<p' . $attrs . '>';
        }, $body, 1);
        echo $body;
        ?>
        <!-- END: AuthorProfile -->
    </article>
    <!-- END: ArticleBody -->

    <!-- BEGIN: Pagination -->
    <?php if ($prev_article || $next_article): ?>
    <nav class="mt-20 flex flex-col md:flex-row justify-between items-center gap-12 border-t border-[#1b2b41]/5 pt-12" data-purpose="article-navigation">
        <?php if ($prev_article): ?>
        <a class="group flex flex-col items-start gap-2 max-w-[240px]" href="article.php?slug=<?php echo htmlspecialchars($prev_article['slug']); ?>">
            <span class="text-[10px] uppercase tracking-widest text-[#b89c5e] opacity-80">Previous Article</span>
            <span class="text-lg group-hover:translate-x-[-4px] transition-transform"><?php echo htmlspecialchars($prev_article['title']); ?></span>
        </a>
        <?php endif; ?>

        <?php if ($prev_article && $next_article): ?>
        <div class="hidden md:block w-[1px] h-12 bg-[#b89c5e]/20"></div>
        <?php endif; ?>

        <?php if ($next_article): ?>
        <a class="group flex flex-col items-end gap-2 text-right max-w-[240px]" href="article.php?slug=<?php echo htmlspecialchars($next_article['slug']); ?>">
            <span class="text-[10px] uppercase tracking-widest text-[#b89c5e] opacity-80">Next Article</span>
            <span class="text-lg group-hover:translate-x-[4px] transition-transform"><?php echo htmlspecialchars($next_article['title']); ?></span>
        </a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
    <!-- END: Pagination -->
</main>
<!-- END: MainContent -->

<?php include 'includes/footer.php'; ?>
