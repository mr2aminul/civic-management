/**
 * Modal Tab Data Loader for Payment Schedule Modal
 * Add this to manage_purchase_modal.phtml before closing </script> tag
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

  // Tab: Documents
  $('#tab-documents-btn').on('shown.bs.tab', function() {
    loadDocuments();
  });

  // Load Documents
  function loadDocuments() {
    var purchaseId = $('#ui_purchase_id').val();
    var type = $('#doc_type_filter').val();
    if (!purchaseId) return;
    
    $('#documents_list_container').html('<p class="text-center text-muted py-5"><i class="lni lni-spinner-arrow lni-spin me-2"></i>Loading documents...</p>');
    
    $.get(Wo_Ajax_Requests_File() + '?f=manage_inventory&s=get_purchase_documents&purchase_id=' + purchaseId + '&type=' + (type || ''))
      .done(function(resp) {
        var data = typeof resp === 'string' ? JSON.parse(resp) : resp;
        if (data.status === 200 && data.documents && data.documents.length > 0) {
          renderDocuments(data.documents);
        } else {
          $('#documents_list_container').html('<p class="text-center text-muted py-5"><i class="lni lni-files me-2"></i>No documents found</p>');
        }
      })
      .fail(function() {
        $('#documents_list_container').html('<p class="text-danger text-center py-5"><i class="lni lni-warning me-2"></i>Failed to load documents</p>');
      });
  }

  function renderDocuments(docs) {
    var html = '<div class="table-responsive"><table class="table table-hover align-middle">';
    html += '<thead class="table-light"><tr><th>File Name</th><th>Type</th><th>Size</th><th>Date</th><th class="text-end">Action</th></tr></thead><tbody>';
    
    docs.forEach(function(doc) {
      var icon = 'lni-empty-file';
      var ext = (doc.file_name || '').split('.').pop().toLowerCase();
      if(['pdf'].includes(ext)) icon = 'lni-offer'; // pdf icon approximation
      if(['jpg','jpeg','png','gif'].includes(ext)) icon = 'lni-image';
      if(['doc','docx'].includes(ext)) icon = 'lni-text-format';
      if(['xls','xlsx'].includes(ext)) icon = 'lni-grid-alt';

      html += '<tr>';
      html += '  <td><div class="d-flex align-items-center"><i class="lni ' + icon + ' fs-4 me-2 text-secondary"></i><span>' + (doc.file_name || 'Untitled') + '</span></div></td>';
      html += '  <td><span class="badge bg-light text-dark border">' + (doc.document_type || 'Document') + '</span></td>';
      html += '  <td class="small text-muted">' + formatBytes(doc.file_size) + '</td>';
      html += '  <td class="small text-muted">' + (doc.generated_at || '-') + '</td>';
      html += '  <td class="text-end">';
      html += '    <div class="btn-group btn-group-sm">';
      html += '      <a href="' + (doc.file_path || '#') + '" target="_blank" class="btn btn-outline-secondary" title="View/Download"><i class="lni lni-download"></i></a>';
      html += '      <button type="button" class="btn btn-outline-danger btn-delete-doc" data-id="' + doc.id + '" title="Delete"><i class="lni lni-trash"></i></button>';
      html += '    </div>';
      html += '  </td>';
      html += '</tr>';
    });
    
    html += '</tbody></table></div>';
    $('#documents_list_container').html(html);
  }

  function formatBytes(bytes, decimals = 2) {
    if (!+bytes) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
  }

  // Document Filters
  $('#doc_type_filter').on('change', function() {
    loadDocuments();
  });

  // Upload Document
  $('#btn-upload-document').on('click', function() {
    $('#file-upload-input').trigger('click');
  });

  $('#file-upload-input').on('change', function() {
    var file = this.files[0];
    if (!file) return;

    var purchaseId = $('#ui_purchase_id').val();
    var type = $('#doc_type_filter').val() || 'other'; // Default to 'other' or current filter
    if(type === '') type = 'other';

    var formData = new FormData();
    formData.append('file', file);
    formData.append('purchase_id', purchaseId);
    formData.append('document_type', type);

    var $btn = $('#btn-upload-document');
    var originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i>Uploading...');

    $.ajax({
      url: Wo_Ajax_Requests_File() + '?f=manage_inventory&s=upload_purchase_document',
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      success: function(resp) {
        var data = typeof resp === 'string' ? JSON.parse(resp) : resp;
        if (data.status === 200) {
          loadDocuments();
          $('#file-upload-input').val(''); // Reset input
        } else {
          alert('Upload failed: ' + (data.message || 'Unknown error'));
        }
      },
      error: function() {
        alert('Server error during upload');
      },
      complete: function() {
        $btn.prop('disabled', false).html(originalHtml);
      }
    });
  });

  // Delete Document
  $(document).on('click', '.btn-delete-doc', function() {
    if(!confirm('Are you sure you want to delete this document?')) return;
    
    var docId = $(this).data('id');
    var $btn = $(this);
    $btn.prop('disabled', true);

    $.post(Wo_Ajax_Requests_File() + '?f=manage_inventory&s=delete_purchase_document', {
      document_id: docId
    }, function(resp) {
      var data = typeof resp === 'string' ? JSON.parse(resp) : resp;
      if (data.status === 200) {
        loadDocuments();
      } else {
        alert('Delete failed: ' + (data.message || 'Unknown error'));
        $btn.prop('disabled', false);
      }
    }).fail(function() {
      alert('Server error during delete');
      $btn.prop('disabled', false);
    });
  });

});

