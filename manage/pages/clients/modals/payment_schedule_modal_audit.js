// Audit Trail Tab JavaScript
jQuery(function($){
    'use strict';

    $(document).on('shown.bs.tab', '#tab-audit-btn', function(){
        loadAuditTrail();
    });

    window.loadAuditTrail = function(){
        var purchaseId = $('#ui_purchase_id').val();
        var category = $('#audit_category_filter').val();
        var dateFrom = $('#audit_date_from').val();
        var dateTo = $('#audit_date_to').val();

        if (!purchaseId) return;

        $.post(Wo_Ajax_Requests_File() + '?f=manage_inventory_audit&s=get_audit_trail', {
            purchase_id: purchaseId,
            category: category,
            date_from: dateFrom,
            date_to: dateTo,
            limit: 50
        })
            .done(function(resp){
                var data = typeof resp === 'string' ? JSON.parse(resp) : resp;
                if (data.status === 200){
                    renderAuditTrail(data.audit || []);
                } else {
                    $('#audit_trail_container').html('<div class="alert alert-danger">Error loading</div>');
                }
            })
            .fail(function(){
                $('#audit_trail_container').html('<div class="alert alert-danger">Server error</div>');
            });
    };

    function renderAuditTrail(records){
        var $container = $('#audit_trail_container').empty();

        if (records.length === 0){
            $container.html('<div class="text-center text-muted py-3"><i class="lni lni-inbox me-2"></i>No audit records</div>');
            return;
        }

        var html = '<div class="audit-timeline">';
        records.forEach(function(record){
            var categoryBadge = getCategoryBadge(record.category);
            var icon = getCategoryIcon(record.category);

            html += '<div class="audit-entry mb-3 pb-3 border-bottom">' +
                    '<div class="d-flex gap-3">' +
                    '<div class="text-muted">' + icon + '</div>' +
                    '<div class="flex-grow-1">' +
                    '<div><small class="text-muted">' + formatDate(record.timestamp) + ' by ' + record.user + '</small></div>' +
                    '<div class="fw-medium mt-1">' + record.action + ' - ' + record.description + '</div>' +
                    '<div>' + categoryBadge + '</div>' +
                    '</div>' +
                    '</div>' +
                    '</div>';
        });
        html += '</div>';

        $container.html(html);
    }

    function getCategoryBadge(category){
        var badges = {
            'payment': '<span class="badge bg-success">Payment</span>',
            'invoice': '<span class="badge bg-info">Invoice</span>',
            'email': '<span class="badge bg-primary">Email</span>',
            'reschedule': '<span class="badge bg-warning text-dark">Reschedule</span>',
            'manual_adjustment': '<span class="badge bg-secondary">Adjustment</span>'
        };
        return badges[category] || '<span class="badge bg-secondary">' + category + '</span>';
    }

    function getCategoryIcon(category){
        var icons = {
            'payment': '<i class="lni lni-wallet"></i>',
            'invoice': '<i class="lni lni-document"></i>',
            'email': '<i class="lni lni-email"></i>',
            'reschedule': '<i class="lni lni-reload"></i>',
            'manual_adjustment': '<i class="lni lni-pencil"></i>'
        };
        return icons[category] || '<i class="lni lni-list"></i>';
    }

    function formatDate(dateStr){
        if (!dateStr) return '-';
        var d = new Date(dateStr);
        return d.toLocaleDateString() + ' ' + d.toLocaleTimeString();
    }

    // Filter change handlers
    $('#audit_category_filter, #audit_date_from, #audit_date_to').on('change', function(){
        loadAuditTrail();
    });
});
