# Stockroom — product inventory

A small PHP application for recording stock counts and item prices. The form submits with Ajax, the PHP endpoint validates and stores each record in `storage/products.json`, and the page calculates row and grand totals using integer cents. Saved rows stay in submission order; edits preserve their original submission time.

## Requirements

- PHP 8.1 or newer, with JSON and sessions enabled.
- A web server configured to serve this directory and execute PHP.
- Write permission for the PHP process on `storage/`.
- Bootstrap 5.3 CSS is included locally. The app has no Composer or JavaScript package dependencies.

## Run locally

From this directory, run `php -S 127.0.0.1:8000` and open `http://127.0.0.1:8000`. PHP's development server is for local testing; use Apache or Nginx with PHP-FPM for deployment. On a hosting server, extract the folder, point the site document root at this folder, and grant the PHP process write permission on `storage/`. Apache access to the JSON file is denied by the included `.htaccess`; for Nginx, deny HTTP access to `/storage/` in the site configuration.

The included `storage/products.json` starts as an empty valid JSON array. Keep `storage/` writable; do not make its files publicly downloadable.

## Fields and behavior

- Product name: required, up to 200 bytes.
- Quantity: whole number from 0 to 99999.
- Price: decimal with up to two places from 0.00 to 99999.99.
- Total value: quantity multiplied by price, stored/calculated as integer cents.
- Editing preserves the original submitted date/time and uses a version check to reject stale edits.
- Submission times are recorded in UTC, displayed oldest first. A refresh uses the saved server data.

## Included files

- `index.php`, `api.php`, `store.php`: page, Ajax endpoint and file-backed data operations.
- `assets/`: Bootstrap CSS, page styles, and browser logic.
- `storage/products.json`: initial data file; `storage/.htaccess` and `storage/index.php` help block direct access.

## Verification

The assessment package was checked on PHP 8.3 WebAssembly. Its 20 request-level checks cover persistence, exact cent arithmetic, row order, edits, stale-edit conflicts, invalid values, CSRF, unsupported methods and page rendering. JavaScript syntax passed Node.js parsing. A local PHP 8.3 or newer server is still recommended for final deployment verification.

Bootstrap is distributed under the MIT License; see `assets/BOOTSTRAP-LICENSE.txt`.
