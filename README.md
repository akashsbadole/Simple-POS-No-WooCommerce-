# Simple POS — WordPress Point of Sale Plugin

A lightweight, standalone Point of Sale system for WordPress. No WooCommerce required. Custom database tables, REST API, per-country VAT/GST, variants, suppliers & purchase orders, barcode labels and ESC/POS printing.

## Requirements

| Dependency | Minimum | Recommended |
|------------|---------|-------------|
| WordPress  | 5.8     | 6.4+        |
| PHP        | 7.4     | 8.1+        |
| MySQL      | 5.6     | 8.0+        |
| Extensions | mbstring, GD | —       |
| HTTPS      | Required for USB printing (WebUSB) | — |

> **Note:** InnoDB engine is required for transaction support. MyISAM tables will work but without atomic checkout guarantees.

## Installation

### From ZIP

1. Download the `simple-pos.zip` file.
2. Go to **Plugins → Add New → Upload Plugin**.
3. Choose the zip and click **Install Now**.
4. Click **Activate Plugin**.

The plugin creates its own database tables (`wp_pos_*`) and the **POS Cashier** / **POS Manager** roles automatically on activation.

### Manual Upload

1. Extract the zip into `/wp-content/plugins/simple-pos/`.
2. Activate via **Plugins → Installed Plugins**.

### Post-Activation Setup

1. Go to **POS → Settings** and configure:
   - Store name, base currency (USD/EUR/INR/GBP + 16 more)
   - Default tax country/state
   - Receipt header/footer
   - Printer type (browser / USB / network)
2. Go to **POS → Taxes** to review tax classes and rates (US/IN/EU presets pre-loaded).
3. Go to **POS → Products** and add products with categories and variants.
4. Go to **POS → Terminal** to start selling.

## Quick Start

### Creating Your First Product

1. Go to **POS → Products → Add New**.
2. Fill in name, price, SKU, barcode.
3. Assign a category and tax class.
4. Enable **Track stock** if inventory-managed.
5. Click **Save**.

### Adding Variants (Size/Color)

1. Edit a product → expand **Variants**.
2. Click **Add Variant** and fill in attributes (e.g., Size: M, Color: Red).
3. Set per-variant SKU, barcode, price, stock, and image.

### Completing a Sale

1. Open **POS → Terminal**.
2. Scan a barcode or click a product to add to cart.
3. For variants, select attributes in the picker modal.
4. Apply discount if needed.
5. Select payment method (Cash/Card).
6. Enter amount paid → click **Complete Sale**.
7. Print receipt (browser print or USB ESC/POS).

## Features

### Terminal
- Barcode/SKU scan input (Enter to add)
- Click-to-add product grid with category filters
- Variant picker modal for size/color products
- Per-country tax calculation (server-authoritative)
- Percentage or fixed-amount discounts
- Cash/Card payment with change calculator
- Browser print + USB ESC/POS (WebUSB) + network printer support
- Cash drawer kick via ESC/POS

### Products & Variants
- SKU, barcode, price, cost price per product
- Free-form variant attributes (size, color, material, etc.)
- Per-variant: SKU, barcode, price, cost price, stock, image, low-stock threshold
- Stock tracking per variant or inherited from parent
- Tax class assignment per product
- CSV import/export (products + variants)

### Inventory
- Stock adjustment on every sale, void, PO receive, and manual edit
- Full audit trail in stock log table
- Low-stock dashboard notice (including variants)
- Allow/disallow negative stock (configurable)

### Purchase Orders & Suppliers
- Supplier management with contact details
- PO workflow: Draft → Ordered → Partial → Received
- Auto stock increment on receive
- Cost price update on receive

### Reports
- Revenue, sale count, average sale value
- Gross profit (cost of goods vs. sale price)
- Items sold
- Revenue-by-day chart
- Top products by revenue
- Sales-by-cashier
- Low stock products (including variants)
- Date-range filtering on all reports

### Sales History
- Filter by date range, status (completed/voided)
- Tax breakdown per sale (country/state + line items)
- Variant line items
- Void sale (restores stock)
- CSV export

### Customers
- Walk-in (no customer) or saved customer
- Purchase history per customer
- Customer type (wholesale/retail) with optional tax exemption
- GSTIN / Business ID fields (India compliance)

### Barcode Labels
- A4 30-up / 65-up sheets
- Roll 58mm / 80mm sheets
- CODE128, EAN13, QR symbologies
- Bulk print from **POS → Barcode Labels**
- Vendored JsBarcode (no external CDN)

### Roles
- **POS Cashier** — terminal + sales history only
- **POS Manager** — full POS access including settings, reports, and product management
- **Administrator** — inherits all POS capabilities

### Settings
- Store name, base currency (20 presets)
- Tax defaults (country/state, inclusive/exclusive, discount-before-tax, rounding)
- Paper width (58mm / 80mm)
- Printer type (browser / USB / network)
- Network printer IP:port
- PO number prefix
- Barcode symbology (CODE128/EAN13/QR)
- Barcode label format (A4/roll sheet)
- Receipt header/footer
- Allow negative stock toggle
- Decimal places
- Delete data on uninstall

## REST API

All endpoints are under `/wp-json/pos/v1/`. Authentication uses WordPress cookie + nonce.

### Products
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/products` | List products (paginated) |
| GET | `/products/{id}` | Get product with variants |
| POST | `/products` | Create product |
| PUT | `/products/{id}` | Update product |
| DELETE | `/products/{id}` | Delete product |
| GET | `/products/lookup/{code}` | Barcode/SKU lookup |
| POST | `/products/variants/import` | Import variants from CSV |
| GET | `/products/variants/export` | Export variants as CSV |
| GET | `/products/{id}/barcode-image` | Generate barcode PNG |

### Sales
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/sales` | Create sale (checkout) |
| GET | `/sales` | List sales |
| GET | `/sales/{id}` | Get sale detail |
| POST | `/sales/{id}/void` | Void sale |
| GET | `/sales/export` | Export sales CSV |

### Other
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/tax/calculate` | Preview tax calculation |
| GET | `/reports/sales` | Daily sales summary |
| GET | `/reports/top-products` | Top products by revenue |
| GET | `/reports/low-stock` | Low stock products |
| POST | `/csv/import` | Import products from CSV |

### Error Handling

All errors follow consistent format:

```json
{
  "success": false,
  "data": "Error message here",
  "code": "error_code"
}
```

## Database Schema

The plugin uses its own tables (prefix: `wp_pos_`):

| Table | Description |
|-------|-------------|
| `wp_pos_products` | Products |
| `wp_pos_product_variants` | Product variants |
| `wp_pos_categories` | Product categories |
| `wp_pos_customers` | Customers |
| `wp_pos_sales` | Sales header |
| `wp_pos_sale_items` | Sale line items |
| `wp_pos_stock_log` | Stock adjustment audit trail |
| `wp_pos_suppliers` | Suppliers |
| `wp_pos_purchase_orders` | Purchase orders |
| `wp_pos_po_items` | PO line items |
| `wp_pos_tax_classes` | Tax classes |
| `wp_pos_tax_rates` | Tax rates per class/country/state |

Foreign keys enforce referential integrity on InnoDB tables.

## Troubleshooting

### "Cart is empty" on checkout
Ensure products have at least one variant or no variants configured. Check browser console for API errors.

### USB printer not working
- Requires HTTPS (WebUSB security requirement)
- Requires user gesture (button click) to connect
- Check `chrome://usb-devices` for device visibility

### Tax calculation mismatch
- Verify tax country/state in terminal matches expected rates
- Check **POS → Taxes** for correct rates
- Server calculates tax; terminal shows preview only

### Stock not updating on PO receive
- Ensure PO status is "Ordered" or "Partial"
- Click "Receive" with quantity > 0
- Check stock log for audit trail

## Changelog

### 1.0.0
- Initial public release: POS terminal, products, variants, tax engine, suppliers, purchase orders, barcode labels, CSV import/export, reports, roles, and settings
- All critical security issues fixed (CSV formula injection, barcode DoS, sale number race condition)
- WCAG 2.1 accessible variant picker
- Transactional consistency across all core operations

### 2.0.9
- Fix: admin CSS/JS load on all POS screens (screen check never matched, so pos-admin.css was never enqueued)
- Fix: checkout "Could not record sale." — DB auto-upgrade to 2.1.1 creates missing sale-number sequence table and sale columns; sale-number generation self-heals
- Fix: [simple_pos_terminal] shortcode returns markup in place with reliable asset loading
- Fix: legacy products.tax_rate column preserved (dropping it broke product lookups/saves)
- Checkout DB failures now log the real SQL error under WP_DEBUG

### 2.0.0
- Tax engine with classes/rates per country+state (US/IN/EU presets)
- Product variants (free-form attributes, per-variant stock/pricing)
- Store-wide currency (20 presets)
- Barcode labels (A4/roll sheets, CODE128/EAN13/QR)
- Suppliers & Purchase Orders (draft/ordered/partial/received)
- CSV import/export (products + variants + sales)
- USB ESC/POS printing (WebUSB + browser fallback)
- Reports (revenue, profit, top products, low stock)
- POS Cashier / POS Manager roles
- Transaction support with InnoDB verification
- Foreign key constraints for data integrity

### 1.0.0
- Initial release: terminal, products, categories, customers, sales history, reports, roles, settings

## License

GPL v2 or later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).

## Credits

- [JsBarcode](https://github.com/lindell/JsBarcode) 3.11.6 (MIT) — vendored for barcode label printing
