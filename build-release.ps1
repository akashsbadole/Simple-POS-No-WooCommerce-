<#
.SYNOPSIS
    Builds a WordPress.org / ThemeForest release zip for Simple POS.
.DESCRIPTION
    Creates a clean zip containing only runtime plugin files — no .git,
    vendor (test-only), README.md (GitHub), build artifacts, or test caches.
    Output: release\simple-pos-{version}.zip
.EXAMPLE
    .\build-release.ps1
    .\build-release.ps1 -Version "2.1.1" -OutputDir ".\dist"
#>
[CmdletBinding()]
param(
    [string]$Version,
    [string]$OutputDir = 'release',
    [switch]$OpenFolder
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# Resolve plugin root (same directory as this script).
$PluginRoot = Split-Path -Parent $MyInvocation.MyCommand.Definition

# ── Determine version ────────────────────────────────────────────────
if (-not $Version) {
    $readme = Join-Path $PluginRoot 'readme.txt'
    if (-not (Test-Path $readme)) { throw "readme.txt not found at $readme" }
    $tag = (Get-Content $readme | Select-String -Pattern 'Stable tag:\s+(.+)').Matches[0].Groups[1].Value.Trim()
    if (-not $tag) { throw "Could not parse Stable tag from readme.txt" }
    $Version = $tag
}

Write-Host "Building simple-pos $Version ..." -ForegroundColor Cyan

# ── Prepare staging area ─────────────────────────────────────────────
$OutputDir  = Join-Path $PluginRoot $OutputDir
$StageRoot  = Join-Path $env:TEMP  "pos-release-build-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
$StagePlugin = Join-Path $StageRoot 'simple-pos'

if (-not (Test-Path $OutputDir)) { New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null }
if (-not (Test-Path $StagePlugin)) { New-Item -ItemType Directory -Path $StagePlugin -Force | Out-Null }

# ── Exclusion patterns (applied at any depth by robocopy) ────────────
$VendorDir = Join-Path $PluginRoot 'vendor'
$ExcludeDirs = @(
    '.git',
    '.github',
    '.kilo',
    $VendorDir,
    'node_modules',
    'release',         # output directory
    'tests',           # unit tests / self-checks
    'dist',            # build artifacts
    'simple-pos-example',  # developer template add-on, not shipped
    'docs'             # marketplace documentation, kept out of the runtime zip
)

$ExcludeFiles = @(
    'README.md',       # GitHub/docs-only; WP.org uses readme.txt
    'composer.json',
    'composer.lock',
    '.gitignore',
    '.distignore',
    '.phpunit.result.cache',
    'build-release.ps1',
    'run-tests.php',   # developer test runner
    'phpunit.xml',
    'phpcs.xml',
    'SECURITY.md',
    'COMPREHENSIVE-AUDIT-REPORT.md',
    'MANUAL-TEST-CASES.md',
    'PRE-RELEASE-AUDIT.md',
    'RE-VERIFICATION-REPORT.md',
    '*.log'
)

# ── Copy files via robocopy (recursive, exclusion-aware) ─────────────
$rcArgs = @($PluginRoot, $StagePlugin, '/E')

foreach ($dir in $ExcludeDirs) { $rcArgs += '/XD', $dir }
foreach ($file in $ExcludeFiles) { $rcArgs += '/XF', $file }

$rcArgs += @('/NFL', '/NDL', '/NJH', '/NJS', '/NP')

& robocopy @rcArgs | Out-Null

# robocopy exit codes: 0-7 are success (1 = files copied).
if ($LASTEXITCODE -ge 8) {
    throw "robocopy failed with exit code $LASTEXITCODE"
}

# ── Verify required files ───────────────────────────────────────────
$required = @('wp-pos-plugin.php', 'readme.txt', 'LICENSE.txt')
foreach ($f in $required) {
    if (-not (Test-Path (Join-Path $StagePlugin $f))) {
        throw "Required file missing from staging: $f"
    }
}

# ── Create zip ───────────────────────────────────────────────────────
$zipName = "simple-pos-$Version.zip"
$zipPath = Join-Path $OutputDir $zipName

if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory(
    $StageRoot,
    $zipPath,
    [System.IO.Compression.CompressionLevel]::Optimal,
    $false   # includeBaseDirectory: false (staging root is the base)
)

# ── Cleanup staging ──────────────────────────────────────────────────
Remove-Item -Path $StageRoot -Recurse -Force -ErrorAction SilentlyContinue

# ── Report ───────────────────────────────────────────────────────────
$zipItem = Get-Item $zipPath
$sizeMB  = [math]::Round($zipItem.Length / 1MB, 2)

Write-Host ""
Write-Host "Package ready" -ForegroundColor Green
Write-Host "   $zipPath"
Write-Host ("   {0} MB  ({1} bytes)" -f $sizeMB, $zipItem.Length)
Write-Host ""

$entries = $null
$zipRead = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
try {
    $entries = @($zipRead.Entries | Select-Object -First 40 -ExpandProperty FullName)
} finally {
    $zipRead.Dispose()
}

Write-Host "Contents:" -ForegroundColor DarkGray
foreach ($entry in $entries) {
    Write-Host "   $entry" -ForegroundColor DarkGray
}

if ($OpenFolder) { explorer.exe (Split-Path $zipPath) }
