<?php
require_once dirname(__DIR__) . '/includes/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - The Kingdom Ministry</title>
    <link href="/TheKingdomMinistry 2/assets/css/tailwind.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js" referrerpolicy="origin"></script>
    <link href="/TheKingdomMinistry 2/assets/css/style.css" rel="stylesheet">
    <link href="/TheKingdomMinistry 2/admin/assets/css/admin.css" rel="stylesheet">
</head>
<body class="bg-[#f0ece1] text-[#1b2b41] flex flex-col md:flex-row min-h-screen">
    <!-- Mobile Header -->
    <header class="md:hidden bg-white border-b border-[#b89c5e]/20 p-4 flex justify-between items-center sticky top-0 z-40 shadow-sm">
        <span class="text-xl font-semibold tracking-widest text-[#b89c5e] uppercase">Ministry Admin</span>
        <button id="mobileMenuBtn" class="text-[#1b2b41] hover:text-[#b89c5e] focus:outline-none transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
        </button>
    </header>

    <!-- Mobile Overlay -->
    <div id="sidebarOverlay" class="hidden md:hidden mobile-overlay"></div>

    <?php include_once 'sidebar.php'; ?>
    <main class="flex-1 p-4 md:p-10 overflow-auto w-full md:w-auto mt-0">
