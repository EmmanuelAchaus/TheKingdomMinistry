<?php require_once 'includes/db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>The Kingdom Ministry | A Faith-Based Journal</title>
    
    <!-- Tailwind CSS with Plugins -->
    <link href="assets/css/tailwind.css" rel="stylesheet"/>
    
    <!-- Google Fonts: Newsreader -->
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,200..800;1,6..72,200..800&amp;display=swap" rel="stylesheet"/>
    
    <!-- Custom Assets -->
    <link href="assets/css/style.css?v=2.1" rel="stylesheet"/>
    <script src="assets/js/script.js?v=2.1"></script>
</head>
<body class="antialiased">
    <!-- BEGIN: Navigation -->
    <nav id="main-nav" class="fixed left-0 right-0 z-[101] py-8 border-b border-[#b89c5e]/10 bg-parchment transition-all duration-300" data-purpose="main-navigation">
        <div class="max-w-6xl mx-auto px-6 md:px-12 flex justify-between items-center">
            <a href="index.php" class="text-2xl font-semibold tracking-tight italic hover:opacity-80 transition-opacity" data-purpose="site-logo">
                The Kingdom Ministry
            </a>
            <div class="hidden md:flex gap-10 text-xs uppercase tracking-[0.3em] opacity-70 font-medium">
                <a class="hover:text-[#b89c5e] transition-colors" href="articles.php">All Articles</a>
            </div>
            <div class="md:hidden">
                <button id="mobile-menu-button" class="p-2 hover:text-sacred-gold transition-colors" aria-label="Toggle Menu">
                    <svg fill="none" height="24" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewbox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg">
                        <line x1="4" x2="20" y1="12" y2="12"></line>
                        <line x1="4" x2="20" y1="6" y2="6"></line>
                        <line x1="4" x2="20" y1="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>
    </nav>
    <!-- BEGIN: Mobile Menu -->
    <div id="mobile-menu" class="hidden fixed left-0 right-0 z-[100] bg-parchment border-b border-[#b89c5e]/20 py-8 px-6 space-y-6 text-center shadow-md" style="top: 73px;">
        <a class="block text-xs uppercase tracking-[0.3em] opacity-70 hover:text-sacred-gold transition-colors font-medium" href="articles.php">All Articles</a>
    </div>
    <!-- END: Mobile Menu -->
    <!-- END: Navigation -->
    <div style="height: 160px; width: 100%;" aria-hidden="true"></div> <!-- Refined Spacer for v2.0 -->
