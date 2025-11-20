<?php
/**
 * Invoice Email Template
 * Variables: $client_name, $invoice_number, $invoice_date, $items (array), $total_amount, $due_date, $payment_instructions
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice - <?php echo $invoice_number; ?></title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 700px; margin: 0 auto; padding: 20px; }
        .header { background-color: #003366; color: white; padding: 20px; text-align: center; }
        .content { background-color: #f8f9fa; padding: 20px; }
        .invoice-header { background-color: white; padding: 15px; margin-bottom: 15px; }
        .invoice-number { font-size: 16px; font-weight: bold; color: #003366; }
        .table { width: 100%; border-collapse: collapse; margin: 15px 0; background: white; }
        .table th { background-color: #e9ecef; padding: 10px; text-align: left; font-weight: bold; border: 1px solid #ddd; }
        .table td { padding: 10px; border: 1px solid #ddd; }
        .total-row { font-weight: bold; background-color: #e9ecef; }
        .payment-section { background-color: white; padding: 15px; margin-top: 15px; border-left: 4px solid #003366; }
        .footer { text-align: center; color: #6c757d; font-size: 12px; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>INVOICE</h2>
        </div>
        <div class="content">
            <div class="invoice-header">
                <div class="invoice-number">Invoice #: <?php echo $invoice_number; ?></div>
                <div>Invoice Date: <?php echo date('d M, Y', strtotime($invoice_date)); ?></div>
                <div>Due Date: <?php echo date('d M, Y', strtotime($due_date)); ?></div>
            </div>
            
            <p>Dear <strong><?php echo $client_name; ?></strong>,</p>
            
            <p>We have prepared an invoice for you. Please find the details below:</p>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Item Description</th>
                        <th align="right">Quantity</th>
                        <th align="right">Unit Price</th>
                        <th align="right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo $item['description']; ?></td>
                        <td align="right"><?php echo $item['quantity']; ?></td>
                        <td align="right">৳<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td align="right">৳<?php echo number_format($item['amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <table class="table" style="width: 50%; margin-left: auto;">
                <tr class="total-row">
                    <td>Total Amount Due:</td>
                    <td align="right">৳<?php echo number_format($total_amount, 2); ?></td>
                </tr>
            </table>
            
            <div class="payment-section">
                <h4>Payment Instructions:</h4>
                <p><?php echo nl2br($payment_instructions); ?></p>
            </div>
            
            <p style="margin-top: 20px; color: #dc3545;"><strong>Please ensure payment is made by the due date to avoid penalties.</strong></p>
        </div>
        <div class="footer">
            <p>&copy; 2025 Civic Group. All rights reserved.</p>
            <p>Contact: info@civicgroup.com | Phone: +880-2-XXXX-XXXX</p>
        </div>
    </div>
</body>
</html>
