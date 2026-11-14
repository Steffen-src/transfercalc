(() => {
  'use strict';
  document.querySelectorAll('[data-add]').forEach(button => button.addEventListener('click', () => {
    const type = button.dataset.add;
    const template = document.querySelector(`[data-template="${type}"]`);
    const fragment = template.content.cloneNode(true);
    const index = `new_${Date.now()}_${Math.random().toString(36).slice(2,8)}`;
    fragment.querySelectorAll('[name]').forEach(field => field.name = field.name.replace('__INDEX__', index));
    const target = document.querySelector(`[data-rows="${type}"]`);
    target.append(fragment);
    target.lastElementChild.querySelector('input:not([type="hidden"]),select')?.focus();
  }));
  document.addEventListener('click', event => {
    if (event.target.matches('.tpa-remove')) {
      event.target.closest('.tpa-row').remove();
    }
  });
})();
