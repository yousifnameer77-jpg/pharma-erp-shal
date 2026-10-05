@echo off
title Pharma ERP - System Launcher
color 0A
echo ===================================================
echo           PHARMA ERP - SYSTEM LAUNCHER
echo ===================================================
echo.

set PHP_BIN=php
where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    if exist "%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" (
        set "PHP_BIN=%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
    )
)

echo [1/3] Starting Laravel Backend API on http://localhost:8000 ...
start "Pharma ERP - Backend API" cmd /k "cd /d %~dp0backend && \"%PHP_BIN%\" artisan serve --port=8000"

echo [2/3] Starting Next.js Frontend UI on http://localhost:3000 ...
start "Pharma ERP - Frontend UI" cmd /k "cd /d %~dp0frontend && npm run start"

echo [3/3] Opening browser in 3 seconds...
timeout /t 3 /nobreak >nul
start http://localhost:3000

echo.
echo ===================================================
echo  System started successfully!
echo  Backend:  http://localhost:8000/api/v1
echo  Frontend: http://localhost:3000
echo.
echo  Default Login:
echo  Username: admin
echo  Password: Passw0rd!
echo ===================================================
echo Press any key to close this launcher window...
pause >nul
