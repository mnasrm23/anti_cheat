# Start the Anti-Cheat FastAPI service
# Usage (from repo root): .\scripts\start-ai-service.ps1

$ErrorActionPreference = "Stop"
$Root = Split-Path $PSScriptRoot -Parent
$AiDir = Join-Path $Root "Anti_Cheat_System"
$Python = "C:\Users\Mahmoud\AppData\Local\Programs\Python\Python314\python.exe"

if (-not (Test-Path $Python)) {
    $Python = (Get-Command python -ErrorAction SilentlyContinue).Source
}
if (-not $Python) {
    Write-Error "Python not found. Install Python 3.12+ and ensure it is on PATH."
}

Set-Location $AiDir

$Requirements = Join-Path $AiDir "requirements.txt"
if (-not (Test-Path $Requirements)) {
    $Requirements = Join-Path $PSScriptRoot "ai-requirements.txt"
}

Write-Host "Installing Python dependencies from $Requirements ..."
& $Python -m pip install -r $Requirements

Write-Host "Starting FastAPI on http://127.0.0.1:8001 ..."
& $Python -m uvicorn backend.api.main:app --host 127.0.0.1 --port 8001
