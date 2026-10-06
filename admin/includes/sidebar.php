<aside id="adminSidebar" class="w-72 bg-white border-r border-[#b89c5e]/20 flex-col shrink-0 hidden md:flex mobile-sidebar">
    <div class="h-20 flex items-center justify-between px-6 border-b border-[#b89c5e]/20">
        <span class="text-xl font-semibold tracking-widest text-[#b89c5e] uppercase">Ministry Admin</span>
        <button id="closeSidebarBtn" class="md:hidden text-gray-400 hover:text-[#1b2b41] hover:bg-gray-100 p-2 rounded-full transition-colors focus:outline-none">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>
    <nav class="flex-1 py-8 px-4 space-y-2">
        <a href="/TheKingdomMinistry 2/admin/dashboard.php" class="block px-4 py-3 rounded hover:bg-[#b89c5e]/10 hover:text-[#b89c5e] transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-[#b89c5e]/10 text-[#b89c5e] font-medium' : ''; ?>">Dashboard</a>
        <a href="/TheKingdomMinistry 2/admin/articles/index.php" class="block px-4 py-3 rounded hover:bg-[#b89c5e]/10 hover:text-[#b89c5e] transition-colors <?php echo strpos($_SERVER['PHP_SELF'], '/articles/') !== false ? 'bg-[#b89c5e]/10 text-[#b89c5e] font-medium' : ''; ?>">Articles</a>
        <a href="/TheKingdomMinistry 2/admin/categories/index.php" class="block px-4 py-3 rounded hover:bg-[#b89c5e]/10 hover:text-[#b89c5e] transition-colors <?php echo strpos($_SERVER['PHP_SELF'], '/categories/') !== false ? 'bg-[#b89c5e]/10 text-[#b89c5e] font-medium' : ''; ?>">Categories</a>
        <a href="/TheKingdomMinistry 2/admin/settings/index.php" class="block px-4 py-3 rounded hover:bg-[#b89c5e]/10 hover:text-[#b89c5e] transition-colors <?php echo strpos($_SERVER['PHP_SELF'], '/settings/') !== false ? 'bg-[#b89c5e]/10 text-[#b89c5e] font-medium' : ''; ?>">Settings</a>
    </nav>
    <div class="p-4 border-t border-[#b89c5e]/20 space-y-2">
        <a href="/TheKingdomMinistry 2/" target="_blank" class="block w-full text-center px-4 py-2 text-[#1b2b41] border border-[#1b2b41]/20 hover:bg-[#1b2b41]/5 rounded transition-colors font-medium">Return to Website</a>
        <a href="/TheKingdomMinistry 2/admin/logout.php" class="block w-full text-center px-4 py-2 text-red-600 hover:bg-red-50 rounded transition-colors">Logout</a>
    </div>
</aside>
