<#
    Builds a copy that carries its own .NET runtime, for a server where the
    ASP.NET Core Hosting Bundle cannot be installed.

    Needs the .NET 8 SDK on the machine you run this on - not on the server.
    Run it from the root of the package (the folder with src\ and deploy\).
#>
[CmdletBinding()]
param(
    [string]$Output = "publish-self-contained",
    [ValidateSet("win-x64", "win-x86", "win-arm64", "linux-x64")]
    [string]$Runtime = "win-x64"
)

$ErrorActionPreference = "Stop"
$project = Join-Path $PSScriptRoot "..\src\DigitalControlTower.Web\DigitalControlTower.Web.csproj"

dotnet publish $project -c Release -r $Runtime --self-contained true -o $Output

Write-Host ""
Write-Host "Done: $Output" -ForegroundColor Green
Write-Host "Copy that folder to the server and run DigitalControlTower.Web.exe --urls http://0.0.0.0:8080"
Write-Host "No runtime needs installing there."
