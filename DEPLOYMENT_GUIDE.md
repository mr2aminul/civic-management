# CRM Automation - Quick Deployment Guide

## 🚀 5-Minute Deployment

### Step 1: Install Dependencies (2 mins)
```bash
cd "c:\Users\Aminul Islam\Desktop\GitHub\civic-management"
composer require phpmailer/phpmailer tecnickcom/tcpdf phpoffice/phpspreadsheet
```

### Step 2: Database Migration (1 min)
```bash
# Run the automation schema
mysql -u USERNAME -p DATABASE_NAME < database/01_AUTOMATION_ADDITIONS.sql

# Or via phpMyAdmin:
# 1. Open phpMyAdmin
# 2. Select your database
# 3. Click "Import"
# 4. Choose file: database/01_AUTOMATION_ADDITIONS.sql
# 5. Click "Go"
```

### Step 3: Configure SMTP (30 seconds)
```sql
-- Run in phpMyAdmin or MySQL client
INSERT INTO wo_config (name, value) VALUES
('smtp_host', 'smtp.gmail.com'),
('smtp_port', '587'),
('smtp_username', 'your-email@gmail.com'),
('smtp_password', 'your-app-password'),
('smtp_from_email', 'noreply@civicgroupbd.com'),
('smtp_from_name', 'Civic Group BD')
ON DUPLICATE KEY UPDATE value=VALUES(value);
```

**For Gmail:**
1. Enable 2-factor authentication
2. Generate App Password: https://myaccount.google.com/apppasswords
3. Use the generated password (not your regular password)

### Step 4: Test Invoice Creation (30 seconds)
```javascript
// In browser console on your CRM:
fetch('/xhr/manage_inventory/invoices.php?s=create_invoice', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'purchase_id=1&amount=50000&invoice_type=installment&due_date=2025-12-31'
}).then(r => r.json()).then(console.log);

// Expected response:
// {
//   "status": 200,
//   "invoice_id": 123,
//   "pdf_generated": true,    ← Should be TRUE
//   "email_queued": true       ← Should be TRUE
// }
```

### Step 5: Verify PDF Storage (30 seconds)
```bash
# Check if PDF was created
# Navigate to: storage/client_docs/{client_id}/Purchases/{file_num}/Invoices/
# You should see: Invoice_INV-202511-XXXXX.pdf
```

### Step 6: Setup Cron Job (30 seconds)
```bash
# Edit crontab
crontab -e

# Add this line (runs every 5 minutes):
*/5 * * * * cd /path/to/civic-management && php cron-job.php >> /tmp/crm-cron.log 2>&1

# Save and exit
# Test immediately:
php cron-job.php
```

---

## ✅ Verification Checklist

### Database Tables Created
```sql
-- Check if tables exist:
SHOW TABLES LIKE 'crm_%';
SHOW TABLES LIKE 'fm_%';

-- Should see:
-- crm_email_queue
-- crm_sms_queue
-- crm_email_logs
-- crm_documents
-- crm_reminders
-- fm_recycle_bin
-- ... and more
```

### Email Queue Working
```sql
-- Check email queue
SELECT COUNT(*) FROM crm_email_queue WHERE status = 'queued';

-- Check email logs (after cron runs)
SELECT * FROM crm_email_logs ORDER BY sent_at DESC LIMIT 5;
```

### PDF Generation Working
```sql
-- Check generated documents
SELECT * FROM crm_documents ORDER BY generated_at DESC LIMIT 10;

-- Check file manager entries
SELECT * FROM fm_files WHERE is_folder = 0 ORDER BY created_at DESC LIMIT 10;
```

### Cron Jobs Running
```bash
# Check cron log
tail -f /tmp/crm-cron.log

# Should see output like:
# ========== CRM Automation Started ==========
# [Email Queue] Processing...
# [Email Queue] Sent 5 emails
# [SMS Queue] Processing...
# [SMS Queue] Sent 3 SMS
# ... etc
```

---

## 🧪 Test Scenarios

### Test 1: Invoice Creation with PDF
```
1. Create an invoice via UI or API
2. Check response: pdf_generated = true
3. Navigate to storage/client_docs/{client_id}/Purchases/{file_num}/Invoices/
4. Verify PDF exists
5. Check crm_email_queue for queued email
6. Run cron: php cron-job.php
7. Check crm_email_logs for sent email
```

### Test 2: Payment Recording with Receipt
```
1. Record a payment via UI or API
2. Check response: receipt_pdf_generated = true
3. Navigate to storage/client_docs/{client_id}/Purchases/{file_num}/Receipts/
4. Verify receipt PDF exists
5. Check crm_email_queue for queued email
6. Run cron to send email
```

### Test 3: Completion Certificate
```
1. Record final payment (make all schedules paid)
2. Check response: certificate_generated = true
3. Navigate to storage/client_docs/{client_id}/Purchases/{file_num}/Certificates/
4. Verify certificate PDF exists
5. Check both crm_email_queue AND crm_sms_queue
6. Run cron to send congratulations
```

### Test 4: Payment Reminders
```
1. Create payment schedule with due date in 7 days
2. Wait for daily cron or run: php cron/send_payment_reminders.php
3. Check crm_email_queue for reminder_7_days email
4. Check crm_sms_queue for SMS
5. Verify crm_reminders table has entry
```

---

## 🔧 Troubleshooting

### Issue: PDF not generating
**Check:**
```bash
# 1. TCPDF installed?
composer show tecnickcom/tcpdf

# 2. Storage directory writable?
ls -la storage
chmod -R 755 storage

# 3. Check PHP errors
tail -f /var/log/php_errors.log
```

### Issue: Emails not sending
**Check:**
```sql
-- 1. SMTP configured?
SELECT * FROM wo_config WHERE name LIKE 'smtp%';

-- 2. Emails queued?
SELECT COUNT(*) FROM crm_email_queue WHERE status = 'queued';

-- 3. Check errors
SELECT * FROM crm_email_logs WHERE status = 'failed' ORDER BY sent_at DESC LIMIT 10;
```

### Issue: Cron not running
**Check:**
```bash
# 1. Cron service running?
service cron status

# 2. Crontab configured?
crontab -l

# 3. Test manually
cd /path/to/civic-management
php cron-job.php

# 4. Check permissions
ls -la cron-job.php
chmod +x cron-job.php
```

---

## 📞 Support Checklist

### Before Asking for Help
1. ✅ Dependencies installed? (`composer show`)
2. ✅ Database migrated? (check tables exist)
3. ✅ SMTP configured? (check wo_config)
4. ✅ Cron job added? (`crontab -l`)
5. ✅ Checked error logs? (`tail -f error.log`)
6. ✅ Tested manually? (`php cron-job.php`)

### Provide This Information
- PHP version: `php -v`
- Composer packages: `composer show`
- Database tables: `SHOW TABLES LIKE 'crm_%'`
- Recent logs: Last 20 lines from cron log
- Error messages: Exact error text

---

## 🎯 Next Steps After Deployment

1. **Monitor for 24 hours**
   - Check cron logs daily
   - Verify emails sending
   - Check PDF generation

2. **Create test data**
   - Create test invoices
   - Record test payments
   - Verify automations

3. **Train users**
   - Show new features
   - Explain automation
   - Demonstrate PDFs

4. **Implement remaining features**
   - Excel schedule generation
   - File browser UI
   - Plot hold UI
   - Refund system

---

## 🚀 You're Ready to Go!

The system is production-ready. All core automation is working. Deploy with confidence! 🎉
