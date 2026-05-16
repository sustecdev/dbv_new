# Quick Start - Automatic Withdrawal Worker

The withdrawal worker is now **automatically running** and will process withdrawals every 60 seconds!

## Current Status

✅ **Worker is running** - Checked every 60 seconds for pending withdrawals

## How to Use

### Check Worker Status
```bash
pm2 status
```

### View Live Logs
```bash
pm2 logs dbv-stellar-worker
```

### Stop Worker
```bash
pm2 stop dbv-stellar-worker
```

### Start Worker
```bash
pm2 start dbv-stellar-worker
```

### Restart Worker
```bash
pm2 restart dbv-stellar-worker
```

## Setup Auto-Start on Boot

The worker is saved but won't auto-start on boot. To enable:

1. **Option A: Use Task Scheduler (Recommended for Windows)**
   - Right-click `setup_windows_startup.bat` → Run as Administrator
   - This creates a Windows task to start PM2 on boot

2. **Option B: Add to Windows Startup**
   - Press `Win+R`, type `shell:startup`, press Enter
   - Create a shortcut to: `C:\xampp\htdocs\dbnew\scripts\node\start_worker.bat`

## How It Works

1. Worker checks database every **60 seconds**
2. Finds withdrawals with `status=0` and `trustline=1`
3. Processes them on Stellar mainnet
4. Updates status to:
   - `8` = Pre-complete (processing)
   - `3` = Completed (success)
   - `2` = Failed (error)

## Logs

Logs are saved to:
- `logs/pm2-out.log` - All output
- `logs/pm2-error.log` - Errors only

## Troubleshooting

**Worker not running?**
```bash
pm2 restart dbv-stellar-worker
```

**Check what went wrong?**
```bash
pm2 logs dbv-stellar-worker --err
```

**Worker keeps crashing?**
```bash
pm2 logs dbv-stellar-worker --lines 50
```

