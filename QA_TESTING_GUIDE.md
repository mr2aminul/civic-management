# Quality Assurance & Testing Guide

## Complete Payment & Invoice Management System

### Test Environment Setup

#### Prerequisites
1. Database tables created (run migration script)
2. Backend API modules in `/xhr/manage_inventory/` directory
3. Frontend tab pages in `/manage/pages/clients/tabs/` directory
4. Email templates in `/manage/pages/clients/emails/` directory
5. Modals in `/manage/pages/clients/modals/` directory

#### Database Verification
\`\`\`sql
-- Verify all required tables exist
SHOW TABLES LIKE 'crm_%';

-- Expected tables:
-- crm_invoices
-- crm_money_receipts
-- crm_overpayment_credits
-- crm_audit_trail
-- crm_merge_requests
-- crm_email_queue
-- crm_email_logs
\`\`\`

---

## Unit Testing

### 1. Invoice Management Module

#### Test 1.1: Create Invoice
- **Purpose**: Verify invoice creation with correct data
- **Steps**:
  1. Call `/xhr/manage_inventory.php?s=create_invoice`
  2. POST data: `purchase_id=1, invoice_type=installment, amount=50000, due_date=2025-12-15`
  3. Verify response status 200
  4. Verify invoice_number format: `INV-YYYYMM-00001`
  5. Check database: invoice exists with correct values
  6. Check audit_trail: entry logged

- **Expected Result**: Invoice created, receipt returned with invoice_number

#### Test 1.2: Get Invoices
- **Purpose**: Retrieve all invoices for a purchase
- **Steps**:
  1. Create 3 invoices for purchase ID 1
  2. Call `/xhr/manage_inventory.php?s=get_invoices&purchase_id=1`
  3. Verify all 3 invoices returned
  4. Verify fields: invoice_number, type, amount, paid_amount, status
  5. Verify status values: pending, partial, paid

- **Expected Result**: Array of invoices with correct structure

#### Test 1.3: Record Payment
- **Purpose**: Record payment against invoice with overpayment detection
- **Steps**:
  1. Create invoice for ৳50,000
  2. Record payment of ৳75,000 (overpayment ৳25,000)
  3. Verify response: receipt_number returned, overpayment_credit = 25000
  4. Check crm_money_receipts: receipt created
  5. Check crm_overpayment_credits: credit created with status='active'
  6. Check crm_invoices: status changed to 'paid'
  7. Check audit_trail: payment entry logged with before/after values

- **Expected Result**: Payment recorded, credit created, audit logged

#### Test 1.4: Multi-Invoice Payment
- **Purpose**: Apply payment across multiple invoices
- **Steps**:
  1. Create 3 invoices: ৳25,000 + ৳25,000 + ৳25,000 = ৳75,000
  2. Record payment ৳75,000 against all 3 invoice IDs
  3. Verify all 3 invoices marked as 'paid'
  4. Verify no overpayment credit created
  5. Verify receipt covers all 3 invoices

- **Expected Result**: All invoices marked paid, payment distributed correctly

#### Test 1.5: Get Credits
- **Purpose**: Retrieve overpayment credits for purchase
- **Steps**:
  1. Create invoice ৳50,000, pay ৳75,000 (credit ৳25,000)
  2. Call `/xhr/manage_inventory.php?s=get_credits&purchase_id=1`
  3. Verify credit returned with: credit_amount=25000, remaining_credit=25000, status='active'
  4. Filter by status: verify only 'active' credits shown

- **Expected Result**: Credit array with correct structure

---

### 2. Audit Trail Module

#### Test 2.1: Get Audit Trail
- **Purpose**: Retrieve audit logs with filtering
- **Steps**:
  1. Create invoice (logs 'create' action)
  2. Record payment (logs 'create' action)
  3. Call `/xhr/manage_inventory.php?s=get_audit_trail&purchase_id=1`
  4. Verify minimum 2 entries returned
  5. Test filters: category='payment', action='create'
  6. Verify pagination works (page parameter)

- **Expected Result**: Audit entries with correct timestamps, user, IP address

#### Test 2.2: Get Audit Detail
- **Purpose**: Retrieve detailed before/after values
- **Steps**:
  1. Create audit trail entry
  2. Call `/xhr/manage_inventory.php?s=get_audit_detail&audit_id=1`
  3. Verify before_value: JSON string
  4. Verify after_value: JSON string
  5. Parse JSON and verify structure

- **Expected Result**: Valid JSON before/after values

#### Test 2.3: Audit Trail Date Filtering
- **Purpose**: Filter audit logs by date range
- **Steps**:
  1. Create payment today
  2. Create payment with past date (manually insert)
  3. Call with date_from and date_to parameters
  4. Verify only entries in range returned

- **Expected Result**: Date filtering works correctly

---

### 3. Email Queue Module

#### Test 3.1: Queue Email
- **Purpose**: Queue email for sending
- **Steps**:
  1. Call `/xhr/manage_inventory.php?s=queue_email`
  2. POST: `purchase_id=1, recipient_email=client@example.com, email_type=payment_reminder, subject=Reminder, body=HTML`
  3. Verify email validation: reject invalid emails
  4. Verify response: queue_id returned
  5. Check crm_email_queue: entry created with status='pending'

- **Expected Result**: Email queued with status='pending'

#### Test 3.2: Send Email
- **Purpose**: Send queued email
- **Steps**:
  1. Queue email (see 3.1)
  2. Call `/xhr/manage_inventory.php?s=send_email&email_id=1` via POST
  3. Verify response: status 200
  4. Check crm_email_queue: status='sent', sent_date populated
  5. Check crm_email_logs: entry created with status='sent'

- **Expected Result**: Email marked sent, logged

#### Test 3.3: Send All Pending
- **Purpose**: Send all pending emails in batch
- **Steps**:
  1. Queue 3 emails
  2. Call `/xhr/manage_inventory.php?s=send_all_pending_emails` via POST
  3. Verify response: sent_count=3, failed_count=0
  4. Check all 3 emails: status='sent'

- **Expected Result**: All pending emails sent

#### Test 3.4: Get Pending Emails
- **Purpose**: Retrieve queued emails with filtering
- **Steps**:
  1. Queue: 2 pending, 1 sent
  2. Call `/xhr/manage_inventory.php?s=get_pending_emails&purchase_id=1&status=pending`
  3. Verify only 2 returned
  4. Filter by email_type
  5. Filter by recipient_type

- **Expected Result**: Filtered results correct

#### Test 3.5: Delete Queued Email
- **Purpose**: Remove email from queue
- **Steps**:
  1. Queue email
  2. Call `/xhr/manage_inventory.php?s=delete_queued_email&email_id=1` via POST
  3. Verify response: status 200
  4. Call get_pending_emails: verify email not returned

- **Expected Result**: Email deleted from queue

---

### 4. Merge Purchase Module

#### Test 4.1: Create Merge Request
- **Purpose**: Create purchase merge request
- **Steps**:
  1. Create 2 purchases for same client (ID 5)
  2. Call `/xhr/manage_inventory.php?s=create_merge_request`
  3. POST: `client_id=5, source_purchase_id=1, target_purchase_id=2`
  4. Verify response: merge_id returned
  5. Check crm_merge_requests: entry created with approval_status='pending'
  6. Verify ownership validation: reject if different clients

- **Expected Result**: Merge request created, pending approval

#### Test 4.2: Get Merge Requests
- **Purpose**: Retrieve merge requests
- **Steps**:
  1. Create merge request (pending)
  2. Call `/xhr/manage_inventory.php?s=get_merge_requests&status=pending`
  3. Verify merge request returned
  4. Filter by status: approved, rejected

- **Expected Result**: Merge requests filtered correctly

#### Test 4.3: Approve & Execute Merge
- **Purpose**: Approve merge and consolidate purchases
- **Steps**:
  1. Create merge request (merge_id=1)
  2. Create invoices for source (50,000) and target (75,000)
  3. Record payment: source paid 40,000, target paid 50,000
  4. Create credit: source has 5,000 overpayment
  5. Call `/xhr/manage_inventory.php?s=approve_merge` with merge_id=1 via POST
  6. Verify response: status 200
  7. Check crm_merge_requests: status='approved', approved_by set
  8. Check source invoices: transferred to target
  9. Check source credits: transferred to target
  10. Check wo_booking_helper: source status=5 (merged)
  11. Check audit_trail: merge entry logged

- **Expected Result**: All data transferred, source marked merged, audit logged

#### Test 4.4: Reject Merge
- **Purpose**: Reject merge request
- **Steps**:
  1. Create merge request (merge_id=2)
  2. Call `/xhr/manage_inventory.php?s=reject_merge` via POST
  3. POST: `merge_id=2, reason=Not approved`
  4. Verify response: status 200
  5. Check crm_merge_requests: status='rejected', rejection_reason set

- **Expected Result**: Merge rejected, reason logged

---

### 5. Frontend Tab Integration

#### Test 5.1: Invoices Tab Loading
- **Purpose**: Verify tab loads and displays invoices
- **Steps**:
  1. Navigate to client detail page
  2. Click "Invoices" tab
  3. Verify table loads with AJAX
  4. Verify columns: Invoice #, Date, Type, Amount, Paid, Balance, Status
  5. Create invoice, refresh: verify new invoice appears
  6. Verify status badges color-coded

- **Expected Result**: Tab loads, invoices displayed, AJAX works

#### Test 5.2: Payment Schedules Tab
- **Purpose**: Verify schedule tab functionality
- **Steps**:
  1. Click "Payment Schedules" tab
  2. Verify filter options: status, type, date range
  3. Apply filters, verify results update
  4. Click "Record Payment" button
  5. Modal opens with invoice selection

- **Expected Result**: Filters work, modal opens

#### Test 5.3: Pending Emails Tab
- **Purpose**: Verify email queue tab
- **Steps**:
  1. Queue 2 emails
  2. Click "Pending Emails" tab
  3. Verify emails displayed in table
  4. Click "Send All Pending": verify emails sent
  5. Verify status updates to "Sent"

- **Expected Result**: Emails queued, sent, status updated

#### Test 5.4: Audit Trail Tab
- **Purpose**: Verify audit trail tab
- **Steps**:
  1. Perform 3-5 actions (invoice, payment, schedule change)
  2. Click "Audit Trail" tab
  3. Verify timeline displays all actions
  4. Verify color-coding by category
  5. Click action to view detail
  6. Verify before/after values displayed

- **Expected Result**: Timeline shows all actions with correct details

---

## Integration Testing

### Test 6: Complete Payment Flow

- **Scenario**: Client pays in installments with overpayment and credit application

- **Steps**:
  1. Create purchase: total ৳1,00,000 (booking ৳10,000, down ৳20,000, remaining ৳70,000)
  2. Create 14 installments of ৳5,000 each
  3. Payment 1: ৳5,500 (overpayment ৳500)
  4. Verify: credit created, invoice marked paid
  5. Payment 2: ৳5,200 (overpayment ৳200)
  6. Verify: 2nd credit created
  7. Next invoice: ৳5,000 - ৳500 (from credit 1) = ৳4,500 due
  8. Payment 3: ৳4,800
  9. Verify: covers 4,500, creates ৳300 new credit
  10. Queue email: payment confirmation
  11. Send all pending emails
  12. Verify audit trail shows all transactions

- **Expected Result**: All steps execute without errors, audit complete

### Test 7: Purchase Merge Flow

- **Scenario**: Client merges 2 properties into one file

- **Steps**:
  1. Create client "Ahmad" with 2 properties:
     - Property A: ৳50,000 total, paid ৳30,000, credit ৳5,000
     - Property B: ৳75,000 total, paid ৳40,000, credit ৳8,000
  2. Create merge request: A → B
  3. Admin reviews: checks consolidation details
  4. Admin approves merge
  5. Verify Property A marked "merged"
  6. Verify Property B now has:
     - Total paid: ৳70,000
     - Total credit: ৳13,000
     - Combined schedules
  7. Queue notification email to client
  8. Verify audit shows merge with all details

- **Expected Result**: Merge executes cleanly, no data loss

### Test 8: Error Handling

#### Test 8.1: Invalid Data
- Try to create invoice with amount=0: should reject
- Try to record payment with amount<0: should reject
- Try to merge same property to itself: should reject
- Verify error messages are clear

#### Test 8.2: Database Rollback
- Create merge request
- Approve merge
- Simulate partial failure
- Verify transaction rolls back: data unchanged

#### Test 8.3: Email Failures
- Try to send to invalid email
- Verify status='failed', retry_count incremented
- Verify error logged

---

## Performance Testing

### Test 9: Load Testing

- **Test 9.1**: Create 100 invoices for single purchase
  - Verify performance: <2 seconds to display
  - Verify pagination works

- **Test 9.2**: Retrieve 1000 audit trail entries
  - Verify pagination: 25 per page
  - Verify filters responsive

- **Test 9.3**: Queue 500 emails
  - Send all pending
  - Verify batch processing efficient

---

## Browser Compatibility

- Test on: Chrome, Firefox, Safari, Edge (latest 2 versions)
- Verify: All modals render correctly
- Verify: Date pickers work
- Verify: AJAX requests handle timeouts

---

## Accessibility Testing

- Tab navigation: verify all form fields accessible
- Screen reader: verify labels associated
- Keyboard: verify all buttons accessible without mouse
- Color contrast: verify badges readable

---

## Security Testing

- SQL Injection: test with special characters in inputs
- XSS: test with HTML/script in remarks/email
- CSRF: verify session tokens validated
- Permissions: verify non-admin can't approve merges

---

## Regression Testing

After each update:
1. Run all unit tests
2. Run 2-3 integration tests
3. Verify no existing features broken
4. Check audit trail still logging
5. Verify email queue still functioning

---

## Sign-Off Checklist

- [ ] Database migration successful
- [ ] All API endpoints responding
- [ ] Tab pages loading with AJAX
- [ ] Email templates displaying correctly
- [ ] Audit trail logging all actions
- [ ] Merge functionality working with approval
- [ ] No console errors
- [ ] All filters functioning
- [ ] PDF generation working (if applicable)
- [ ] Cron jobs configured for email sending
- [ ] Backups tested

---

## Deployment Checklist

1. Backup production database
2. Run migration script on production
3. Deploy backend API modules
4. Deploy frontend files
5. Deploy email templates
6. Clear application cache
7. Monitor error logs for 24 hours
8. Verify all tabs load without errors
9. Test payment recording flow
10. Confirm emails sending

---

## Support & Troubleshooting

### Common Issues

**Issue**: Invoices not displaying
- Solution: Check AJAX endpoint exists
- Verify purchase_id in URL
- Check browser console for errors

**Issue**: Email not sending
- Solution: Verify email valid format
- Check mail server configuration
- Review email logs for error

**Issue**: Merge fails silently
- Solution: Check both purchases exist
- Verify same client ownership
- Review audit trail for details

**Issue**: Credits not applying
- Solution: Check credit status='active'
- Verify next invoice exists
- Review audit trail for application attempts

---

Generated: 2025-11-20
Version: 1.0
Status: Ready for Testing
