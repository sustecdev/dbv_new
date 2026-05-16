-- Migration: Update amount columns to 2 decimal places
-- Run this script to update existing tables

-- Update stellar_deposit table
ALTER TABLE stellar_deposit MODIFY COLUMN amount DECIMAL(32,2) NOT NULL DEFAULT 0;

-- Update stellar_withdraw table
ALTER TABLE stellar_withdraw MODIFY COLUMN amount DECIMAL(32,2) NOT NULL DEFAULT 0;

-- Round existing data to 2 decimals
UPDATE stellar_deposit SET amount = ROUND(amount, 2);
UPDATE stellar_withdraw SET amount = ROUND(amount, 2);

