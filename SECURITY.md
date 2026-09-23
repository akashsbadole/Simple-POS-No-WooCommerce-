# Security Policy

## Supported Versions

| Version | Supported          |
|---------|-----------|
| 1.0.x   | Yes       |

## Reporting a Vulnerability

If you discover a security vulnerability in Simple POS, please report it responsibly.

**Do NOT open a public GitHub issue for security vulnerabilities.**

### How to Report

1. **Email:** Send details to the plugin maintainer (see `Author URI` in `wp-pos-plugin.php`)
2. **Include:**
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if any)

### What to Expect

- **Acknowledgment:** Within 48 hours of your report
- **Status update:** Within 7 days with assessment
- **Fix timeline:** Critical issues patched within 14 days
- **Credit:** You will be credited in the release notes unless you prefer anonymity

## Security Measures

### Authentication & Authorization

- All REST API endpoints require WordPress cookie authentication + nonce verification (`X-WP-Nonce` header)
- Capability checks on every endpoint (`manage_pos_products`, `manage_pos_terminal`, `manage_pos_reports`)
- Nonce verification on all admin form submissions (`wp_nonce_field` / `check_admin_referer`)

### Input Validation

- All database queries use `$wpdb->prepare()` with parameterized queries
- Table name prefix sanitized against SQL injection via `preg_replace('/[^a-z0-9_]/i', '')`
- File uploads validated: MIME type check (CSV only) + 5MB size limit
- `sanitize_text_field()` / `intval()` / `floatval()` on all user inputs

### Data Protection

- No secrets or API keys stored in database
- No sensitive data logged to error logs
- Transaction support with rollback on failure (InnoDB)
- Foreign key constraints prevent orphaned records
- Stock adjustments logged with full audit trail

### File Security

- Direct access blocked (`if (!defined('ABSPATH')) exit;`)
- No file uploads accepted except CSV (validated server-side)
- Vendored JavaScript loaded locally (no external CDN)

## Security Improvements in 1.0.0

- Fixed CSV sales import to bypass cart validation (prevents empty cart exploit)
- Added InnoDB transaction support with automatic rollback
- Fixed sale number race condition with LOCK TABLES
- Added file upload validation (MIME type + size limit)
- Added stock adjustment error propagation in void operations
- Added foreign key constraints for referential integrity
- Sanitized table prefix against SQL injection
- Added transaction error logging
- Settings autoload disabled for performance

## Best Practices for Deployment

1. **HTTPS:** Required for USB printing (WebUSB) and recommended for all API calls
2. **WordPress Updates:** Keep WordPress, PHP, and MySQL updated
3. **User Roles:** Assign POS Cashier/POS Manager roles instead of giving full admin access
4. **Backups:** Regular database backups (the plugin stores all data in custom tables)
5. **File Permissions:** Standard WordPress file permissions (644 for files, 755 for directories)

## Data Storage

All POS data is stored in WordPress database tables with the `wp_pos_` prefix:

- Products, variants, categories
- Customers
- Sales, sale items
- Stock log (audit trail)
- Suppliers, purchase orders
- Tax classes and rates
- Settings (single `wp_options` row, autoload disabled)

No data is sent to external servers. No telemetry or analytics. No external API calls.

## Disclosure Policy

We follow coordinated disclosure:

1. Reporter notifies us privately
2. We acknowledge and investigate
3. We develop and test a fix
4. We release the fix
5. We publish a security advisory
6. Reporter is credited (unless they prefer anonymity)

We ask that reporters give us 90 days from the date of our acknowledgment before public disclosure.
