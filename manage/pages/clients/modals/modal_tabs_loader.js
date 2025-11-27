/**
 * Modal Tab Data Loader for Payment Schedule Modal
 * Add this to payment_schedule_modal.phtml before closing </script> tag
 */

// Load data when tabs are shown
$(document).ready(function() {
  
  // Tab 3: Pending Actions
  $('#tab-pending-btn').on('shown.bs.tab', function() {
    loadPendingActions();
  });
  
  // Tab 4: Invoices
  $('#tab-invoices-btn').on('shown.bs.tab', function() {
    loadInvoices();
  });
  
  // Tab 5: Emails
  $('#tab-emails-btn').on('shown.bs.tab', function() {
    loadPendingEmails();
  });
  
  // Tab 6: Audit
  $('#tab-audit-btn').on('shown.bs.tab', function() {
    loadAuditTrail();
  });

  // Load Pending Actions
  function loadPendingActions() {
    var purchaseId = $('#ui_purchase_id').val();
    if (!purchaseId) return;
    
    $('#pending_actions_container').html('<p class="text-center text-muted py-4"><i class="lni lni-spinner-arrow lni-spin me-2"></i>Loading...</p>');
    
    $.get(Wo_Ajax_Requests_File() + '?f=manage_inventory&s=get_pending_changes&purchase_id=' + purchaseId)
      .done(function(resp) {
        var data = typeof resp === 'string' ? JSON.parse(resp) : resp;
        if (data.status === 200 && data.changes && data.changes.length > 0) {
          renderPendingActions(data.changes);
        } else {
          $('#pending_actions_container').html('<p class="text-muted text-center py-4"><i class="lni lni-inbox me-2"></i>No pending actions</p>');
        }
      })
      .fail(function() {
        $('#pending_actions_container').html('<p class="text-danger text-center py-4"><i class="lni lni-warning me-2"></i>Failed to load pending actions</p>');
      });
  }

  function renderPendingActions(changes) {
    var html = '<div class="list-group">';
    changes.forEach(function(change) {
      var statusBadge = change.status === 'pending' ? 'bg-warning' : (change.status === 'approved' ? 'bg-success' : 'bg-danger');
      html += '<div class="list-group-item">';
      html += '  <div class="d-flex justify-content-between align-items-start">';
      html += '    <div>';
      html += '      <h6 class="mb-1">' + (change.change_type || 'Change') + '</h6>';
      html += '      <p class="mb-1 small">' + (change.reason || 'No reason provided') + '</p>';
      html += '      <small class="text-muted">Requested: ' + (change.created_at || 'Unknown') + '</small>';
      html += '    </div>';
      html += '    <span class="badge ' + statusBadge + '">' + (change.status || 'pending') + '</span>';
      html += '  </div>';
      html += '</div>';
    });
    html += '</div>';
    $('#pending_actions_container').html(html);
  }

  // Load Invoices
  function loadInvoices() {
    var purchaseId = $('#ui_purchase_id').val();
    if (!purchaseId) return;
    
    $('#invoices_list_container').html('<p class="text-center text-muted py-4"><i class="lni lni-spinner-arrow lni-spin me-2"></i>Loading...</p>');
    
    $.get(Wo_Ajax_Requests_File() + '?f=manage_inventory&s=get_invoices&purchase_id=' + purchaseId)
      .done(function(resp) {
        var data = typeof resp === 'string' ? JSON.parse(resp) : resp;
        if (data.status === 200 && data.invoices && data.invoices.length > 0) {
          renderInvoices(data.invoices);
        } else {
          $('#invoices_list_container').html('<p class="text-muted text-center py-4">No invoices found</p>');
        }
      })
      .fail(function() {
        $('#invoices_list_container').html('<p class="text-danger text-center py-4"><i class="lni lni-warning me-2"></i>Failed to load invoices</p>');
      });
  }

  function renderInvoices(invoices) {
    var html = '<div class="table-responsive"><table class="table table-sm table-hover">';
    html += '<thead><tr><th>Invoice #</th><th>Type</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>';
    invoices.forEach(function(inv) {
      var statusClass = inv.status === 'paid' ? 'success' : (inv.status === 'partial' ? 'warning' : 'danger');
      html += '<tr>';
      html += '  <td>' + (inv.invoice_number || '-') + '</td>';
      html += '  <td>' + (inv.invoice_type || '-') + '</td>';
      html += '  <td>৳' + (parseFloat(inv.amount || 0).toLocaleString()) + '</td>';
      html += '  <td><span class="badge bg-' + statusClass + '">' + (inv.status || 'pending') + '</span></td>';
      html += '  <td>' + (inv.invoice_date || '-') + '</td>';
      html += '</tr>';
    });
    html += '</tbody></table></div>';
    $('#invoices_list_container').html(html);
  }

  // Load Pending Emails
  function loadPendingEmails() {
    var purchaseId = $('#ui_purchase_id').val();
    if (!purchaseId) return;
    
    $('#pending_receipts_container').html('<p class="text-center text-muted py-4"><i class="lni lni-spinner-arrow lni-spin me-2"></i>Loading...</p>');
    
    $.get(Wo_Ajax_Requests_File() + '?f=manage_inventory&s=get_pending_emails&purchase_id=' + purchaseId)
      .done(function(resp) {
        var data = typeof resp === 'string' ? JSON.parse(resp) : resp;
        if (data.status === 200 && data.emails && data.emails.length > 0) {
          renderPendingEmails(data.emails);
        } else {
          $('#pending_receipts_container').html('<p class="text-muted text-center py-4">No pending emails</p>');
        }
      })
      .fail(function() {
        $('#pending_receipts_container').html('<p class="text-muted text-center py-4">No pending emails</p>');
      });
  }

  function renderPendingEmails(emails) {
    var html = '<div class="list-group">';
    emails.forEach(function(email) {
      html += '<div class="list-group-item">';
      html += '  <div class="d-flex justify-content-between">';
      html += '    <div><strong>' + (email.template_type || 'Email') + '</strong><br><small>' + (email.recipient_email || '') + '</small></div>';
      html += '    <span class="badge bg-' + (email.status === 'sent' ? 'success' : 'warning') + '">' + (email.status || 'queued') + '</span>';
      html += '  </div>';
      html += '</div>';
    });
    html += '</div>';
    $('#pending_receipts_container').html(html);
  }

  // Load Audit Trail
  function loadAuditTrail() {
    var purchaseId = $('#ui_purchase_id').val();
    if (!purchaseId) return;
    
    $('#audit_trail_container').html('<p class="text-center text-muted py-4"><i class="lni lni-spinner-arrow lni-spin me-2"></i>Loading...</p>');
    
    $.get(Wo_Ajax_Requests_File() + '?f=manage_inventory&s=get_audit_trail&purchase_id=' + purchaseId)
      .done(function(resp) {
        var data = typeof resp === 'string' ? JSON.parse(resp) : resp;
        if (data.success && data.audit_logs && data.audit_logs.length > 0) {
          renderAuditTrail(data.audit_logs);
        } else {
          $('#audit_trail_container').html('<p class="text-muted text-center py-4">No audit logs found</p>');
        }
      })
      .fail(function() {
        $('#audit_trail_container').html('<p class="text-muted text-center py-4">No audit logs found</p>');
      });
  }

  function renderAuditTrail(logs) {
    var html = '<div class="timeline">';
    logs.forEach(function(log) {
      html += '<div class="border-start border-2 border-primary ps-3 pb-3">';
      html += '  <div class="small text-muted">' + (log.timestamp || log.created_at || '') + '</div>';
      html += '  <div class="fw-bold">' + (log.action || log.action_type || 'Action') + '</div>';
      html += '  <div class="small">' + (log.description || '') + '</div>';
      html += '  <div class="small text-muted">By: ' + (log.performed_by_name || log.changed_by_name || 'System') + '</div>';
      html += '</div>';
    });
    html += '</div>';
    $('#audit_trail_container').html(html);
  }

  // Create Invoice button
  $('#create_invoice_btn').on('click', function() {
    $('#createInvoiceModal').modal('show');
  });

});
