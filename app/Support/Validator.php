<?php

/**
 * Input validation helper
 */
class Validator
{
    public static function validateWithdrawal(array $data): array
    {
        $errors = [];
        
        // Amount validation
        $amount = $data['amount'] ?? 0;
        if (empty($amount) || !is_numeric($amount)) {
            $errors['amount'] = 'Amount must be a valid number';
        } elseif ($amount <= 0) {
            $errors['amount'] = 'Amount must be greater than 0';
        } elseif ($amount > 5000000) {
            $errors['amount'] = 'Amount exceeds maximum limit of 5,000,000 DBV';
        }
        
        // Address validation
        $address = trim($data['address'] ?? '');
        if (empty($address)) {
            $errors['address'] = 'Stellar address is required';
        } elseif (!Security::isValidStellarAddress($address)) {
            $errors['address'] = 'Invalid Stellar address format';
        }
        
        return $errors;
    }
    
    public static function validateDeposit(array $data): array
    {
        $errors = [];
        
        $txnHash = trim($data['txn_hash'] ?? '');
        if (empty($txnHash)) {
            $errors['txn_hash'] = 'Transaction hash is required';
        } elseif (!Security::isValidTxnHash($txnHash)) {
            $errors['txn_hash'] = 'Invalid transaction hash format (must be 64 characters)';
        }
        
        return $errors;
    }
}

