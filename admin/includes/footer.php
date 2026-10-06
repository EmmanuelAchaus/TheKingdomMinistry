    </main>
    <script src="/TheKingdomMinistry 2/admin/assets/js/admin.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof tinymce !== 'undefined') {
                tinymce.init({
                    selector: '.rich-editor',
                    height: 500,
                    menubar: false,
                    plugins: 'lists link image code table wordcount',
                    toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | code',
                    content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 16px; }'
                });
            }
        });
    </script>
</body>
</html>
