# Run the full anti-cheat stack locally
# Usage (from repo root): .\scripts\run-dev-stack.ps1

$ErrorActionPreference = "Stop"
$Root = Split-Path $PSScriptRoot -Parent
$Python = "C:\Users\Mahmoud\AppData\Local\Programs\Python\Python314\python.exe"
if (-not (Test-Path $Python)) {
    $Python = (Get-Command python -ErrorAction SilentlyContinue).Source
}

Write-Host "=== Anti-Cheat Dev Stack ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "1) AI service:  http://127.0.0.1:8001  (GET /health, POST /frame)"
Write-Host "2) Laravel API: http://127.0.0.1:8000  (POST /api/exam/check-frame)"
Write-Host ""
Write-Host "Open TWO terminals and run:"
Write-Host "  Terminal 1: .\scripts\start-ai-service.ps1"
Write-Host "  Terminal 2: php artisan serve"
Write-Host ""
Write-Host "Then verify:"
Write-Host "  php scripts/test-live-integration.php"
Write-Host ""

if (-not $Python) {
    Write-Warning "Python not found. Install Python 3.12+ before starting the AI service."
    exit 1
}

$health = $null
try {
    $health = Invoke-WebRequest -Uri "http://127.0.0.1:8001/health" -UseBasicParsing -TimeoutSec 2
} catch {}

if ($health) {
    Write-Host "FastAPI already running: $($health.Content)" -ForegroundColor Green
} else {
    Write-Host "FastAPI is not running. Start it with .\scripts\start-ai-service.ps1" -ForegroundColor Yellow
}

$laravel = $null
try {
    $laravel = Invoke-WebRequest -Uri "http://127.0.0.1:8000/up" -UseBasicParsing -TimeoutSec 2
} catch {}

if ($laravel) {
    Write-Host "Laravel already running." -ForegroundColor Green
} else {
    Write-Host "Laravel is not running. Start it with: php artisan serve" -ForegroundColor Yellow
}

if ($health -and $laravel) {
    Write-Host ""
    Write-Host "Running live integration test..." -ForegroundColor Cyan
    Set-Location $Root
    php scripts/test-live-integration.php
}
