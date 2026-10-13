  </main>
</div><!-- /.main -->

<script>
// Global toast helper
function showToast(msg, type='success') {
  const t = document.createElement('div');
  t.className = 'toast';
  const icons = { success:'✅', error:'❌', info:'ℹ️', warning:'⚠️' };
  t.innerHTML = `<span>${icons[type]||'✅'}</span><span>${msg}</span>`;
  document.body.appendChild(t);
  setTimeout(() => { t.style.opacity='0'; t.style.transform='translateY(10px)'; t.style.transition='all .3s'; setTimeout(()=>t.remove(),300); }, 2800);
}
// Copy link utility
function copyLink(url) {
  navigator.clipboard.writeText(url)
    .then(() => showToast('Link berhasil disalin!'))
    .catch(() => prompt('Salin link berikut:', url));
}
// Modal close on overlay click
document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if(e.target===o) o.classList.remove('show'); });
});
</script>
</body>
</html>
