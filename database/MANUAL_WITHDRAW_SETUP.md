# Manual Withdraw Feature - Setup

## Overview
When **Manual Withdraw Mode** is enabled (via Admin Dashboard → Settings), new withdrawals stay **pending** until an admin processes them manually. The worker will not auto-process these withdrawals.

**User-facing message** (when manual mode is ON):  
*"Your withdrawal request is being processed. The process can take up to 24 hours to complete."*

## Setup Steps

### 1. Run database migrations
Execute in order:

```sql
-- Create app_settings table (stores the toggle)
SOURCE create_app_settings.sql;

-- Add is_manual column to withdraw tables
SOURCE add_is_manual_column.sql;

-- Add processed_by_admin_uid (shows which admin manually completed each withdrawal)
SOURCE add_processed_by_admin_uid.sql;
```

Or via PHP migration (idempotent):
```bash
php database/add_processed_by_admin_migration.php
```

Or via mysql client:
```bash
mysql -u root -p Digital < database/create_app_settings.sql
mysql -u root -p Digital < database/add_is_manual_column.sql
mysql -u root -p Digital < database/add_processed_by_admin_uid.sql
```

### 2. Admin toggle
1. Log in as admin (UID: 1290033)
2. Go to **Admin Dashboard** → **Settings** tab
3. Toggle **Manual Withdraw Mode** ON or OFF
4. Changes take effect immediately for new withdrawals

### 3. Processing manual withdrawals (Mark Complete)
When manual mode is ON, new withdrawals have `is_manual = 1` and `status = 0` (pending).

1. Go to **Admin Dashboard** → **Transactions** tab
2. Filter or find pending withdrawals (status = Pending, no network hash)
3. Click **"Mark Complete"** on the withdrawal row
4. In the modal, paste the **transaction hash** from the blockchain explorer (Stellar/BSCScan/Etherscan)
5. Click **Submit**
6. The system verifies the tx on-chain (recipient address + amount must match) before updating. If verification fails, an error is shown.

**Verification:** Before marking complete, the backend confirms that the transaction sent the correct amount to the correct withdrawal address from the vault. Wrong address or amount will be rejected.

**Processed by:** When an admin marks a withdrawal complete, their UID is stored in `processed_by_admin_uid` and displayed on the transaction in the admin dashboard.

## Fallback
If `app_settings` table doesn't exist, config falls back to `MANUAL_WITHDRAW_ENABLED` in `.env` (default: false).
