
function updateCharacterCounter(field) {
  const max = Number(field.getAttribute("maxlength"));
  if (!max) return;
  let counter = field.parentElement?.querySelector(`[data-character-counter-for="${field.name}"]`);
  if (!counter) {
    counter = document.createElement("small");
    counter.className = "character-counter";
    counter.setAttribute("data-character-counter-for", field.name);
    field.insertAdjacentElement("afterend", counter);
  }
  const current = Array.from(field.value || "").length;
  const over = current > max;
  counter.textContent = `${current}/${max} characters${over ? ` (${current - max} over)` : ` (${max - current} left)`}`;
  counter.classList.toggle("over-limit", over);
  counter.setAttribute("aria-live", "polite");
}

document.querySelectorAll("[data-character-counter][maxlength]").forEach(updateCharacterCounter);
document.addEventListener("input", (e) => {
  if (e.target.matches("[data-character-counter][maxlength]")) updateCharacterCounter(e.target);
  if (e.target.matches("[data-slug-source]")) {
    const target = document.querySelector("[data-slug-target]");
    if (target && !target.dataset.touched)
      target.value = e.target.value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-|-$/g, "");
  }
  if (e.target.matches("[data-slug-target]")) e.target.dataset.touched = "1";
});

document.addEventListener("change", (e) => {
  if (!e.target.matches("[data-preview-images]")) return;
  const fields = document.querySelector("[data-preview-alt-fields]");
  if (!fields) return;
  fields.innerHTML = "";
  Array.from(e.target.files || []).forEach((file, index) => {
    const label = document.createElement("label");
    label.textContent = `Alt text for ${file.name}`;
    const input = document.createElement("input");
    input.name = "preview_alt[]";
    input.placeholder = "Describe this preview image";
    const sort = document.createElement("input");
    sort.type = "hidden";
    sort.name = "preview_sort[]";
    sort.value = String(index);
    label.appendChild(input);
    fields.appendChild(label);
    fields.appendChild(sort);
  });
});

document.addEventListener('click', async (event) => {
  const button = event.target.closest('[data-copy-link]');
  if (!button) return;
  const text = button.getAttribute('data-copy-link') || '';
  const originalHtml = button.dataset.copyOriginalHtml || button.innerHTML;
  button.dataset.copyOriginalHtml = originalHtml;
  try {
    await navigator.clipboard.writeText(text);
    button.classList.add('copied');
    button.innerHTML = '<span aria-hidden="true">✓</span><span class="sr-only">Copied</span>';
    setTimeout(() => {
      button.innerHTML = originalHtml;
      button.classList.remove('copied');
    }, 1800);
  } catch (error) {
    window.prompt('Copy this link:', text);
  }
});
