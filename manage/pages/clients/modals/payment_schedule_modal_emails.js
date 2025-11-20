// Email Management Tab JavaScript
jQuery(function($){
    'use strict';

    // Load pending emails when tab is shown
    $(document).on('shown.bs.tab', '#tab-emails-btn', function(){
        loadPendingEmailsList();
    });

    window.loadPendingEmailsList = function(){
        var purchaseId = $('#ui_purchase_id').val();
        if (!purchaseId) return;

        $.post(Wo_Ajax_Requests_File() + '?f=manage_inventory_emails&s=get_pending_emails', {
            purchase_id: purchaseId
        })
            .done(function(resp){
                var data = typeof resp === 'string' ? JSON.parse(resp) : resp;
                if (data.status === 200){
                    renderPendingEmails(data.pending || []);
                    updateEmailBadges(data.count);
                } else {
                    $('#pending_receipts_container').html('<div class="alert alert-danger">Error loading</div>');
                }
            })
            .fail(function(){
                $('#pending_receipts_container').html('<div class="alert alert-danger">Server error</div>');
            });
    };

    function renderPendingEmails(pendingItems){
        var receipts = pendingItems.filter(function(item){
            return item.type === 'money_receipt';
        });
        var schedules = pendingItems.filter(function(item){
            return item.type === 'payment_schedule';
        });

        renderReceiptsList(receipts);
        renderSchedulesList(schedules);
    }

    function renderReceiptsList(receipts){
        var $container = $('#pending_receipts_container').empty();

        if (receipts.length === 0){
            $container.html('<div class="text-center text-muted py-3"><i class="lni lni-inbox me-2"></i>No pending receipts</div>');
            return;
        }

        var html = '<div class="list-group">';
        receipts.forEach(function(item){
            html += '<div class="list-group-item">' +
                    '<div class="d-flex justify-content-between align-items-start">' +
                    '<div>' +
                    '<h6 class="mb-1">' + item.title + '</h6>' +
                    '<small class="text-muted">Amount: ৳' + formatMoney(item.amount) + ' | Date: ' + item.date + '</small>' +
                    '</div>' +
                    '<button class="btn btn-sm btn-primary" onclick="prepareEmailSend(\'' + item.type + '\', ' + item.id + ')">Send</button>' +
                    '</div>' +
                    '</div>';
        });
        html += '</div>';

        $container.html(html);
    }

    function renderSchedulesList(schedules){
        var $container = $('#pending_schedules_container').empty();

        if (schedules.length === 0){
            $container.html('<div class="text-center text-muted py-3"><i class="lni lni-inbox me-2"></i>No pending schedules</div>');
            return;
        }

        var html = '<div class="list-group">';
        schedules.forEach(function(item){
            html += '<div class="list-group-item">' +
                    '<div class="d-flex justify-content-between align-items-start">' +
                    '<div>' +
                    '<h6 class="mb-1">' + item.title + '</h6>' +
                    '<small class="text-muted">Items: ' + item.item_count + ' | Total: ৳' + formatMoney(item.total_amount) + '</small>' +
                    '</div>' +
                    '<button class="btn btn-sm btn-primary" onclick="prepareEmailSend(\'' + item.type + '\', ' + item.id + ')">Send</button>' +
                    '</div>' +
                    '</div>';
        });
        html += '</div>';

        $container.html(html);
    }

    function updateEmailBadges(count){
        if (count > 0){
            $('#email-pending-badge').text(count).show();
        } else {
            $('#email-pending-badge').hide();
        }
    }

    function formatMoney(amount){
        return Math.round(amount).toLocaleString('en-US');
    }

    window.prepareEmailSend = function(emailType, itemId){
        // TODO: Open email preview and send dialog
        alert('Send ' + emailType + ' - Item: ' + itemId);
    };
});
