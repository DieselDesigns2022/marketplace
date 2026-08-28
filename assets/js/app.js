
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

document.addEventListener("click", (event) => {
  const button = event.target.closest("[data-password-toggle]");
  if (!button) return;
  const input = document.getElementById(button.dataset.passwordTarget || "");
  if (!input || !["password", "text"].includes(input.type)) return;
  const showing = input.type === "text";
  input.type = showing ? "password" : "text";
  button.textContent = showing ? "Show" : "Hide";
  button.setAttribute("aria-pressed", showing ? "false" : "true");
  button.setAttribute("aria-label", showing ? "Show password" : "Hide password");
});

function updateCategoryGuidance() {
  const category = document.querySelector("[data-category-guidance-source]");
  document.querySelectorAll("[data-category-guidance]").forEach((message) => {
    message.hidden = message.dataset.categoryGuidance !== category?.selectedOptions?.[0]?.dataset?.slug;
  });
}
document.addEventListener("change", (event) => { if (event.target.matches("[data-category-guidance-source]")) updateCategoryGuidance(); });
updateCategoryGuidance();

document.querySelectorAll("main table").forEach((table) => {
  if (table.closest(".responsive-table, .table-scroll, .money-scroll")) return;

  const wrapper = document.createElement("div");
  wrapper.className = "responsive-table";
  wrapper.tabIndex = 0;
  wrapper.setAttribute("role", "region");
  wrapper.setAttribute("aria-label", "Scrollable table. Swipe left or right to see all details.");

  table.parentNode.insertBefore(wrapper, table);
  wrapper.appendChild(table);
});

// CSV imports expose network and server work instead of leaving long requests looking frozen.
document.querySelectorAll('form[data-import-action]').forEach((form) => {
  form.addEventListener('submit', () => {
    const button = form.querySelector('button[type="submit"], button:not([type])');
    if (button) { button.disabled = true; button.textContent = form.dataset.loadingText || 'Working…'; }
  });
});

const csvUploadForm = document.querySelector('form[data-csv-upload]');
if (csvUploadForm) csvUploadForm.addEventListener('submit', (event) => {
  if (!window.FormData || !window.XMLHttpRequest) return;
  event.preventDefault();
  const progress = csvUploadForm.querySelector('[data-upload-progress]');
  const message = csvUploadForm.querySelector('[data-upload-message]');
  const button = csvUploadForm.querySelector('button');
  progress.hidden = false; button.disabled = true; button.textContent = 'Uploading…';
  const xhr = new XMLHttpRequest(); xhr.open('POST', csvUploadForm.action || window.location.href);
  xhr.upload.addEventListener('progress', (e) => { if (e.lengthComputable) { progress.max=e.total;progress.value=e.loaded;message.textContent=`Uploading… ${Math.round(e.loaded/e.total*100)}%`; } });
  xhr.upload.addEventListener('load', () => { progress.value=progress.max;message.textContent='Reading your CSV…';button.textContent='Reading your CSV…'; });
  xhr.addEventListener('load', () => { const finalUrl=new URL(xhr.responseURL || window.location.href,window.location.href);if(finalUrl.pathname!==window.location.pathname || finalUrl.search!==window.location.search){window.location.assign(finalUrl.href);return;}document.open();document.write(xhr.responseText);document.close(); });
  xhr.addEventListener('error', () => { message.textContent='Upload failed. Please try again.';button.disabled=false;button.textContent='Upload and preview'; });
  xhr.send(new FormData(csvUploadForm));
});

const importProgress = document.querySelector('[data-import-progress]');
if (importProgress) {
  const bar=importProgress.querySelector('progress'), count=importProgress.querySelector('[data-import-count]'), message=importProgress.querySelector('[data-import-message]');
  const run=async()=>{try{const body=new URLSearchParams({_csrf:importProgress.dataset.csrf});const response=await fetch(importProgress.dataset.processUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},body});if(!response.ok)throw new Error();const data=await response.json();bar.max=Math.max(1,data.selected);bar.value=data.processed;count.textContent=`${Number(data.processed).toLocaleString()} of ${Number(data.selected).toLocaleString()} products`;message.textContent=data.activity || 'Creating draft products…';if(data.done){message.textContent='Import finished. Opening your summary…';window.location.assign(data.redirect);return;}setTimeout(run,Math.max(100,Number(data.retry_ms)||1000));}catch(error){message.textContent='The import was interrupted. Retrying safely…';setTimeout(run,2000);}};run();
}
