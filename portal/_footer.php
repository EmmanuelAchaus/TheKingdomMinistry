    </main>
</div>
<script>
const menuButton = document.querySelector('.portal-menu-btn');
const sidebar = document.getElementById('portal-sidebar');
menuButton?.addEventListener('click', () => {
  const open = sidebar.classList.toggle('open');
  menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
});
</script>
</body>
</html>
