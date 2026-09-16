# Manual Test Cases - WordPress POS Plugin
**Version:** 2.1.0  
**Date:** September 9, 2026  
**Tester:** _______________  

---

## Table of Contents
1. [Installation & Activation](#1-installation--activation)
2. [Settings Configuration](#2-settings-configuration)
3. [Product Management](#3-product-management)
4. [Category Management](#4-category-management)
5. [Variant Management](#5-variant-management)
6. [Customer Management](#6-customer-management)
7. [POS Terminal](#7-pos-terminal)
8. [Sales & Checkout](#8-sales--checkout)
9. [Sales History & Voids](#9-sales-history--voids)
10. [Tax Configuration](#10-tax-configuration)
11. [Supplier Management](#11-supplier-management)
12. [Purchase Orders](#12-purchase-orders)
13. [Stock Management](#13-stock-management)
14. [Reports](#14-reports)
15. [CSV Import/Export](#15-csv-importexport)
16. [Backup & Restore](#16-backup--restore)
17. [Barcode Generation](#17-barcode-generation)
18. [Add-ons Management](#18-addons-management)
19. [REST API](#19-rest-api)
20. [Security Tests](#20-security-tests)

---

## 1. Installation & Activation

### Test Case 1.1: Plugin Installation
**Precondition:** WordPress installed, user logged in as admin

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to Plugins > Add New > Upload Plugin | Upload form appears |
| 2 | Click "Choose File" and select simple-pos.zip | File selected |
| 3 | Click "Install Now" | Installation completes successfully |
| 4 | Click "Activate Plugin" | Plugin activates, no errors |
| 5 | Check for admin menu "Simple POS" | Menu appears in sidebar |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 1.2: Database Tables Creation
**Precondition:** Plugin just activated

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open phpMyAdmin or database tool | Database accessible |
| 2 | Run: `SHOW TABLES LIKE '%pos_%'` | 13+ tables created |
| 3 | Verify table: wp_pos_products | Table exists with correct columns |
| 4 | Verify table: wp_pos_sales | Table exists |
| 5 | Verify table: wp_pos_sale_sequences | Table exists (Issue #28 fix) |
| 6 | Verify table: wp_pos_stock_log | Table exists |

**Status:** ☐ Pass ☐ Fail

---

## 2. Settings Configuration

### Test Case 2.1: Currency Settings
**Precondition:** Plugin activated, admin logged in

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to Simple POS > Settings | Settings page loads |
| 2 | Set Currency Code to "USD" | Field accepts value |
| 3 | Set Currency Symbol to "$" | Field accepts value |
| 4 | Set Currency Position to "before" | Dropdown works |
| 5 | Set Decimal Places to "2" | Field accepts value |
| 6 | Click "Save Settings" | Success message appears |
| 7 | Refresh page | Settings persist |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 2.2: Tax Settings
**Precondition:** Settings page accessible

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Set Tax Country to "US" | Field accepts value |
| 2 | Set Tax State to "CA" | Field accepts value |
| 3 | Set Tax Inclusive to unchecked | Checkbox works |
| 4 | Set Discount Before Tax to checked | Checkbox works |
| 5 | Click "Save Settings" | Success message appears |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 2.3: Receipt Settings
**Precondition:** Settings page accessible

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Set Store Name to "Test Store" | Field accepts value |
| 2 | Set Receipt Header to "Welcome!" | Field accepts value |
| 3 | Set Receipt Footer to "Thank you!" | Field accepts value |
| 4 | Click "Save Settings" | Success message appears |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 2.4: Sale Number Prefix
**Precondition:** Settings page accessible

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Set Sale Number Prefix to "INV-" | Field accepts value |
| 2 | Click "Save Settings" | Success message appears |
| 3 | Navigate to Terminal, complete a sale | Sale number shows "INV-" prefix |
| 4 | Verify format: INV-000001 | 6-digit zero-padded number |

**Status:** ☐ Pass ☐ Fail

---

## 3. Product Management

### Test Case 3.1: Create Product
**Precondition:** Admin logged in

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to Simple POS > Products | Products list page loads |
| 2 | Click "Add Product" | Product form appears |
| 3 | Enter Name: "Test Product" | Field accepts value |
| 4 | Enter SKU: "TEST-001" | Field accepts value |
| 5 | Enter Price: "29.99" | Field accepts value |
| 6 | Enter Cost Price: "15.00" | Field accepts value |
| 7 | Enter Stock Qty: "100" | Field accepts value |
| 8 | Check "Track Stock" | Checkbox checked |
| 9 | Select Category | Dropdown works |
| 10 | Click "Save Product" | Success message, product created |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 3.2: Edit Product
**Precondition:** Product exists

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click on product name | Edit form loads |
| 2 | Change Name to "Updated Product" | Field updates |
| 3 | Change Price to "34.99" | Field updates |
| 4 | Click "Save Product" | Success message |
| 5 | Verify changes persisted | Updated values shown |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 3.3: Delete Product
**Precondition:** Product exists

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click "Delete" on product | Confirmation dialog appears |
| 2 | Confirm deletion | Product removed from list |
| 3 | Verify product not in database | Product deleted |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 3.4: Duplicate SKU Validation
**Precondition:** Product with SKU "TEST-001" exists

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click "Add Product" | Form appears |
| 2 | Enter SKU: "TEST-001" | Field accepts value |
| 3 | Click "Save Product" | Error: "SKU already used" |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 3.5: Product Search
**Precondition:** Multiple products exist

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Enter "Test" in search box | Search field accepts input |
| 2 | Press Enter or click Search | Filtered results shown |
| 3 | Clear search | All products shown |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 3.6: Product Status Toggle
**Precondition:** Product exists

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Change product status to "Inactive" | Status updates |
| 2 | Save product | Success message |
| 3 | Navigate to Terminal | Inactive product not shown |
| 4 | Change status back to "Active" | Product visible again |

**Status:** ☐ Pass ☐ Fail

---

## 4. Category Management

### Test Case 4.1: Create Category
**Precondition:** Admin logged in

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to Simple POS > Categories | Categories page loads |
| 2 | Enter Name: "Electronics" | Field accepts value |
| 3 | Enter Description: "Electronic items" | Field accepts value |
| 4 | Click "Add Category" | Success message |
| 5 | Verify category in list | Category appears |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 4.2: Edit Category
**Precondition:** Category exists

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click on category name | Edit form loads |
| 2 | Change Name to "Electronics & Gadgets" | Field updates |
| 3 | Click "Save Category" | Success message |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 4.3: Delete Category
**Precondition:** Category with no products

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click "Delete" on category | Confirmation dialog |
| 2 | Confirm deletion | Category removed |

**Status:** ☐ Pass ☐ Fail

---

## 5. Variant Management

### Test Case 5.1: Create Variant
**Precondition:** Parent product exists

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open parent product edit page | Product form loads |
| 2 | Scroll to Variants section | Variants section visible |
| 3 | Click "Add Variant" | Variant form appears |
| 4 | Enter Attributes: {"Color": "Red"} | Field accepts JSON |
| 5 | Enter SKU: "TEST-001-RED" | Field accepts value |
| 6 | Enter Price: "32.99" | Field accepts value |
| 7 | Enter Stock: "50" | Field accepts value |
| 8 | Click "Save Variant" | Success message |
| 9 | Verify variant in list | Variant appears |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 5.2: Inactive Parent Prevents Variant Creation
**Precondition:** Inactive parent product

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Set parent product status to "Inactive" | Status updates |
| 2 | Try to add variant | Error: "Cannot create variant for inactive product" |
| 3 | Reactivate parent | Status updates |
| 4 | Add variant | Success |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 5.3: Variant Stock Tracking
**Precondition:** Variant with track_stock enabled

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Set variant stock to "10" | Stock updates |
| 2 | Sell variant in Terminal | Stock decreases |
| 3 | Check stock log | Stock change logged |

**Status:** ☐ Pass ☐ Fail

---

## 6. Customer Management

### Test Case 6.1: Create Customer
**Precondition:** Admin logged in

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to Simple POS > Customers | Customers page loads |
| 2 | Click "Add Customer" | Form appears |
| 3 | Enter Name: "John Doe" | Field accepts value |
| 4 | Enter Phone: "555-0123" | Field accepts value |
| 5 | Enter Email: "john@example.com" | Field accepts value |
| 6 | Click "Save Customer" | Success message |
| 7 | Verify in list | Customer appears |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 6.2: Customer Search
**Precondition:** Multiple customers exist

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Enter "John" in search | Filtered results shown |
| 2 | Search by phone number | Customer found |
| 3 | Search by email | Customer found |

**Status:** ☐ Pass ☐ Fail

---

## 7. POS Terminal

### Test Case 7.1: Terminal Access
**Precondition:** Admin/cashier logged in

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to Simple POS > Terminal | Terminal loads |
| 2 | Verify all sections visible | Cart, products, payment areas shown |
| 3 | Verify scan input focused | Input field has focus |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 7.2: Product Scanning
**Precondition:** Products with barcodes exist

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click scan input field | Field focused |
| 2 | Type barcode and press Enter | Product added to cart |
| 3 | Verify product details | Name, price, qty correct |
| 4 | Scan same product again | Qty increases by 1 |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 7.3: Manual Product Selection
**Precondition:** Products exist

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click on product in product list | Product added to cart |
| 2 | Verify cart update | Cart shows product |
| 3 | Click same product again | Qty increases |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 7.4: Variant Selection Modal
**Precondition:** Product with variants exists

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click product with variants | Modal opens |
| 2 | Verify modal accessibility | ARIA attributes present |
| 3 | Press Tab key | Focus cycles through buttons |
| 4 | Press Escape key | Modal closes |
| 5 | Reopen modal, click variant | Variant added to cart |
| 6 | Verify variant details | Correct price and stock shown |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 7.5: Cart Operations
**Precondition:** Items in cart

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click "+" on item | Qty increases |
| 2 | Click "-" on item | Qty decreases |
| 3 | Click "Remove" button | Item removed from cart |
| 4 | Verify cart total updates | Total recalculated |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 7.6: Discount Application
**Precondition:** Items in cart

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Enter discount amount: "5.00" | Field accepts value |
| 2 | Select discount type: "Fixed" | Dropdown works |
| 3 | Verify discount applied | Cart total reduced |
| 4 | Change to "Percent" | Discount recalculated |
| 5 | Clear discount | Original total restored |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 7.7: Customer Assignment
**Precondition:** Customers exist, items in cart

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Select customer from dropdown | Customer selected |
| 2 | Verify customer name shown | Name displayed |
| 3 | Change customer | New customer selected |
| 4 | Remove customer | No customer assigned |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 7.8: Hold & Recall Cart
**Precondition:** Items in cart

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click "Hold Cart" | Cart saved, cleared |
| 2 | Add different items | New cart created |
| 3 | Click "Recall Cart" | Previous cart restored |
| 4 | Verify items match | All items restored |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 7.9: Clear Cart
**Precondition:** Items in cart

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click "Clear Cart" | Confirmation (if implemented) |
| 2 | Confirm action | Cart emptied |
| 3 | Verify error messages cleared | No stale errors |
| 4 | Verify tax breakdown cleared | Clean state |

**Status:** ☐ Pass ☐ Fail

---

## 8. Sales & Checkout

### Test Case 8.1: Cash Payment
**Precondition:** Items in cart

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Verify total: $29.99 | Total shown |
| 2 | Enter Amount Paid: "30.00" | Field accepts value |
| 3 | Click "Complete Sale" | Sale processed |
| 4 | Verify change due: $0.01 | Change calculated |
| 5 | Verify receipt generated | Receipt shown/printed |
| 6 | Verify stock decreased | Stock updated |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 8.2: Exact Payment
**Precondition:** Items in cart

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Enter exact amount | Field accepts value |
| 2 | Click "Complete Sale" | Sale processed |
| 3 | Verify change: $0.00 | No change due |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 8.3: Insufficient Payment
**Precondition:** Items in cart, total $29.99

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Enter Amount Paid: "20.00" | Field accepts value |
| 2 | Click "Complete Sale" | Error or warning shown |
| 3 | Sale not processed | Cart remains |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 8.4: Card Payment
**Precondition:** Items in cart

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Select Payment Method: "Card" | Dropdown works |
| 2 | Enter Amount Paid: "29.99" | Field accepts value |
| 3 | Click "Complete Sale" | Sale processed |
| 4 | Verify payment method recorded | "Card" saved |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 8.5: Sale Number Generation (Issue #28 Fix)
**Precondition:** Multiple sales completed

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Complete sale #1 | Sale number: POS-000001 |
| 2 | Complete sale #2 | Sale number: POS-000002 |
| 3 | Complete sale #3 | Sale number: POS-000003 |
| 4 | Verify no duplicates | All numbers unique |
| 5 | Check database: `SELECT sale_number, COUNT(*) FROM wp_pos_sales GROUP BY sale_number HAVING COUNT(*) > 1` | No results |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 8.6: Tax Calculation (Issue #36 Fix)
**Precondition:** Tax rates configured

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Set country: "US", state: "CA" | Tax calculated |
| 2 | Set country: "IN", state: "MH" | IGST applied |
| 3 | Set country: "IN", state: same as rate | CGST+SGST split |
| 4 | Leave country empty | Error: "Tax country required" |
| 5 | Enter invalid country: "USA" | Error: "Invalid country code" |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 8.7: India GST Split (Issue #39 Fix)
**Precondition:** India tax rates with gst_split enabled

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Sale within Maharashtra (MH → MH) | CGST + SGST shown |
| 2 | Sale from MH to DL (MH → DL) | IGST shown |
| 3 | Sale with empty state | Falls back to store state |
| 4 | Verify amounts: $18 total | CGST: $9, SGST: $9 |

**Status:** ☐ Pass ☐ Fail

---

## 9. Sales History & Voids

### Test Case 9.1: View Sales History
**Precondition:** Sales exist

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to Simple POS > Sales History | Page loads |
| 2 | Verify sales list | All sales shown |
| 3 | Filter by date range | Filtered results |
| 4 | Filter by status | Filtered results |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 9.2: Void Sale
**Precondition:** Completed sale exists

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click "Void" on sale | Confirmation dialog |
| 2 | Enter void note | Field accepts value |
| 3 | Confirm void | Sale voided |
| 4 | Verify stock restored | Stock increased |
| 5 | Verify sale status changed | Status: "voided" |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 9.3: View Sale Details
**Precondition:** Sale exists

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click on sale number | Details page loads |
| 2 | Verify line items | All items shown |
| 3 | Verify totals | Correct amounts |
| 4 | Verify customer info | Customer shown (if assigned) |

**Status:** ☐ Pass ☐ Fail

---

## 10. Tax Configuration

### Test Case 10.1: Create Tax Class
**Precondition:** Admin logged in

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to Simple POS > Taxes | Tax page loads |
| 2 | Enter Name: "Reduced Rate" | Field accepts value |
| 3 | Enter Slug: "reduced" | Field accepts value |
| 4 | Click "Add Class" | Success message |
| 5 | Verify in list | Class appears |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 10.2: Create Tax Rate
**Precondition:** Tax class exists

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Select tax class | Dropdown works |
| 2 | Enter Country: "US" | Field accepts value |
| 3 | Enter State: "CA" | Field accepts value |
| 4 | Enter Rate: "7.25" | Field accepts value |
| 5 | Check "GST Split" if India | Checkbox works |
| 6 | Click "Add Rate" | Success message |
| 7 | Verify in list | Rate appears |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 10.3: Tax Rate Priority
**Precondition:** Multiple rates for same location

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create rate with priority 1 | Rate created |
| 2 | Create rate with priority 2 | Rate created |
| 3 | Apply tax to sale | Higher priority rate used first |

**Status:** ☐ Pass ☐ Fail

---

## 11. Supplier Management

### Test Case 11.1: Create Supplier
**Precondition:** Admin logged in

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to Simple POS > Suppliers | Suppliers page loads |
| 2 | Click "Add Supplier" | Form appears |
| 3 | Enter Name: "Acme Corp" | Field accepts value |
| 4 | Enter Contact: "Jane Smith" | Field accepts value |
| 5 | Enter Phone: "555-0456" | Field accepts value |
| 6 | Enter Email: "jane@acme.com" | Field accepts value |
| 7 | Click "Save Supplier" | Success message |

**Status:** ☐ Pass ☐ Fail

---

## 12. Purchase Orders

### Test Case 12.1: Create Purchase Order
**Precondition:** Supplier exists, products exist

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to Simple POS > Purchase Orders | PO page loads |
| 2 | Click "Create PO" | Form appears |
| 3 | Select Supplier | Dropdown works |
| 4 | Add product item | Item row added |
| 5 | Enter Qty: "50" | Field accepts value |
| 6 | Enter Cost: "10.00" | Field accepts value |
| 7 | Click "Save PO" | Success message |
| 8 | Verify PO number | PO-000001 format |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 12.2: Edit Purchase Order (Issue #41 Fix)
**Precondition:** Draft PO exists

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open draft PO | PO details shown |
| 2 | Click "Edit" | Edit form loads |
| 3 | Change quantity | Field updates |
| 4 | Add new item | Item added |
| 5 | Click "Save Changes" | Success message |
| 6 | Verify changes | Updated values shown |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 12.3: Edit Non-Draft PO
**Precondition:** Ordered PO exists

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open ordered PO | PO details shown |
| 2 | Try to edit | Error: "Can only edit draft POs" |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 12.4: Receive Purchase Order
**Precondition:** Ordered PO exists

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open PO | PO details shown |
| 2 | Click "Receive" | Receive form appears |
| 3 | Enter received qty: "25" | Field accepts value |
| 4 | Click "Confirm Receive" | Success message |
| 5 | Verify stock increased | Stock updated |
| 6 | Verify cost price updated (Issue #31) | Cost price correct |
| 7 | Verify PO status: "partial" | Status updated |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 12.5: Full Receive
**Precondition:** PO with remaining qty

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Receive remaining qty | All items received |
| 2 | Verify PO status: "received" | Status updated |
| 3 | Verify stock fully updated | All stock added |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 12.6: Update PO Status
**Precondition:** Draft PO exists

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Select PO | PO highlighted |
| 2 | Change status to "Ordered" | Status updates |
| 3 | Verify ordered_at timestamp | Timestamp set |
| 4 | Change to "Cancelled" | Status updates |

**Status:** ☐ Pass ☐ Fail

---

## 13. Stock Management

### Test Case 13.1: Manual Stock Adjustment
**Precondition:** Product with track_stock

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open product edit page | Form loads |
| 2 | Click "Adjust Stock" | Adjustment form appears |
| 3 | Enter delta: "+10" | Field accepts value |
| 4 | Enter note: "Restock" | Field accepts value |
| 5 | Click "Save" | Success message |
| 6 | Verify stock increased | Stock updated |
| 7 | Check stock log | Change logged |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 13.2: Stock Validation (Issue #37 Fix)
**Precondition:** Product exists

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Try to adjust with non-numeric value | Error: "Must be numeric" |
| 2 | Try to adjust with null | Error: "Must be numeric" |
| 3 | Adjust with 0 | No-op, no error |
| 4 | Adjust with negative (insufficient stock) | Error or warning |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 13.3: Negative Stock Control
**Precondition:** Settings > Allow Negative Stock unchecked

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Set product stock to "5" | Stock set |
| 2 | Try to sell 10 | Error: "Not enough stock" |
| 3 | Enable negative stock | Setting saved |
| 4 | Try to sell 10 | Sale allowed, stock: -5 |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 13.4: Low Stock Alert
**Precondition:** Product with low_stock_threshold = 5

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Set stock to "5" | At threshold |
| 2 | Navigate to Dashboard | Low stock notice shown |
| 3 | Set stock to "10" | Above threshold |
| 4 | Notice disappears | Clean dashboard |

**Status:** ☐ Pass ☐ Fail

---

## 14. Reports

### Test Case 14.1: Sales Summary Report
**Precondition:** Sales exist

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to Simple POS > Reports | Reports page loads |
| 2 | Select date range | Date picker works |
| 3 | View summary | Total sales, tax shown |
| 4 | Export report | Download triggered |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 14.2: Product Sales Report
**Precondition:** Product sales exist

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | View product sales | Products listed |
| 2 | Sort by quantity | Sorted correctly |
| 3 | Sort by revenue | Sorted correctly |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 14.3: Stock Report
**Precondition:** Products with stock

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | View stock report | All products shown |
| 2 | Filter low stock | Only low stock items |
| 3 | Export stock report | Download triggered |

**Status:** ☐ Pass ☐ Fail

---

## 15. CSV Import/Export

### Test Case 15.1: Export Products CSV
**Precondition:** Products exist

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to Products > Export | Export form loads |
| 2 | Click "Export" | CSV downloads |
| 3 | Open CSV in Excel | File opens correctly |
| 4 | Verify all columns | All fields present |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 15.2: CSV Formula Injection Prevention (Issue #30 Fix)
**Precondition:** Product with name starting with "="

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create product: name "=cmd\|'/c calc'!A1" | Product created |
| 2 | Export products CSV | CSV downloads |
| 3 | Open in Excel | No formula execution |
| 4 | Verify cell shows literal text | Escaped with single quote |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 15.3: Import Products CSV
**Precondition:** CSV file prepared

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to Products > Import | Import form loads |
| 2 | Upload CSV file | File selected |
| 3 | Click "Import" | Import processes |
| 4 | Verify imported count | Correct number shown |
| 5 | Verify errors | Any errors displayed |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 15.4: Import Validation
**Precondition:** Invalid CSV file

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Upload non-CSV file | Error: "Only CSV files allowed" |
| 2 | Upload empty CSV | Error: "Empty CSV" |
| 3 | Upload CSV missing required columns | Error: "Missing column" |
| 4 | Upload CSV > 5MB | Error: "File exceeds limit" |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 15.5: Export/Import Variants
**Precondition:** Products with variants exist

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Export variants CSV | CSV downloads |
| 2 | Verify attributes format | "Key:Value; Key2:Value2" |
| 3 | Import variants CSV | Import processes |
| 4 | Verify variants created | Variants appear |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 15.6: Export Sales CSV
**Precondition:** Sales exist

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to Sales > Export | Export form loads |
| 2 | Set date range | Filters applied |
| 3 | Click "Export" | CSV downloads |
| 4 | Verify sale data | All fields present |

**Status:** ☐ Pass ☐ Fail

---

## 16. Backup & Restore

### Test Case 16.1: Create Backup
**Precondition:** Data exists

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to Simple POS > Backup | Backup page loads |
| 2 | Click "Export Backup" | JSON file downloads |
| 3 | Verify file contains version (Issue #38) | `_backup_version` present |
| 4 | Verify file contains all tables | All data exported |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 16.2: Restore Backup
**Precondition:** Backup file exists

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click "Import Backup" | File upload form |
| 2 | Select backup file | File selected |
| 3 | Click "Restore" | Restoration processes |
| 4 | Verify success message | "Backup restored successfully" |
| 5 | Verify data restored | All data present |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 16.3: Backup Version Validation (Issue #38 Fix)
**Precondition:** Backup from newer version

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Upload backup with version > current | Error shown |
| 2 | Error message | "Backup is from a newer version" |
| 3 | Data not restored | Original data intact |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 16.4: Backup Integrity Validation
**Precondition:** Malformed backup file

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Upload invalid JSON | Error: "Invalid backup file" |
| 2 | Upload backup with invalid columns | Error logged, skipped |
| 3 | Verify rollback on failure | Original data intact |

**Status:** ☐ Pass ☐ Fail

---

## 17. Barcode Generation

### Test Case 17.1: Generate Barcode Image
**Precondition:** Product with barcode/SKU

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open product page | Product shown |
| 2 | Click "Print Barcode" | Barcode page loads |
| 3 | Select scale: "2" | Dropdown works |
| 4 | Click "Generate" | PNG image displayed |
| 5 | Right-click > Save | Image downloads |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 17.2: Barcode Scale Limits (Issue #29 Fix)
**Precondition:** Barcode page accessible

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Enter scale: "1" | Works |
| 2 | Enter scale: "10" | Works (max) |
| 3 | Enter scale: "11" | Limited to 10 |
| 4 | Enter scale: "9999" | Limited to 10 |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 17.3: Barcode Dimensions Validation
**Precondition:** Product with long barcode

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Request large barcode | Error if exceeds limits |
| 2 | Error message | "Dimensions exceed limits" |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 17.4: Barcode Character Validation (Issue #32 Fix)
**Precondition:** Product with lowercase barcode

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Set barcode: "abc123" | Saved |
| 2 | Generate barcode | Converted to uppercase |
| 3 | Log entry | "Converting to uppercase" logged |

**Status:** ☐ Pass ☐ Fail

---

## 18. Add-ons Management

### Test Case 18.1: View Add-ons
**Precondition:** Admin logged in

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to Simple POS > Add-ons | Add-ons page loads |
| 2 | Verify bundled add-ons list | Add-ons shown |
| 3 | Verify catalog section | Available add-ons shown |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 18.2: Enable/Disable Add-on (Issue #34 Fix)
**Precondition:** Add-ons page accessible

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click "Enable" on add-on | POST form submitted |
| 2 | Verify nonce in form | Security field present |
| 3 | Verify status change | Status: "Enabled" |
| 4 | Click "Disable" | POST form submitted |
| 5 | Verify status change | Status: "Disabled" |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 18.3: Add-on Version Compatibility (Issue #35 Fix)
**Precondition:** Add-on with version requirement

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Enable incompatible add-on | Skipped during load |
| 2 | Check admin notice | Compatibility error shown |
| 3 | Notice includes add-on name | Name displayed |
| 4 | Notice includes required version | Version shown |

**Status:** ☐ Pass ☐ Fail

---

## 19. REST API

### Test Case 19.1: API Authentication
**Precondition:** API enabled

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send unauthenticated request | 401 Unauthorized |
| 2 | Send with valid nonce | 200 OK |
| 3 | Send with invalid nonce | 403 Forbidden |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 19.2: Products API
**Precondition:** Products exist

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET /wp-json/simple-pos/v1/products | Products list returned |
| 2 | GET /wp-json/simple-pos/v1/products/1 | Single product returned |
| 3 | POST /wp-json/simple-pos/v1/products | Product created |
| 4 | PUT /wp-json/simple-pos/v1/products/1 | Product updated |
| 5 | DELETE /wp-json/simple-pos/v1/products/1 | Product deleted |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 19.3: Sales API
**Precondition:** Sales exist

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET /wp-json/simple-pos/v1/sales | Sales list returned |
| 2 | POST /wp-json/simple-pos/v1/sales | Checkout processed |
| 3 | POST /wp-json/simple-pos/v1/sales/1/void | Sale voided |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 19.4: Purchase Orders API (Issue #41 Fix)
**Precondition:** POs exist

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET /wp-json/simple-pos/v1/purchase-orders | POs listed |
| 2 | POST /wp-json/simple-pos/v1/purchase-orders | PO created |
| 3 | PUT /wp-json/simple-pos/v1/purchase-orders/1 | PO updated (draft only) |
| 4 | PUT on ordered PO | Error: "Can only edit draft POs" |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 19.5: Tax Calculation API (Issue #36 Fix)
**Precondition:** Tax rates configured

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST /wp-json/simple-pos/v1/tax/calculate | Tax calculated |
| 2 | Send without country | Error: "Tax country required" |
| 3 | Send invalid country | Error: "Invalid country code" |
| 4 | Send valid country | Tax calculated correctly |

**Status:** ☐ Pass ☐ Fail

---

## 20. Security Tests

### Test Case 20.1: SQL Injection Prevention
**Precondition:** Terminal accessible

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Enter `' OR 1=1 --` in scan input | No SQL error |
| 2 | Enter `'; DROP TABLE wp_pos_sales; --` | No damage |
| 3 | Search with SQL injection attempt | Sanitized input |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 20.2: XSS Prevention
**Precondition:** Product creation accessible

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create product: `<script>alert('XSS')</script>` | Stored safely |
| 2 | View product list | Script not executed |
| 3 | Export to CSV | HTML entities escaped |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 20.3: CSRF Protection
**Precondition:** Forms accessible

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Submit form without nonce | Error: "Security check failed" |
| 2 | Submit form with expired nonce | Error: "Security check failed" |
| 3 | Submit form with valid nonce | Success |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 20.4: Permission Checks
**Precondition:** Different user roles

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Cashier accesses Settings | Denied |
| 2 | Cashier accesses Terminal | Allowed |
| 3 | Cashier tries to void sale | Check capability |
| 4 | Admin accesses all pages | Allowed |

**Status:** ☐ Pass ☐ Fail

---

### Test Case 20.5: Error Message Consistency (Issue #47 Fix)
**Precondition:** Permission denied scenarios

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Trigger various permission errors | Same message shown |
| 2 | Message text | "You do not have permission to perform this action." |

**Status:** ☐ Pass ☐ Fail

---

## Appendix: Test Execution Log

| Date | Tester | Tests Passed | Tests Failed | Notes |
|------|--------|--------------|--------------|-------|
| | | /113 | | |
| | | /113 | | |
| | | /113 | | |

---

## Sign-off

**Test Lead:** _______________  
**Date:** _______________  
**Result:** ☐ PASS ☐ FAIL ☐ CONDITIONAL PASS  

**Notes:** _______________
