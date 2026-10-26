'use strict';
const form = document.querySelector('#product-form');
const fields = Object.fromEntries(['name', 'quantity', 'price'].map(key => [key, document.getElementById(key)]));
const save = document.getElementById('save');
let products = [], csrf = '', editVersion = null, busy = false;
const money = cents => (cents / 100).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
function message(text, error = false) {
    const box = document.getElementById('message');
    box.className = 'mt-3 mb-0 alert ' + (error ? 'alert-danger' : 'alert-success');
    box.textContent = text;
}
function resetForm() {
    form.reset();
    document.getElementById('product-id').value = '';
    editVersion = null;
    document.getElementById('form-title').textContent = 'Add a product';
    save.textContent = 'Add product';
    document.getElementById('cancel').hidden = true;
    updatePreview();
}
function render(data) {
    products = data.products;
    csrf = data.csrf;
    const body = document.getElementById('rows');
    body.replaceChildren();
    if (!products.length) {
        const row = body.insertRow(), cell = row.insertCell();
        cell.colSpan = 6; cell.className = 'py-4 text-secondary';
        cell.textContent = 'No products yet. Add your first product above.';
    }
    for (const product of products) {
        const row = body.insertRow();
        for (const [index, value] of [product.name, product.quantity, money(product.price_cents), product.submitted_at.replace('T',' ').replace(/\.\d+Z$/, ' UTC'), money(product.total_cents)].entries()) {
            const cell = row.insertCell(); cell.textContent = value;
            if ([1,2,4].includes(index)) cell.className = 'text-end';
        }
        const button = document.createElement('button');
        button.type = 'button'; button.className = 'btn btn-sm btn-outline-success';
        button.textContent = 'Edit'; button.dataset.id = product.id;
        button.setAttribute('aria-label', 'Edit ' + product.name);
        row.insertCell().append(button);
    }
    document.getElementById('grand-total').textContent = money(data.grand_total_cents);
}
async function request(options = {}) {
    const response = await fetch('api.php', {cache: 'no-store', credentials: 'same-origin', ...options});
    let data;
    try { data = await response.json(); } catch (_) { throw new Error('The server did not return JSON. Ensure PHP is enabled.'); }
    if (!response.ok) throw new Error(data.error || 'The request failed.');
    return data;
}
function setBusy(value) {
    busy = value; save.disabled = value || !csrf;
    document.getElementById('refresh').disabled = value;
    document.getElementById('cancel').disabled = value;
    Object.values(fields).forEach(field => field.disabled = value);
    document.querySelectorAll('[data-id]').forEach(button => button.disabled = value);
    form.setAttribute('aria-busy', String(value));
}
async function load() {
    setBusy(true);
    try { render(await request()); }
    catch (error) { message(error.message, true); }
    finally { setBusy(false); }
}
function updatePreview() {
    const quantity = fields.quantity.value.trim(), price = fields.price.value.trim();
    let cents = 0;
    if (/^\d{1,5}$/.test(quantity) && /^\d{1,5}(\.\d{1,2})?$/.test(price)) {
        const [whole, fraction = ''] = price.split('.');
        cents = Number(quantity) * (Number(whole) * 100 + Number(fraction.padEnd(2, '0')));
    }
    document.getElementById('preview').textContent = money(cents);
}
form.addEventListener('input', updatePreview);
form.addEventListener('submit', async event => {
    event.preventDefault();
    if (busy || !form.reportValidity()) return;
    const id = document.getElementById('product-id').value;
    const payload = {id, version: editVersion, ...Object.fromEntries(Object.entries(fields).map(([key, field]) => [key, field.value.trim()]))};
    setBusy(true);
    try {
        render(await request({method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf}, body: JSON.stringify(payload)}));
        resetForm(); message(id ? 'Product updated.' : 'Product added.'); fields.name.focus();
    } catch (error) { message(error.message, true); }
    finally { setBusy(false); }
});
document.getElementById('rows').addEventListener('click', event => {
    const button = event.target.closest('[data-id]');
    if (!button || busy) return;
    const product = products.find(item => item.id === button.dataset.id);
    document.getElementById('product-id').value = product.id;
    editVersion = product.version;
    fields.name.value = product.name; fields.quantity.value = product.quantity;
    fields.price.value = (product.price_cents / 100).toFixed(2);
    document.getElementById('form-title').textContent = 'Edit product';
    save.textContent = 'Save changes'; document.getElementById('cancel').hidden = false;
    updatePreview(); fields.name.focus(); form.scrollIntoView({behavior: 'smooth', block: 'center'});
});
document.getElementById('cancel').addEventListener('click', resetForm);
document.getElementById('refresh').addEventListener('click', async () => {resetForm(); await load();});
load();
