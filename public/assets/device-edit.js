(() => {
  const form = document.querySelector('form[method="post"]');
  const button = form.querySelector('button[type="submit"]');
  const buttonLabel = button.textContent;
  const state = document.createElement("p");
  state.className = "small text-muted mt-2";
  state.setAttribute("role", "status");
  form.append(state);
  const initial = JSON.stringify([...new FormData(form)]);
  const failedSave = !!document.querySelector('.alert-danger[role="alert"]');
  let submitting = false;
  const dirty = () =>
    failedSave || JSON.stringify([...new FormData(form)]) !== initial;
  function update() {
    state.textContent = dirty() ? "Unsaved changes" : "";
  }
  form.addEventListener("input", update);
  form.addEventListener("change", update);
  form.addEventListener("submit", (event) => {
    if (submitting) {
      event.preventDefault();
      return;
    }
    submitting = true;
    button.disabled = true;
    button.textContent = "Saving…";
    form.setAttribute("aria-busy", "true");
  });
  window.addEventListener("beforeunload", (event) => {
    if (!submitting && dirty()) {
      event.preventDefault();
      event.returnValue = "";
    }
  });
  window.addEventListener("pageshow", () => {
    submitting = false;
    button.disabled = false;
    button.textContent = buttonLabel;
    form.setAttribute("aria-busy", "false");
    update();
  });
  update();
})();
