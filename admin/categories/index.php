<?php
require_once '../includes/header.php';
require_once dirname(__DIR__) . '/includes/db.php';

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>

<div class="mb-8 flex justify-between items-center">
    <h1 class="text-3xl font-semibold mb-2">Categories</h1>
    <button onclick="addCategory()" class="btn-primary flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        Add Category
    </button>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-[#b89c5e]/20 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse" id="categoriesTable">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="py-4 px-6 font-medium text-sm text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="py-4 px-6 font-medium text-sm text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="py-4 px-6 font-medium text-sm text-gray-500 uppercase tracking-wider text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (count($categories) > 0): ?>
                    <?php foreach ($categories as $category): ?>
                        <tr class="hover:bg-gray-50 transition-colors" id="row-<?php echo $category['id']; ?>">
                            <td class="py-4 px-6 text-gray-500"><?php echo $category['id']; ?></td>
                            <td class="py-4 px-6 text-[#1b2b41] font-medium" id="name-<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </td>
                            <td class="py-4 px-6 text-right space-x-2">
                                <button onclick="editCategory(<?php echo $category['id']; ?>)" class="text-[#b89c5e] hover:text-[#8a7242] transition-colors p-1" title="Edit">
                                    <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </button>
                                <button onclick="deleteCategory(<?php echo $category['id']; ?>)" class="text-red-500 hover:text-red-700 transition-colors p-1" title="Delete">
                                    <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="emptyRow">
                        <td colspan="3" class="py-8 text-center text-gray-500">No categories found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function addCategory() {
    Swal.fire({
        title: 'Add New Category',
        input: 'text',
        inputAttributes: {
            autocapitalize: 'off',
            placeholder: 'e.g. Meditations'
        },
        showCancelButton: true,
        confirmButtonText: 'Create',
        confirmButtonColor: '#1b2b41',
        showLoaderOnConfirm: true,
        preConfirm: (name) => {
            if(!name) {
                Swal.showValidationMessage('Category name is required');
                return false;
            }
            const data = new FormData();
            data.append('action', 'create');
            data.append('name', name);
            
            return fetch('/TheKingdomMinistry 2/admin/ajax/categories.ajax.php', { method: 'POST', body: data })
                .then(response => response.json())
                .then(data => {
                    if(!data.success) throw new Error(data.message);
                    return data;
                })
                .catch(error => { Swal.showValidationMessage(`${error.message}`); });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire('Created!', 'Category has been created.', 'success').then(() => {
                location.reload();
            });
        }
    });
}

function editCategory(id) {
    const currentName = document.getElementById('name-' + id).innerText.trim();
    Swal.fire({
        title: 'Edit Category',
        input: 'text',
        inputValue: currentName,
        showCancelButton: true,
        confirmButtonText: 'Save',
        confirmButtonColor: '#1b2b41',
        showLoaderOnConfirm: true,
        preConfirm: (name) => {
            if(!name || name === currentName) return false;
            
            const data = new FormData();
            data.append('action', 'update');
            data.append('id', id);
            data.append('name', name);
            
            return fetch('/TheKingdomMinistry 2/admin/ajax/categories.ajax.php', { method: 'POST', body: data })
                .then(response => response.json())
                .then(data => {
                    if(!data.success) throw new Error(data.message);
                    return data;
                })
                .catch(error => { Swal.showValidationMessage(`${error.message}`); });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            Swal.fire('Saved!', 'Category has been updated.', 'success').then(() => {
                document.getElementById('name-' + id).innerText = result.value.name;
            });
        }
    });
}

function deleteCategory(id) {
    AdminApp.handleAjaxDelete('/TheKingdomMinistry 2/admin/ajax/categories.ajax.php', { action: 'delete', id: id }, function() {
        document.getElementById('row-' + id).remove();
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
