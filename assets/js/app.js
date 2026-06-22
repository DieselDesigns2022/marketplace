document.addEventListener('input', e => {
  if (e.target.matches('[data-slug-source]')) {
    const target = document.querySelector('[data-slug-target]');
    if (target && !target.dataset.touched) target.value = e.target.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
  }
  if (e.target.matches('[data-slug-target]')) e.target.dataset.touched = '1';
});
