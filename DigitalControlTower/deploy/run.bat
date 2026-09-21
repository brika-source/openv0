@echo off
REM Starts the Digital Control Tower in this window. Close the window to stop it.
REM Needs the ASP.NET Core 8 Hosting Bundle (or use the self-contained build).
setlocal
set ASPNETCORE_ENVIRONMENT=Production
cd /d "%~dp0app"
echo Starting the Digital Control Tower on http://localhost:8080 ...
dotnet DigitalControlTower.Web.dll --urls http://0.0.0.0:8080
pause
