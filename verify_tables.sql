-- Quick fix SQL to check all table names
-- Run this to verify your actual table structure

SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    CREATE_TIME
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME IN (
    'wo_booking',
    'wo_booking_helper',
    'crm_customers',
    'crm_invoices',
    'crm_payment_credits',
    'crm_pending_changes',
    'crm_email_queue'
)
ORDER BY TABLE_NAME;

-- If wo_booking_helper doesn't exist but crm_booking_helper does:
-- You may need to update all references

-- If table names are different, get actual names:
SELECT TABLE_NAME 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE()
AND (TABLE_NAME LIKE '%booking%' OR TABLE_NAME LIKE '%customer%')
ORDER BY TABLE_NAME;
