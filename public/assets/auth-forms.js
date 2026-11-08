(() => {
  for (const form of document.querySelectorAll('form[method="post"]')) {
    let submitting = false;
    form.addEventListener("submit", (event) => {
      if (submitting) {
        event.preventDefault();
        return;
      }
      submitting = true;
      form.setAttribute("aria-busy", "true");
      if (event.submitter?.name) {
        const action = document.createElement("input");
        action.type = "hidden";
        action.name = event.submitter.name;
        action.value = event.submitter.value;
        action.dataset.submittedAction = "true";
        form.append(action);
      }
      for (const button of form.querySelectorAll("button"))
        button.disabled = true;
    });
    window.addEventListener("pageshow", () => {
      submitting = false;
      form.setAttribute("aria-busy", "false");
      form
        .querySelectorAll("[data-submitted-action]")
        .forEach((input) => input.remove());
      for (const button of form.querySelectorAll("button"))
        button.disabled = false;
    });
  }
})();
