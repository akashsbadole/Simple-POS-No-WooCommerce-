# Simple POS — User Guide

**Plugin:** Simple POS (No WooCommerce)
**Version:** 2.1.0
**License:** GPL v2 or later

Simple POS is a lightweight, standalone Point of Sale system for WordPress. It does **not** require WooCommerce or any other e-commerce plugin. It ships with its own database tables, REST API, and a dedicated admin area.

---

## 1. Requirements

- WordPress 5.8 or later (tested up to 7.1)
- PHP 7.4 or later (tested up to 8.x)
- MySQL 5.7+ / MariaDB 10.2+ (InnoDB recommended)

---

## 2. Installation

1. Upload the `wp-pos-plugin` folder to `/wp-content/plugins/`, or upload the zip via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin from the **Plugins** screen. Activation creates the required custom tables (`wp_pos_*`), the `POS Cashier` / `POS Manager` roles, and seeds default per-country tax rates.
3. Go to **POS → Settings** and set your store name, base currency, default tax country/state, and receipt details.
4. On a fresh install the **Business Setup** notice appears — choose your business type to seed sample categories, taxes and products (optional).

---

## 3. Quick start (your first sale)

1. Add products under **POS → Products** (name, price, tax class, optional SKU/barcode).
2. Open **POS → Terminal**.
3. Click a product (or scan a barcode/SKU and press **Enter**).
4. Choose a tax country/state if not already set; the tax breakdown updates live.
5. Apply any discount, choose a payment method, and click **Charge**.
6. The sale is recorded atomically (sale + stock deduction in a DB transaction). Print the receipt via browser or USB ESC/POS printer.

---

## 4. Screens and features

### POS Terminal
- Searchable/filterable product grid.
- Barcode/SKU scan input — type or scan, press **Enter** to add.
- Variant picker for products with size/colour/etc. attributes.
- Live tax preview (server-authoritative calculation via `Simple_POS_Tax`).
- Discounts, multiple payment methods, change calculation.
- Dual printing: browser receipt + USB ESC/POS (WebUSB) or network ESC/POS (port 9100). See **Settings → Printer type**.

### Products & Variants
- SKU, barcode, selling price, cost price, tax class, image, optional stock tracking.
- Free-form variant attributes (size, colour, pack …) with per-variant SKU, barcode, price, cost, stock and image.
- Categories; CSV import/export (upsert by SKU).

### Barcode Labels
- JsBarcode (CODE128/EAN13/QR) sheets: A4 30-up/65-up and roll 58 mm/80 mm.
- Vendored locally — no CDN, works offline.

### Taxes (per-country)
- Tax classes & rates per country+state.
- Presets: US-CA 7.25%, US-NY 8%, IN GST 18%/5%, DE 19%, FR 20%, GB 20% and more; fully editable.
- Support for inclusive tax, compound tax (e.g. Canadian PST on GST), and priority ordering.

### Inventory, Suppliers & Purchase Orders
- Every stock change is logged (sale, void, restock, PO receive, manual edit, variant).
- Suppliers and Purchase Orders with a draft → ordered → partial → received workflow; stock auto-increments on receive.
- Low-stock dashboard notice (parent + variant levels).

### Customers
- Optional walk-in or saved customers with purchase history.

### Sales History
- Filterable by date range and status; per-sale tax country/state breakdown.
- Void sale (restores variant stock), CSV export.

### Reports
- Revenue, sale count, average sale, gross profit, items sold.
- Revenue-by-day chart, top products, sales-by-cashier — date-range filtered.
- (Bundled add-on) **Advanced Reports** adds extra report types and CSV export.

### Roles
- `POS Cashier` and `POS Manager` roles; Administrators get every POS capability automatically.
- Every admin screen and REST endpoint is protected by capability + nonce checks.

---

## 5. Settings (POS → Settings)

- Store name, phone, email, currency code + symbol position/decimals (20 presets).
- Default tax country/state; tax-inclusive toggle; discount-before-tax toggle.
- Receipt header/footer text, paper width.
- Printer type: browser, USB ESC/POS, or network ESC/POS (IP + port 9100).
- PO (purchase order) number prefix.
- Barcode symbology & label sheet format.
- Oversell toggle; "delete all data on uninstall" checkbox (off by default — data survives plugin deletion by default).

---

## 6. Bundled add-ons (included, togglable)

All add-ons are part of this plugin and can be enabled/disabled on **POS → Add-ons**:

| Add-on | Purpose |
|---|---|
| **Kitchen Display** | Order tickets + bump flow (view/manage on kitchen screens) |
| **Customer Display** | Rotating product/price view for customer-facing screens |
| **Multi-Outlet** | Manage multiple outlets/tills with default-outlet setting |
| **Gift Cards** | Issue & redeem store gift cards (balance on card number) |
| **Loyalty** | Redeem loyalty points / top-up balances |
| **Multi-Currency** | Store multiple currencies with per-currency rates |
| **Table Service** | Assign tables (tabs) to orders |
| **Online Ordering** | Public storefront + order management (orders REST API) |
| **Offline Mode** | Settings/UX for intermittent connectivity |
| **Time Clock** | Staff shift clock-in/clock-out |
| **Low-Stock Auto PO** | Automatic purchase orders + email notification for low stock |
| **Advanced Reports** | Extra report types + CSV export |

---

## 7. Shortcodes

- `[simple_pos_online_order]` (Online Ordering add-on) — renders the public storefront on a page.
- **POS → Settings** lets you review the printed receipt style; there is no separate terminal shortcode registration needed.

---

## 8. FAQ

**Does this require WooCommerce?**
No. Fully standalone; it never reads or writes WooCommerce data.

**What happens on deactivate/delete?**
Deactivating never touches data. Data is removed only when you delete the plugin **and** have checked “Delete all data on uninstall” in Settings.

**Can I refund a sale?**
Yes — open the sale in Sales History and click **Void Sale**. Voiding restores stock for every tracked line item (including variants). Partial refunds are not yet supported.

**Why USB ESC/POS may not print in Chrome/Edge?**
WebUSB requires HTTPS and a user gesture. In HTTP, use the browser-print fallback or a network ESC/POS printer (set IP + port 9100 in Settings).

**Is printing dependent on a CDN?**
No. JsBarcode and all assets are vendored locally.

---

## 9. Troubleshooting

**No POS admin styles/scripts loading**
Update to 2.1.0 (2.0.9 fixed the wrong screen-check that prevented `pos-admin.css` from being enqueued).

**“Could not record sale.” on upgraded installs**
Schema auto-upgrade (DB 2.1.1+) recreates the missing sale-number sequence table and sale columns on the next admin load. No data loss. Update to a recent version and reload POS → Terminal.

**Taxes don’t match my region**
Open **POS → Taxes** and add/edit a class and rate for your country/state, then select that country/state in the terminal or settings.

---

## 10. Support

- Item page comments / your Envato account contact form.
- Enable `WP_DEBUG` and include any logged `Simple POS` messages when reporting issues.

---

## 11. License

GPL v2 or later — see `LICENSE.txt` in the plugin root. This plugin may be shared and modified under the terms of that license.