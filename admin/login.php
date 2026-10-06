<?php
session_start();
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: /TheKingdomMinistry 2/admin/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - The Kingdom Ministry</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="/TheKingdomMinistry 2/assets/css/style.css" rel="stylesheet">
    <link href="/TheKingdomMinistry 2/admin/assets/css/admin.css" rel="stylesheet">
</head>
<body class="bg-[#f0ece1] flex items-center justify-center min-h-screen text-[#1b2b41]">
    <div class="bg-white p-10 rounded-2xl shadow-lg border border-[#b89c5e]/20 w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-semibold mb-2">Admin Dashboard</h1>
            <p class="text-sm opacity-70">Login to manage your content</p>
        </div>
        
        <form id="loginForm">
            <div class="mb-6">
                <label for="username" class="block text-sm font-medium mb-2 uppercase tracking-wide text-[#b89c5e]">Username</label>
                <input type="text" id="username" name="username" required 
                       class="w-full px-4 py-3 rounded border border-gray-300 focus:outline-none focus:border-[#b89c5e] focus:ring-1 focus:ring-[#b89c5e]">
            </div>
            
            <div class="mb-8">
                <label for="password" class="block text-sm font-medium mb-2 uppercase tracking-wide text-[#b89c5e]">Password</label>
                <input type="password" id="password" name="password" required 
                       class="w-full px-4 py-3 rounded border border-gray-300 focus:outline-none focus:border-[#b89c5e] focus:ring-1 focus:ring-[#b89c5e]">
            </div>
            
            <button type="submit" 
                    class="w-full bg-[#1b2b41] text-white py-3 rounded font-medium hover:bg-[#b89c5e] transition-colors duration-300">
                Log In
            </button>
            <div id="login-error" class="text-red-600 mt-4 text-center text-sm hidden"></div>
        </form>
    </div>

    <!-- Login script -->
    <script src="/TheKingdomMinistry 2/admin/assets/js/admin.js"></script>
    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'login');
            
            fetch('/TheKingdomMinistry 2/admin/ajax/auth.ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    window.location.href = '/TheKingdomMinistry 2/admin/dashboard.php';
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Login Failed',
                        text: data.message,
                        confirmButtonColor: '#1b2b41'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    </script>
</body>
</html>
