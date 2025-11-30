/**
 * Global Nested Modal Manager
 * Handles z-index stacking and ESC key behavior for nested modals
 */

(function() {
    'use strict';
    
    // Modal stack to track open modals
    var modalStack = [];
    var baseZIndex = 1050; // Bootstrap modal base z-index
    var zIndexIncrement = 10;
    
    // Initialize on document ready
    $(document).ready(function() {
        initializeNestedModalManager();
    });
    
    function initializeNestedModalManager() {
        // Track modal show events
        $(document).on('show.bs.modal', '.modal', function(e) {
            var $modal = $(this);
            var modalId = $modal.attr('id');
            
            // Add to stack
            modalStack.push(modalId);
            
            // Calculate z-index based on stack position
            var stackIndex = modalStack.length - 1;
            var newZIndex = baseZIndex + (stackIndex * zIndexIncrement);
            
            // Set z-index for modal and backdrop
            $modal.css('z-index', newZIndex);
            
            // Wait for backdrop to be created
            setTimeout(function() {
                var $backdrop = $('.modal-backdrop').last();
                if ($backdrop.length) {
                    $backdrop.css('z-index', newZIndex - 1);
                }
            }, 50);
            
            console.log('Modal opened:', modalId, 'Stack:', modalStack.length, 'Z-Index:', newZIndex);
        });
        
        // Track modal hide events
        $(document).on('hidden.bs.modal', '.modal', function(e) {
            var $modal = $(this);
            var modalId = $modal.attr('id');
            
            // Remove from stack
            var index = modalStack.indexOf(modalId);
            if (index > -1) {
                modalStack.splice(index, 1);
            }
            
            // Reset z-index
            $modal.css('z-index', '');
            
            // If there are still modals open, ensure body has modal-open class
            if (modalStack.length > 0) {
                $('body').addClass('modal-open');
            }
            
            console.log('Modal closed:', modalId, 'Remaining stack:', modalStack.length);
        });
        
        // Handle ESC key - only close topmost modal
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                if (modalStack.length > 0) {
                    // Get topmost modal
                    var topmostModalId = modalStack[modalStack.length - 1];
                    var $topmostModal = $('#' + topmostModalId);
                    
                    // Check if modal allows ESC to close (data-bs-keyboard)
                    var allowEsc = $topmostModal.data('bs-keyboard') !== false;
                    
                    if (allowEsc) {
                        e.stopPropagation();
                        e.preventDefault();
                        $topmostModal.modal('hide');
                    }
                }
            }
        });
        
        // Prevent backdrop click from closing parent modals
        $(document).on('click', '.modal', function(e) {
            if (e.target === this) {
                var $modal = $(this);
                var modalId = $modal.attr('id');
                
                // Only close if it's the topmost modal
                if (modalStack.length > 0 && modalStack[modalStack.length - 1] === modalId) {
                    var allowBackdropClose = $modal.data('bs-backdrop') !== 'static';
                    if (allowBackdropClose) {
                        $modal.modal('hide');
                    }
                }
                e.stopPropagation();
            }
        });
        
        // Fix select2 dropdown z-index in nested modals
        $(document).on('select2:open', function(e) {
            var $select = $(e.target);
            var $modal = $select.closest('.modal');
            
            if ($modal.length) {
                var modalZIndex = parseInt($modal.css('z-index')) || baseZIndex;
                var $dropdown = $('.select2-container--open').last();
                
                if ($dropdown.length) {
                    $dropdown.css('z-index', modalZIndex + 5);
                }
            }
        });
    }
    
    // Expose utility functions globally
    window.NestedModalManager = {
        getModalStack: function() {
            return modalStack.slice(); // Return copy
        },
        getTopmostModal: function() {
            return modalStack.length > 0 ? modalStack[modalStack.length - 1] : null;
        },
        closeTopmost: function() {
            if (modalStack.length > 0) {
                var topmostId = modalStack[modalStack.length - 1];
                $('#' + topmostId).modal('hide');
            }
        },
        closeAll: function() {
            // Close in reverse order
            var stack = modalStack.slice().reverse();
            stack.forEach(function(modalId) {
                $('#' + modalId).modal('hide');
            });
        }
    };
    
})();
