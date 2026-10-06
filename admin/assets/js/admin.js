// Global Admin JS functions
const AdminApp = {
    handleAjaxDelete: function(url, data, onSuccess) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                for (const key in data) {
                    formData.append(key, data[key]);
                }

                fetch(url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(res => {
                    if (res.success) {
                        Swal.fire(
                            'Deleted!',
                            res.message,
                            'success'
                        ).then(() => {
                            if (onSuccess) onSuccess();
                        });
                    } else {
                        Swal.fire('Error', res.message || 'Error deleting item.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'An unexpected error occurred.', 'error');
                });
            }
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const closeSidebarBtn = document.getElementById('closeSidebarBtn');
    const adminSidebar = document.getElementById('adminSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
        if (adminSidebar && sidebarOverlay) {
            adminSidebar.classList.add('sidebar-open');
            sidebarOverlay.classList.remove('hidden');
            setTimeout(() => {
                sidebarOverlay.classList.add('overlay-open');
            }, 10);
            document.body.style.overflow = 'hidden'; 
        }
    }

    function closeSidebar() {
        if (adminSidebar && sidebarOverlay) {
            adminSidebar.classList.remove('sidebar-open');
            sidebarOverlay.classList.remove('overlay-open');
            setTimeout(() => {
                sidebarOverlay.classList.add('hidden');
                document.body.style.overflow = '';
            }, 300); 
        }
    }

    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', openSidebar);
    }
    if (closeSidebarBtn) {
        closeSidebarBtn.addEventListener('click', closeSidebar);
    }
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }
});
