document.addEventListener("input", (e) => {
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

document.addEventListener("change", (e) => {
  if (!e.target.matches('[data-license-options] input[name="license_type"]')) return;
  const price = document.querySelector("[data-license-price]");
  if (price) price.textContent = e.target.dataset.licensePrice || price.textContent;
});
