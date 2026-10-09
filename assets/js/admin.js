/* global quixdevsStockNotifier */
(() => {
 'use strict';
 const digits = /^[0-9]+$/;
 document.querySelectorAll('.qsn-integer').forEach((input) => {
  const error = document.getElementById(`${input.id}-error`);
  const message = error.dataset.message;
  const reject = () => {
   input.setCustomValidity(message);
   input.setAttribute('aria-invalid', 'true');
   error.textContent = message;
  };
  const validate = () => {
   const value = input.value;
   const normalized = value.replace(/^0+(?=\d)/, '');
   const max = input.max;
   const valid = digits.test(value) && !input.validity.badInput &&
    (normalized.length < max.length || (normalized.length === max.length && normalized <= max));
   if (!valid) { reject(); return false; }
   input.setCustomValidity('');
   input.removeAttribute('aria-invalid');
   error.textContent = '';
   return true;
  };
  input.addEventListener('keydown', (event) => {
   if (event.ctrlKey || event.metaKey || event.altKey || event.key.length !== 1) { return; }
   if (!/^[0-9]$/.test(event.key)) { event.preventDefault(); reject(); }
  });
  input.addEventListener('beforeinput', (event) => {
   if (event.data !== null && event.data !== undefined && !digits.test(event.data)) {
    event.preventDefault(); reject();
   }
  });
  input.addEventListener('paste', (event) => {
   if (!digits.test(event.clipboardData.getData('text'))) { event.preventDefault(); reject(); }
  });
  input.addEventListener('drop', (event) => {
   const text = event.dataTransfer.getData('text');
   if (!digits.test(text)) { event.preventDefault(); reject(); }
  });
  input.addEventListener('input', validate);
  input.addEventListener('change', validate);
  input.form.addEventListener('submit', (event) => {
   if (!validate()) { event.preventDefault(); input.reportValidity(); }
  });
 });
 const authors = document.getElementById('qsn-authors');
 const updateAuthors = () => {
  if (authors) { authors.hidden = document.querySelector('input[name=provider]:checked')?.value !== 'publishpress'; }
 };
 document.querySelectorAll('input[name=provider]').forEach((input) => input.addEventListener('change', updateAuthors));
 updateAuthors();
 const search = document.getElementById('qsn-product-search');
 if (search) {
  let timer;
  let controller;
  search.addEventListener('input', () => {
   clearTimeout(timer);
   if (controller) { controller.abort(); }
   timer = setTimeout(async () => {
    const status = document.getElementById('qsn-search-status');
    const results = document.getElementById('qsn-product-results');
    if (search.value.length < 2) { return; }
    controller = new AbortController();
    status.textContent = quixdevsStockNotifier.searching;
    const url = new URL(quixdevsStockNotifier.url);
    url.search = new URLSearchParams({action:'quixdevs_stocknotifier_products',nonce:quixdevsStockNotifier.nonce,term:search.value});
    try {
     const response = await fetch(url, {signal:controller.signal,credentials:'same-origin'});
     const data = await response.json();
     if (!response.ok || !data.success) { throw new Error('Search failed'); }
     results.replaceChildren(...data.data.map((product) => {
      const option = document.createElement('option'); option.value = product.id; option.textContent = product.name; return option;
     }));
     status.textContent = data.data.length ? '' : quixdevsStockNotifier.empty;
    } catch (error) {
     if (error.name !== 'AbortError') { status.textContent = quixdevsStockNotifier.error; }
    }
   }, 250);
  });
 }
})();
