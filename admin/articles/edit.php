<?php
require_once '../includes/header.php';
require_once dirname(__DIR__) . '/includes/db.php';

$id = $_GET['id'] ?? 0;
if (!$id) {
    echo "<script>window.location.href='/TheKingdomMinistry 2/admin/articles/index.php';</script>";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM articles WHERE id = :id");
$stmt->execute(['id' => $id]);
$article = $stmt->fetch();

if (!$article) {
    echo "<script>window.location.href='/TheKingdomMinistry 2/admin/articles/index.php';</script>";
    exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>

<div class="mb-8 flex justify-between items-center">
    <h1 class="text-3xl font-semibold mb-2">Edit Article</h1>
    <a href="/TheKingdomMinistry 2/admin/articles/index.php" class="text-sm font-medium text-gray-600 hover:text-[#1b2b41] flex items-center gap-1 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Back to Articles
    </a>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-[#b89c5e]/20 p-8 max-w-5xl mx-auto">
    <form id="editArticleForm" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo $article['id']; ?>">
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="md:col-span-2 space-y-6">
                <!-- Title -->
                <div>
                    <label for="title" class="block text-sm font-medium mb-2 uppercase tracking-wide text-[#b89c5e]">Article Title *</label>
                    <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($article['title']); ?>">
                </div>

                <!-- Excerpt -->
                <div>
                    <div class="flex justify-between items-end mb-2">
                        <label for="excerpt" class="block text-sm font-medium uppercase tracking-wide text-[#b89c5e]">Short Excerpt (Optional)</label>
                        <span id="excerpt-counter" class="text-[10px] text-gray-400"><?php echo mb_strlen($article['excerpt'] ?? ''); ?> / 200</span>
                    </div>
                    <textarea id="excerpt" name="excerpt" rows="3" maxlength="200" class="w-full p-3 border border-gray-200 rounded-lg focus:outline-none focus:border-[#b89c5e] text-sm" placeholder="A brief summary for listing pages..." oninput="document.getElementById('excerpt-counter').textContent = this.value.length + ' / 200'"><?php echo htmlspecialchars($article['excerpt'] ?? ''); ?></textarea>
                </div>
                
                <!-- Body Rich Text -->
                <div>
                    <div class="flex justify-between items-end mb-2">
                        <label for="body" class="block text-sm font-medium uppercase tracking-wide text-[#b89c5e]">Article Body *</label>
                        <button type="button" onclick="document.getElementById('importFile').click()" class="text-xs bg-[#b89c5e]/10 text-[#b89c5e] hover:bg-[#b89c5e] hover:text-white px-3 py-1.5 rounded transition-colors flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                            Import File (.docx, .pdf)
                        </button>
                    </div>
                    <input type="file" id="importFile" accept=".docx,.txt,.pdf" class="hidden" onchange="handleFileImport(this)">
                    <textarea id="body" name="body" class="rich-editor"><?php echo htmlspecialchars($article['body']); ?></textarea>
                </div>
            </div>
            
            <div class="space-y-6">
                <!-- Featured Toggle -->
                <div class="flex items-center gap-3 p-4 bg-[#b89c5e]/5 rounded-xl border border-[#b89c5e]/10 transition-colors hover:bg-[#b89c5e]/10">
                    <label class="custom-toggle">
                        <input type="checkbox" id="is_featured" name="is_featured" value="1" <?php echo $article['is_featured'] ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </label>
                    <label for="is_featured" class="text-sm font-medium text-gray-700 cursor-pointer select-none">Set as Featured Article</label>
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium mb-2 uppercase tracking-wide text-[#b89c5e]">Status</label>
                    <select id="status" name="status">
                        <option value="Published" <?php echo $article['status'] == 'Published' ? 'selected' : ''; ?>>Published</option>
                        <option value="Draft" <?php echo $article['status'] == 'Draft' ? 'selected' : ''; ?>>Draft</option>
                    </select>
                </div>

                <!-- Category -->
                <div>
                    <label for="category_id" class="block text-sm font-medium mb-2 uppercase tracking-wide text-[#b89c5e]">Category</label>
                    <select id="category_id" name="category_id">
                        <option value="">-- Select Category --</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $article['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Image Upload -->
                <div>
                    <label class="block text-sm font-medium mb-2 uppercase tracking-wide text-[#b89c5e]">Change Featured Image</label>
                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center cursor-pointer hover:bg-[#b89c5e]/5 hover:border-[#b89c5e]/50 transition-colors" onclick="document.getElementById('image').click()">
                        
                        <div id="imagePreview" class="<?php echo $article['image'] ? '' : 'hidden'; ?> mb-4">
                            <img src="<?php echo $article['image'] ? '/TheKingdomMinistry 2/'.htmlspecialchars($article['image']) : ''; ?>" class="w-full h-auto rounded object-cover aspect-video">
                        </div>
                        
                        <div id="uploadPrompt" class="<?php echo $article['image'] ? 'hidden' : ''; ?>">
                            <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            <span class="text-sm text-gray-500">Click to upload new image<br>(JPG, PNG, WEBP)</span>
                        </div>
                        <input type="file" id="image" name="image" class="hidden" accept="image/jpeg, image/png, image/webp" onchange="previewImage(this)">
                    </div>
                    <?php if($article['image']): ?>
                        <div class="mt-2 text-sm text-gray-500 text-center">Leave blank to keep current image.</div>
                        <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($article['image']); ?>">
                    <?php endif; ?>
                </div>
                
                <hr class="border-[#b89c5e]/20">
                
                <button type="submit" class="btn-primary w-full shadow-md text-center flex justify-center items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                    Update Article
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            const prompt = document.getElementById('uploadPrompt');
            const preview = document.getElementById('imagePreview');
            preview.querySelector('img').src = e.target.result;
            prompt.style.display = 'none';
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function handleFileImport(input) {
    if (!input.files || !input.files[0]) return;
    
    const file = input.files[0];
    const formData = new FormData();
    formData.append('import_file', file);
    
    Swal.fire({
        title: 'Extracting text...',
        text: 'Please wait',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    fetch('/TheKingdomMinistry 2/admin/ajax/import.ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Success', 'Text imported successfully!', 'success');
            if (typeof tinymce !== 'undefined' && tinymce.get('body')) {
                // Determine if we should append or replace. For a fresh import, probably replace, or let's just insert.
                tinymce.get('body').setContent(data.text);
            } else {
                document.getElementById('body').value = data.text;
            }
        } else {
            Swal.fire('Error', data.message || 'Error importing file', 'error');
        }
        input.value = ''; // reset file input
    })
    .catch(error => {
        console.error(error);
        Swal.fire('Error', 'An unexpected error occurred.', 'error');
        input.value = '';
    });
}

document.getElementById('editArticleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    if(typeof tinymce !== 'undefined') {
        tinymce.triggerSave();
    }
    
    // Check if body is empty
    const bodyContent = document.getElementById('body').value.trim();
    if(!bodyContent) {
        Swal.fire('Error', 'Article body cannot be empty', 'error');
        return;
    }

    const formData = new FormData(this);
    formData.append('action', 'update');

    Swal.fire({
        title: 'Updating...',
        text: 'Please wait',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    fetch('/TheKingdomMinistry 2/admin/ajax/articles.ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            Swal.fire('Success', 'Article has been updated!', 'success').then(() => {
                window.location.href = '/TheKingdomMinistry 2/admin/articles/index.php';
            });
        } else {
            Swal.fire('Error', data.message || 'Error updating article', 'error');
        }
    })
    .catch(error => {
        console.error(error);
        Swal.fire('Error', 'An unexpected error occurred.', 'error');
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
