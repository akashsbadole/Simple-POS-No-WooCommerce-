<?php
// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Dev-only CLI test runner; never shipped in the release zip.
/**
 * Simple POS — Test Runner
 *
 * Usage: php run-tests.php
 *
 * Runs the full PHPUnit suite (tests/Unit) when PHPUnit is installed,
 * otherwise falls back to a small standalone smoke test that needs no
 * dependencies.
 */

$phpunit = __DIR__ . '/vendor/bin/phpunit' . ( 0 === stripos( PHP_OS, 'WIN' ) ? '.bat' : '' );

if ( is_file( $phpunit ) ) {
    // Canonical path: entire suite, including checkout/tax/void/stock
    // regression coverage (Test_Pos_Checkout).
    passthru( escapeshellarg( $phpunit ) . ' --colors=never', $code );
    exit( is_int( $code ) ? $code : 1 );
}

// --- Fallback: dependency-free smoke test -------------------------------
require_once __DIR__ . '/tests/bootstrap.php';
require_once __DIR__ . '/tests/Unit/Pos_DBTest.php';

echo "=== Simple POS Unit Tests (fallback smoke test; PHPUnit not installed) ===" . PHP_EOL . PHP_EOL;

$tests = new Test_Pos_DB();
$pass  = 0;
$fail  = 0;

$methods = array(
    'test_table_method_sanitizes_prefix',
    'test_table_method_output_format',
    'test_format_currency_non_empty',
    'test_format_currency_zero',
    'test_sku_exists_returns_bool',
    'test_settings_get_all_returns_array',
    'test_settings_get_with_default',
    'test_constants_defined',
    'test_wp_error_stub',
    'test_wp_error_no_errors',
);

foreach ( $methods as $method ) {
    try {
        $tests->$method();
        echo "  PASS: $method" . PHP_EOL;
        $pass++;
    } catch ( \Throwable $e ) {
        echo "  FAIL: $method — " . $e->getMessage() . PHP_EOL;
        $fail++;
    }
}

echo PHP_EOL . "Results: $pass passed, $fail failed, " . ( $pass + $fail ) . " total" . PHP_EOL;
exit( $fail > 0 ? 1 : 0 );
