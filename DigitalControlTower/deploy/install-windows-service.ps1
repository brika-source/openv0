<#
    Installs the Digital Control Tower as a Windows service.
    Run from an elevated PowerShell in the folder that contains app\.

        .\install-windows-service.ps1 -InstallPath "C:\Apps\DigitalControlTower" -Port 8080
#>
[CmdletBinding()]
param(
    [string]$InstallPath = "C:\Apps\DigitalControlTower",
    [int]$Port = 8080,
    [string]$ServiceName = "DigitalControlTower",
    [string]$DisplayName = "Digital Control Tower"
)

$ErrorActionPreference = "Stop"
$source = Join-Path $PSScriptRoot "app"

if (-not (Test-Path $source)) { throw "app\ not found next to this script." }

if (Get-Service -Name $ServiceName -ErrorAction SilentlyContinue) {
    Write-Host "Stopping the existing service..."
    Stop-Service -Name $ServiceName -Force -ErrorAction SilentlyContinue
    sc.exe delete $ServiceName | Out-Null
    Start-Sleep -Seconds 2
}

Write-Host "Copying the application to $InstallPath..."
New-Item -ItemType Directory -Path $InstallPath -Force | Out-Null

# Keep the settings that are already on the server; everything else is replaced.
$settings = Join-Path $InstallPath "appsettings.Production.json"
$keep = $null
if (Test-Path $settings) { $keep = Get-Content $settings -Raw }
Copy-Item -Path (Join-Path $source "*") -Destination $InstallPath -Recurse -Force
if ($keep) { Set-Content -Path $settings -Value $keep -Encoding UTF8 }

$exe = Join-Path $InstallPath "DigitalControlTower.Web.exe"
if (-not (Test-Path $exe)) { throw "DigitalControlTower.Web.exe not found in $InstallPath." }

Write-Host "Registering the service..."
$binPath = '"{0}" --urls "http://0.0.0.0:{1}"' -f $exe, $Port
sc.exe create $ServiceName binPath= $binPath start= auto DisplayName= $DisplayName | Out-Null
sc.exe description $ServiceName "90-day plan, meetings action plan, value reporting and due-date reminders." | Out-Null

Write-Host "Starting the service..."
Start-Service -Name $ServiceName

Write-Host ""
Write-Host "Installed. Open http://$env:COMPUTERNAME`:$Port" -ForegroundColor Green
Write-Host "Set the connection string in $settings, then: Restart-Service $ServiceName"
