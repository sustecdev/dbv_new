@echo off
echo Starting DBV Stellar Withdrawal Worker...
echo.

REM Check if PM2 is installed
pm2 --version >nul 2>&1
if %errorlevel% neq 0 (
    echo PM2 is not installed. Installing PM2...
    npm install -g pm2
    if %errorlevel% neq 0 (
        echo Failed to install PM2. Please install it manually: npm install -g pm2
        pause
        exit /b 1
    )
)

REM Navigate to project directory
cd /d C:\xampp\htdocs\dbnew

REM Create logs directory if it doesn't exist
if not exist "logs" mkdir logs

REM Stop existing worker if running
pm2 stop dbv-stellar-worker 2>nul
pm2 delete dbv-stellar-worker 2>nul

REM Start the worker using PM2
echo Starting worker with PM2...
pm2 start ecosystem.config.cjs

REM Save PM2 configuration so it starts on system boot
pm2 save

REM Setup PM2 to start on Windows startup
pm2 startup
echo.
echo Worker started successfully!
echo.
echo Commands:
echo   pm2 status           - Check worker status
echo   pm2 logs dbv-stellar-worker - View logs
echo   pm2 stop dbv-stellar-worker - Stop worker
echo   pm2 restart dbv-stellar-worker - Restart worker
echo.
pause

