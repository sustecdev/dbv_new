# DBV Stellar Withdrawal Worker

This worker automatically processes pending Stellar withdrawals every 60 seconds.

## Quick Start

### Option 1: Using PM2 (Recommended)

1. **Double-click `start_worker.bat`** - This will:
   - Install PM2 if needed
   - Start the worker
   - Configure it to run on system startup

2. **Check status:**
   ```bash
   pm2 status
   ```

3. **View logs:**
   ```bash
   pm2 logs dbv-stellar-worker
   ```

### Option 2: Manual PM2 Start

```bash
cd C:\xampp\htdocs\dbnew
pm2 start scripts/node/ecosystem.config.js
pm2 save
pm2 startup
```

### Option 3: Run Directly (for testing)

```bash
cd C:\xampp\htdocs\dbnew
node scripts/node/withdrawal_worker_improved.js
```

## PM2 Commands

- **Start:** `pm2 start dbv-stellar-worker`
- **Stop:** `pm2 stop dbv-stellar-worker`
- **Restart:** `pm2 restart dbv-stellar-worker`
- **Status:** `pm2 status`
- **Logs:** `pm2 logs dbv-stellar-worker`
- **Delete:** `pm2 delete dbv-stellar-worker`

## Worker Behavior

- Checks for pending withdrawals every **60 seconds**
- Processes withdrawals with `status=0` and `trustline=1`
- Automatically updates status to:
  - `8` (Pre-complete) when starting
  - `3` (Completed) when successful
  - `2` (Failed) when transaction fails

## Logs

Logs are saved to:
- `logs/pm2-out.log` - Standard output
- `logs/pm2-error.log` - Errors

## Auto-Start on Boot

After running `pm2 save` and `pm2 startup`, the worker will automatically start when Windows boots.

