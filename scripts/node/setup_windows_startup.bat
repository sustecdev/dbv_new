@echo off
echo Setting up Windows Task Scheduler for DBV Stellar Worker...
echo.

REM Check if running as administrator
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo This script needs to run as Administrator!
    echo Please right-click and select "Run as administrator"
    pause
    exit /b 1
)

REM Create task to run PM2 start on system startup
echo Creating scheduled task...
schtasks /create /tn "DBV Stellar Worker" /tr "pm2 resurrect" /sc onstart /ru SYSTEM /f >nul 2>&1

if %errorlevel% equ 0 (
    echo.
    echo Task created successfully!
    echo The worker will start automatically when Windows boots.
    echo.
    echo Note: You must run 'pm2 save' after starting the worker for this to work.
    echo.
) else (
    echo.
    echo Failed to create task. Please create it manually:
    echo.
    echo Task Name: DBV Stellar Worker
    echo Trigger: On System Startup
    echo Action: pm2 resurrect
    echo Run as: SYSTEM
    echo.
)

pause

