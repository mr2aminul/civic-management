// Invoice Management JavaScript
// Integrates with payment_schedule_modal_enhanced.phtml

jQuery(function($){
    'use strict';

    // Load invoices when tab is clicked
    $(document).on('shown.bs.tab', '#tab-invoices-btn', function(){
        loadInvoicesForPurchase();
    });

    window.loadInvoicesForPurchase = function(){
        var purchaseId = $('#ui_purchase_id').val();
        if (!purchaseId) return;

        $.post(Wo_Ajax_Requests_File() + '?f=manage_inventory_invoices&s=get_invoices_for_purchase', {
            purchase_id: purchaseId
        })
            .done(function(resp){
                var data = typeof resp === 'string' ? JSON.parse(resp) : resp;
                if (data.status === 200){
                    renderInvoicesList(data.invoices || []);
                } else {
                    $('#invoices_list_container').html('<div class="alert alert-danger">Error loading invoices</div>');
                }
            })
            .fail(function(){
                $('#invoices_list_container').html('<div class="alert alert-danger">Server error</div>');
            });
    };

    function renderInvoicesList(invoices){
        var $container = $('#invoices_list_container').empty();

        if (invoices.length === 0){
            $container.html('<div class="text-center text-muted py-3"><i class="lni lni-inbox me-2"></i>No invoices created</div>');
            return;
        }

        var html = '<div class="table-responsive"><table class="table table-sm table-hover"><thead class="table-light"><tr>' +
                   '<th>Invoice #</th><th>Date</th><th>Total (৳)</th><th>Paid (৳)</th><th>Balance (৳)</th><th>Status</th><th>Actions</th>' +
                   '</tr></thead><tbody>';

        invoices.forEach(function(inv){
            var statusBadge = getStatusBadge(inv.status);
            var actions = '<button class="btn btn-sm btn-outline-primary" onclick="viewInvoiceDetail(' + inv.id + ')">View</button> ';
            
            if (inv.status !== 'cancelled' && inv.paid_amount > 0 && !inv.money_receipt_no){
                actions += '<button class="btn btn-sm btn-outline-success" onclick="generateReceipt(' + inv.id + ')">Receipt</button>';
            }

            html += '<tr>' +
                    '<td><small class="fw-bold">' + inv.invoice_number + '</small></td>' +
                    '<td><small>' + inv.invoice_date + '</small></td>' +
                    '<td class="text-end"><small>৳' + formatMoney(inv.total_amount) + '</small></td>' +
                    '<td class="text-end"><small>৳' + formatMoney(inv.paid_amount) + '</small></td>' +
                    '<td class="text-end"><small>৳' + formatMoney(inv.balance_amount) + '</small></td>' +
                    '<td>' + statusBadge + '</td>' +
                    '<td>' + actions + '</td>' +
                    '</tr>';
        });

        html += '</tbody></table></div>';
        $container.html(html);
    }

    function getStatusBadge(status){
        var badges = {
            'draft': '<span class="badge bg-secondary">Draft</span>',
            'issued': '<span class="badge bg-info">Issued</span>',
            'partial': '<span class="badge bg-warning text-dark">Partial</span>',
            'paid': '<span class="badge bg-success">Paid</span>',
            'cancelled': '<span class="badge bg-danger">Cancelled</span>',
            'overdue': '<span class="badge bg-danger">Overdue</span>'
        };
        return badges[status] || '<span class="badge bg-secondary">' + status + '</span>';
    }

    function formatMoney(amount){
        return Math.round(amount).toLocaleString('en-US');
    }

    window.viewInvoiceDetail = function(invoiceId){
        // TODO: Open invoice detail modal
        alert('Invoice detail view - ID: ' + invoiceId);
    };

    window.generateReceipt = function(invoiceId){
        if (!confirm('Generate money receipt for this invoice?')) return;

        $.post(Wo_Ajax_Requests_File() + '?f=manage_inventory_invoices&s=generate_money_receipt', {
            invoice_id: invoiceId,
            payment_method: 'Cash'
        })
            .done(function(resp){
                var data = typeof resp === 'string' ? JSON.parse(resp) : resp;
                if (data.status === 200){
                    alert('Money receipt generated: ' + data.receipt_number);
                    loadInvoicesForPurchase();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .fail(function(){
                alert('Server error');
            });
    };

    // Create new invoice button
    $(document).on('click', '#create_invoice_btn', function(){
        openCreateInvoiceModal();
    });

    function openCreateInvoiceModal(){
        var purchaseId = $('#ui_purchase_id').val();
        if (!purchaseId) {
            alert('Purchase ID not found');
            return;
        }

        // Get unpaid schedule items
        var unpaidItems = [];
        $('#ui_schedule_table tbody tr').each(function(){
            var $row = $(this);
            var statusText = $row.find('.status-col').text();
            
            if (statusText.indexOf('Unpaid') !== -1){
                var idx = $row.data('idx-row');
                var particular = currentSchedule[idx]?.particular || '';
                var amount = currentSchedule[idx]?.installment_amount || 0;
                
                unpaidItems.push({
                    id: currentSchedule[idx]?.id || idx,
                    particular: particular,
                    amount: amount
                });
            }
        });

        if (unpaidItems.length === 0){
            alert('No unpaid installments available to invoice');
            return;
        }

        // TODO: Open create invoice modal with checkbox list of unpaid items
        alert('Create invoice modal - ' + unpaidItems.length + ' unpaid items');
    }
});
