=== Simple POS (No WooCommerce) ===
Contributors: yourname
Tags: pos, point of sale, retail, inventory, cash register
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, standalone Point of Sale system for WordPress. No WooCommerce required.

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

1. Upload the `wp-pos-plugin` folder to `/wp-content/plugins/`, or upload
   the zip via Plugins → Add New → Upload Plugin.
2. Activate the plugin. This creates the required database tables and two
   custom roles.
3. Go to **POS → Settings** and set your currency, tax, and receipt
   details.
4. Go to **POS → Products** and add your first products (or categories).
5. Go to **POS → Terminal** to start selling.
6. Optionally, assign the **POS Cashier** or **POS Manager** role to staff
   accounts under Users, so they don't need full admin access.

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
the sale voided and restores stock for every tracked line item. Partial
refunds are not yet supported.

== Changelog ==

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
