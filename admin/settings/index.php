<?php
require_once '../includes/header.php';
?>

<div class="mb-8 flex justify-between items-center">
    <h1 class="text-3xl font-semibold mb-2">Settings</h1>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-[#b89c5e]/20 p-8 max-w-2xl">
    <h2 class="text-xl font-semibold mb-6 border-b border-[#b89c5e]/20 pb-4">Change Password</h2>
    
    <form id="changePasswordForm">
        <div class="space-y-6">
            <div>
                <label for="current_password" class="block text-sm font-medium mb-2 uppercase tracking-wide text-[#b89c5e]">Current Password *</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>
            
            <div>
                <label for="new_password" class="block text-sm font-medium mb-2 uppercase tracking-wide text-[#b89c5e]">New Password *</label>
                <input type="password" id="new_password" name="new_password" required minlength="6">
                <p class="text-xs text-gray-500 mt-1">Password must be at least 6 characters long.</p>
            </div>
            
            <div>
                <label for="confirm_password" class="block text-sm font-medium mb-2 uppercase tracking-wide text-[#b89c5e]">Confirm New Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
            </div>
            
            <button type="submit" class="btn-primary w-full md:w-auto mt-4 px-8">Update Password</button>
        </div>
    </form>
</div>

<script>
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const newPass = document.getElementById('new_password').value;
    const confirmPass = document.getElementById('confirm_password').value;
    
    if (newPass !== confirmPass) {
        Swal.fire('Error', 'New passwords do not match.', 'error');
        return;
    }
    
    const formData = new FormData(this);
    formData.append('action', 'change_password');
    
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
            Swal.fire('Success', data.message, 'success').then(() => {
                document.getElementById('changePasswordForm').reset();
            });
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

<?php require_once '../includes/footer.php'; ?>
