<?php include 'includes/header.php'; ?>

<?php
// Fetch highlighted scripture
$settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('highlighted_verse', 'highlighted_details')");
$site_settings = [];
while ($row = $settings_stmt->fetch()) {
    $site_settings[$row['setting_key']] = $row['setting_value'];
}
$h_verse = $site_settings['highlighted_verse'] ?? '"Be still, and know that the divine speaks in the silence of the soul."';
$h_details = $site_settings['highlighted_details'] ?? 'Ancient Wisdom for Modern Spirits';
?>

<!-- BEGIN: Hero Section -->
<header class="max-w-screen-xl mx-auto px-6 pt-8 pb-32 text-center fade-in" data-purpose="hero-section">
    <div class="mb-6 flex justify-center">
        <div class="w-px h-16 bg-gold-accent opacity-50"></div>
    </div>
    <blockquote class="text-4xl md:text-6xl font-light mb-12 spiritual-quote leading-tight max-w-4xl mx-auto">
        <?php echo htmlspecialchars($h_verse); ?>
    </blockquote>
    <p class="text-gold-accent tracking-[0.2em] uppercase text-sm mb-16"><?php echo htmlspecialchars($h_details); ?></p>
    <a class="inline-block border border-sacred-navy px-10 py-4 rounded-custom hover:bg-sacred-navy hover:text-white transition-all duration-500 text-sm tracking-widest uppercase"
        href="#latest">
        Begin Reading
    </a>
</header>
<!-- END: Hero Section -->

<!-- BEGIN: Featured Reflection -->
<?php
// Fetch featured article (explicitly marked)
$featured_stmt = $pdo->query("SELECT a.*, c.name as category_name FROM articles a LEFT JOIN categories c ON a.category_id = c.id WHERE a.is_featured = 1 AND a.status = 'Published' LIMIT 1");
$featured = $featured_stmt->fetch();

// Fallback: If no article is marked as featured, take the latest published one
if (!$featured) {
    $fallback_stmt = $pdo->query("SELECT a.*, c.name as category_name FROM articles a LEFT JOIN categories c ON a.category_id = c.id WHERE a.status = 'Published' ORDER BY a.created_at DESC LIMIT 1");
    $featured = $fallback_stmt->fetch();
}
?>

<?php if ($featured): ?>
    <section class="bg-sacred-navy text-parchment py-24 px-6" data-purpose="featured-reflection">
        <div class="mx-auto max-w-6xl grid md:grid-cols-2 gap-16 items-center">
            <div class="relative aspect-[4/5] bg-gray-200 overflow-hidden rounded-custom group"
                data-purpose="featured-image-container">
                <!-- Blurred Background Layer -->
                <div class="absolute inset-0 bg-cover bg-center blur-2xl opacity-40 scale-110"
                    style="background-image: url('<?php echo htmlspecialchars($featured['image']); ?>');"></div>
                <!-- Foreground Full Image -->
                <img alt="<?php echo htmlspecialchars($featured['title']); ?>"
                    class="relative z-10 w-full h-full object-cover"
                    src="<?php echo htmlspecialchars($featured['image']); ?>" />
            </div>
            <div class="space-y-8">
                <span class="text-gold-accent tracking-widest text-xs uppercase">Featured Article</span>
                <h2 class="text-4xl md:text-5xl leading-snug"><?php echo htmlspecialchars($featured['title']); ?></h2>
                <p class="text-lg opacity-80 leading-relaxed font-light">
                    <?php 
                    if (!empty($featured['excerpt'])) {
                        echo htmlspecialchars($featured['excerpt']);
                    } else {
                        $f_excerpt = strip_tags($featured['body']);
                        if (strlen($f_excerpt) > 200) {
                            $f_excerpt = substr($f_excerpt, 0, 200) . '...';
                        }
                        echo htmlspecialchars($f_excerpt);
                    }
                    ?>
                </p>
                <a class="inline-block text-gold-accent border-b border-gold-accent pb-1 tracking-widest uppercase text-xs hover:opacity-70 transition-opacity"
                    href="article.php?slug=<?php echo urlencode($featured['slug']); ?>">
                    Read The Full Article
                </a>
            </div>
        </div>
    </section>
<?php endif; ?>
<!-- END: Featured Reflection -->

<!-- BEGIN: Latest Articles -->
<main class="max-w-screen-xl mx-auto py-32 px-6" data-purpose="article-grid" id="latest">
    <div class="flex justify-between items-end mb-16 elegant-border pb-6">
        <h3 class="text-3xl font-light">Latest Articles</h3>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-12 lg:gap-20">
        <?php
        // Fetch 3 latest articles (excluding the one already featured above)
        $featured_id = $featured['id'] ?? 0;
        $stmt = $pdo->prepare("SELECT a.*, c.name as category_name FROM articles a LEFT JOIN categories c ON a.category_id = c.id WHERE a.status = 'Published' AND a.id != ? ORDER BY a.created_at DESC LIMIT 3");
        $stmt->execute([$featured_id]);
        $latest = $stmt->fetchAll();
        foreach ($latest as $article):
            ?>
            <article class="group cursor-pointer">
                <a href="article.php?slug=<?php echo urlencode($article['slug']); ?>">
                    <div class="relative aspect-square bg-gray-100 mb-8 overflow-hidden rounded-custom">
                        <!-- Blurred Background Layer -->
                        <div class="absolute inset-0 bg-cover bg-center blur-2xl opacity-30 scale-110 md:grayscale transition-all duration-700 group-hover:grayscale-0"
                            style="background-image: url('<?php echo htmlspecialchars($article['image']); ?>');"></div>
                        <!-- Foreground Full Image -->
                        <img alt="<?php echo htmlspecialchars($article['title']); ?>"
                            class="relative z-10 w-full h-full object-cover md:grayscale group-hover:grayscale-0 transition-all duration-700"
                            src="<?php echo htmlspecialchars($article['image']); ?>" />
                    </div>
                    <span
                        class="text-xs tracking-widest text-gold-accent uppercase mb-3 block"><?php echo htmlspecialchars($article['category_name'] ?: 'Uncategorized'); ?></span>
                    <h4 class="text-2xl mb-4 group-hover:text-gold-accent transition-colors">
                        <?php echo htmlspecialchars($article['title']); ?></h4>
                    <p class="text-sm opacity-70 leading-relaxed mb-6">
                        <?php 
                        if (!empty($article['excerpt'])) {
                            echo htmlspecialchars($article['excerpt']);
                        } else {
                            $l_excerpt = strip_tags($article['body']);
                            if (strlen($l_excerpt) > 120) {
                                $l_excerpt = substr($l_excerpt, 0, 120) . '...';
                            }
                            echo htmlspecialchars($l_excerpt);
                        }
                        ?>
                    </p>
                    <span class="text-[10px] tracking-widest uppercase opacity-40">
                        <?php
                        $date = new DateTime($article['created_at']);
                        echo $date->format('M d, Y');
                        ?>
                    </span>
                </a>
            </article>
        <?php endforeach; ?>
    </div>
</main>
<!-- END: Latest Articles -->


<?php include 'includes/footer.php'; ?>