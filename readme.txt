=== Simple POS — Standalone Point of Sale ===
Contributors: badoleakash
Tags: pos, point of sale, retail, inventory, cash register
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, standalone Point of Sale for WordPress. Custom tables, REST API, tax, variants, suppliers and barcode labels.

== Description ==

Simple POS turns WordPress into a retail checkout system without requiring
WooCommerce or any other e-commerce plugin. It ships with its own database
tables, its own REST API, and a dedicated admin area — so it stays fast even
on a WordPress install that already has a lot going on.

**Core features**

* **POS Terminal** — searchable/filterable product grid, barcode-or-SKU scan
  input (Enter to add), running cart, per-country tax classes (US/IN/EU presets: US-CA 7.25%, IN GST 18%/5%, DE 19%, FR 20%…), variant picker for size/color etc., discounts, payment methods, change calc, and dual printing (browser + USB ESC/POS with drawer kick).
* **Products & Variants** — SKU, barcode, price, cost price, tax class (free-form variant attributes), stock per variant + per parent, image per variant, low-stock threshold, optional tracking.
* **Barcode Labels** — A4 30/65-up and roll 58/80mm sheets via JsBarcode (CODE128/EAN13/QR), bulk print from Products → Barcode Labels.
* **Inventory + POs** — every stock change (sale, void, restock, PO receive, manual edit, variant) is logged; Purchase Orders & Suppliers (draft → ordered → partial → received, stock auto-increment on receive); low-stock Dashboard notice covers variants.
* **Customers** — optional walk-in or saved customers, with purchase history.
* **Sales History** — filterable by date/status, tax country/state + breakdown on detail, variant line items, void (restores variant stock), CSV export.
* **CSV Import/Export** — products (upsert by SKU) and sales export.
* **Reports** — revenue, sale count, avg sale, gross profit, items sold, revenue-by-day chart, top products, sales-by-cashier — date-range filtered.
* **Roles** — POS Cashier, POS Manager; Administrators get full POS caps automatically.
* **Settings** — store-wide base currency (USD/EUR/INR/GBP … 20 codes), tax defaults (country/state, inclusive, discount-before-tax, rounding), paper width, printer type (browser/USB/network), PO prefix, barcode symbology/label format, receipt header/footer, oversell toggle, delete-on-uninstall.

**Built for performance**

* Uses its own indexed custom database tables (`wp_pos_products`,
  `wp_pos_sales`, `wp_pos_sale_items`, `wp_pos_customers`,
  `wp_pos_categories`, `wp_pos_stock_log`) instead of post types/postmeta,
  which do not scale well for high-volume transactional data.
* All queries use `$wpdb->prepare()`.
* Checkout runs inside a database transaction — a sale and its stock
  deductions are written atomically, or not at all.
* Report queries are cached briefly with transients and busted
  automatically whenever a sale is completed or voided.
* CSS/JS are only enqueued on the plugin's own admin screens.
* No external JS dependencies and no build step — everything is plain
  vanilla JS/CSS/PHP in one drag-and-drop plugin folder.

= Not included in v2.0 =

Multi-location/multi-till sync, offline queue, split/partial payments and gift cards are still roadmap. USB ESC/POS via WebUSB requires HTTPS + user gesture; network ESC/POS needs printer IP (9100).

== Installation ==

1. Upload the `simple-pos` folder to `/wp-content/plugins/`, or upload
   the zip via Plugins → Add New → Upload Plugin (ensure the zip contains a single top-level folder `simple-pos` so the Text Domain `simple-pos` matches the slug).
2. Activate the plugin through the **Plugins** screen. This creates the required custom tables (`wp_pos_*`) and the `POS Cashier` / `POS Manager` roles and seeds default tax classes/rates.
3. Go to **POS → Settings** and set your store name, base currency, default tax country/state and receipt details.
4. Go to **POS → Taxes** to review or edit per-country rates (US CA 7.25%, NY 8%, IN GST 18%/5%, DE 19%, FR 20%, GB 20% etc.).
5. Go to **POS → Products** and add your first products (or categories). Add variants (size/color etc.) per product if needed.
6. Go to **POS → Terminal** to start selling (barcode scan or click, variant picker appears automatically). Use **POS → Barcode Labels** to print A4/roll sheets (vendored JsBarcode, no CDN).
7. Optionally, assign the **POS Cashier** or **POS Manager** role to staff accounts under **Users**, so they don't need full admin access.

== Screenshots ==

1. POS Terminal — product grid, barcode scan, variant picker and live tax breakdown.
2. Products — tax class per product, free-form variant attributes, stock per variant, CSV import/export.
3. Taxes — per-country/state classes and rates (US CA/NY, IN GST, EU VAT) with inclusive/compound support.
4. Purchase Orders — suppliers, draft/ordered/partial/received workflow with stock-in on receive.
5. Barcode Labels — A4 30/65-up and roll 58/80mm sheets via vendored JsBarcode.
6. Reports & Dashboard — revenue, gross profit, low-stock (including variants).

== Frequently Asked Questions ==

= Does this require WooCommerce? =

No. It is fully standalone and does not read from or write to WooCommerce
data.

= Can I track stock for services or non-physical items? =

Yes — uncheck "Track stock for this product" when creating it, and it will
never affect or be affected by stock levels.

= What happens to sales data if I deactivate the plugin? =

Nothing. Deactivating never touches your data. Data is only removed if you
delete the plugin from the Plugins screen AND have explicitly checked
"Delete all data on uninstall" in Settings (off by default).

= Can I refund a sale? =

Yes — open the sale from Sales History and click "Void Sale". This marks
the sale voided and restores stock for every tracked line item (including variant stock). Partial
refunds are not yet supported.

= Does the barcode/label printing use an external CDN? =

No. JsBarcode 3.11.6 (MIT) is vendored at `admin/js/vendor/jsbarcode.min.js` and enqueued locally per WordPress.org guidelines. The label print window loads the same local copy — no external requests.

= What currency is used? =

One store-wide base currency (USD/EUR/INR/GBP and 16 more presets) set in POS → Settings. All amounts are stored in that currency.

= Why does the terminal show a tax breakdown? =

Sales are calculated server-side via `Simple_POS_Tax::calculate_order()` using the active tax classes/rates for the selected country/state, so the receipt breakdown matches the stored sale exactly. A `POST pos/v1/tax/calculate` preview is used for the live total.

== Upgrade Notice ==

= 1.0.0 =

Initial public release. All core features: POS terminal, products, variants, tax engine, suppliers, purchase orders, barcode labels, CSV import/export, reports, roles, and settings.

= 2.1.0 =

Maintenance release. Fixes an empty error screen when a user without permission opens the Kitchen Display / bumps an order, updates author metadata for the marketplace release, and bumps "Tested up to" to WordPress 7.1. No settings changes; just update.

= 2.0.9 =

Fixes terminal CSS on POS screens, checkout "Could not record sale" on missing sequence table (auto-upgrades schema to DB 2.1.1, no data loss), and frontend shortcode assets. Just update.

= 2.0.0 =

Adds tax classes/rates (US/IN/EU presets), product variants, suppliers/purchase orders, barcode labels, store-wide currency, and USB ESC/POS. Migrates legacy tax_rate to tax_class_id. No data loss. Flush rewrite rules and review tax settings.

== Changelog ==

= 2.1.0 =
* Fix: Kitchen Display + bump actions now show the permission error correctly — `wp_die( esc_html_e() )` echoed the message and passed empty to `wp_die`, rendering a blank error screen (switched to `esc_html__`).
* Polish: release metadata updated (Author, Author URI, Plugin URI, Contributors) for WordPress.org and ThemeForest submission.
* Polish: readme aligned — "Tested up to" WordPress 7.1, placeholder links and the legacy `simple-pos` packaging note removed.

= 2.0.9 =
* Fix: admin CSS/JS now load on all POS screens (wrong screen check meant pos-admin.css never enqueued); terminal script no longer loads on non-terminal screens.
* Fix: checkout "Could not record sale." — schema auto-upgrade to DB 2.1.1 creates a missing sale-number sequence table and any missing sale columns; sale-number generation self-heals with a fallback.
* Fix: [simple_pos_terminal] shortcode now returns markup in place and enqueues its assets reliably.
* Fix: legacy products.tax_rate column is preserved (dropping it broke product lookups/saves on upgraded installs).
* Checkout and line-item DB failures now log the real SQL error (WP_DEBUG) instead of a generic message.

= 2.0.0 =
* Tax engine: classes/rates per country+state, presets for US/IN/EU, inclusive/compound/priority, breakdown on sales, terminal preview via /tax/calculate, DB migration.
* Product variants: free-form attributes (size/color etc.), separate SKU/barcode/price/cost/stock/image/track per variant, low-stock includes variants, lookup supports variant SKU/barcode, sales handle variant_id.
* Store-wide currency: base code + 20 currency presets, symbol/position/decimals.
* Barcode labels: JsBarcode CODE128/EAN13/QR, A4 30/65 + roll 58/80 sheet printing.
* Suppliers + Purchase Orders: draft/ordered/partial/received, receive increments variant/product stock + audit log.
* CSV: products import/export, sales export.
* Terminal: variant picker modal, tax country/state inputs, tax breakdown, USB ESC/POS + drawer kick + browser fallback, paper width.
* Admin: new pages Taxes, Suppliers, Purchase Orders, Barcode Labels; Products shows variants + import/export + tax class.
* Sale totals: server-authoritative via Simple_POS_Tax::calculate_order, discount-before-tax toggle.

= 1.0.0 =
* Initial release: terminal, products, categories, customers, sales
  history, reports, roles, settings.
