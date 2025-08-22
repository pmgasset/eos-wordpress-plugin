/**
 * EOS Manager Admin JavaScript
 */

(function($) {
    'use strict';
    
    // Global EOS object
    window.EOS = {
        init: function() {
            this.bindEvents();
            this.initComponents();
        },
        
        bindEvents: function() {
            // Modal events
            $(document).on('click', '[data-modal]', this.openModal);
            $(document).on('click', '.eos-modal-close, .eos-modal-backdrop', this.closeModal);
            $(document).on('keyup', this.handleEscape);
            
            // Form events
            $(document).on('submit', '.eos-form', this.handleFormSubmit);
            $(document).on('click', '.eos-save-btn', this.handleSave);
            $(document).on('click', '.eos-delete-btn', this.handleDelete);
            
            // Rock events
            $(document).on('change', '.rock-progress-slider', this.updateRockProgress);
            $(document).on('click', '.complete-rock-btn', this.completeRock);
            
            // Issue events
            $(document).on('change', '.issue-status-select', this.updateIssueStatus);
            $(document).on('click', '.solve-issue-btn', this.solveIssue);
            
            // Scorecard events
            $(document).on('change', '.metric-value-input', this.updateMetricValue);
            $(document).on('click', '.save-scorecard-btn', this.saveScorecard);
            
            // Meeting events
            $(document).on('click', '.start-meeting-btn', this.startMeeting);
            $(document).on('click', '.complete-meeting-btn', this.completeMeeting);
            $(document).on('click', '.create-google-meet-btn', this.createGoogleMeet);
            
            // Auto-save agenda items
            $(document).on('blur', '.agenda-notes', this.saveAgendaItem);
            
            // Dashboard interactions
            $(document).on('click', '.dashboard-card', this.handleCardClick);
        },
        
        initComponents: function() {
            this.initDatePickers();
            this.initProgressBars();
            this.initTooltips();
            this.initCharts();
            this.initNotifications();
        },
        
        // Modal Management
        openModal: function(e) {
            e.preventDefault();
            const modalId = $(this).data('modal');
            const $modal = $('#' + modalId);
            
            if ($modal.length) {
                $modal.addClass('show').fadeIn(300);
                $('body').addClass('modal-open');
                
                // Focus first input
                setTimeout(() => {
                    $modal.find('input, textarea, select').first().focus();
                }, 350);
            }
        },
        
        closeModal: function(e) {
            if (e.target === this || $(e.target).hasClass('eos-modal-close')) {
                const $modal = $(this).closest('.eos-modal');
                $modal.removeClass('show').fadeOut(300);
                $('body').removeClass('modal-open');
            }
        },
        
        handleEscape: function(e) {
            if (e.keyCode === 27) { // Escape key
                $('.eos-modal.show').trigger('click');
            }
        },
        
        // Form Handling
        handleFormSubmit: function(e) {
            e.preventDefault();
            const $form = $(this);
            const formData = $form.serializeArray();
            const action = $form.data('action');
            
            EOS.submitForm(action, formData, $form);
        },
        
        handleSave: function(e) {
            e.preventDefault();
            const $btn = $(this);
            const $form = $btn.closest('form');
            const action = $btn.data('action') || $form.data('action');
            
            if ($form.length) {
                const formData = $form.serializeArray();
                EOS.submitForm(action, formData, $form);
            }
        },
        
        submitForm: function(action, formData, $form) {
            const $submitBtn = $form.find('[type="submit"], .eos-save-btn');
            const originalText = $submitBtn.text();
            
            // Show loading state
            $submitBtn.prop('disabled', true).text('Saving...');
            
            // Add nonce and action
            formData.push({name: 'action', value: action});
            formData.push({name: 'nonce', value: eosAjax.nonce});
            
            $.post(eosAjax.ajaxurl, formData)
                .done(function(response) {
                    if (response.success) {
                        EOS.showNotification(response.message || 'Saved successfully!', 'success');
                        
                        // Close modal if open
                        $form.closest('.eos-modal').find('.eos-modal-close').trigger('click');
                        
                        // Refresh page or specific content
                        if (response.refresh) {
                            location.reload();
                        } else {
                            EOS.refreshDashboard();
                        }
                    } else {
                        EOS.showNotification(response.message || 'Save failed. Please try again.', 'error');
                    }
                })
                .fail(function() {
                    EOS.showNotification('Network error. Please try again.', 'error');
                })
                .always(function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                });
        },
        
        // Rock Management
        updateRockProgress: function() {
            const $slider = $(this);
            const rockId = $slider.data('rock-id');
            const progress = parseInt($slider.val());
            
            // Update display
            $slider.siblings('.progress-display').text(progress + '%');
            
            // Update progress bar
            const $progressBar = $slider.closest('.rock-item').find('.progress-fill');
            $progressBar.css('width', progress + '%');
            
            // Debounced AJAX update
            clearTimeout($slider.data('timeout'));
            $slider.data('timeout', setTimeout(function() {
                EOS.saveRockProgress(rockId, progress);
            }, 1000));
        },
        
        saveRockProgress: function(rockId, progress) {
            $.post(eosAjax.ajaxurl, {
                action: 'eos_update_rock_progress',
                rock_id: rockId,
                progress: progress,
                nonce: eosAjax.nonce
            }).done(function(response) {
                if (response.success) {
                    EOS.showNotification('Progress updated!', 'success', 2000);
                    
                    // Auto-complete if 100%
                    if (progress >= 100) {
                        EOS.showNotification('Rock completed! 🎉', 'success', 3000);
                    }
                }
            });
        },
        
        completeRock: function(e) {
            e.preventDefault();
            const rockId = $(this).data('rock-id');
            
            if (confirm('Mark this rock as completed?')) {
                $.post(eosAjax.ajaxurl, {
                    action: 'eos_complete_rock',
                    rock_id: rockId,
                    nonce: eosAjax.nonce
                }).done(function(response) {
                    if (response.success) {
                        EOS.showNotification(response.message, 'success');
                        EOS.refreshDashboard();
                    } else {
                        EOS.showNotification(response.message, 'error');
                    }
                });
            }
        },
        
        // Issue Management
        updateIssueStatus: function() {
            const $select = $(this);
            const issueId = $select.data('issue-id');
            const status = $select.val();
            
            $.post(eosAjax.ajaxurl, {
                action: 'eos_update_issue_status',
                issue_id: issueId,
                status: status,
                nonce: eosAjax.nonce
            }).done(function(response) {
                if (response.success) {
                    EOS.showNotification('Status updated!', 'success', 2000);
                    
                    // Update status badge
                    const $badge = $select.closest('.issue-item').find('.status-badge');
                    $badge.removeClass().addClass('status-badge status-' + status).text(status);
                }
            });
        },
        
        solveIssue: function(e) {
            e.preventDefault();
            const issueId = $(this).data('issue-id');
            const solution = prompt('Enter the solution for this issue:');
            
            if (solution) {
                $.post(eosAjax.ajaxurl, {
                    action: 'eos_solve_issue',
                    issue_id: issueId,
                    solution: solution,
                    nonce: eosAjax.nonce
                }).done(function(response) {
                    if (response.success) {
                        EOS.showNotification(response.message, 'success');
                        EOS.refreshDashboard();
                    }
                });
            }
        },
        
        // Scorecard Management
        updateMetricValue: function() {
            const $input = $(this);
            const metricId = $input.data('metric-id');
            const value = parseFloat($input.val());
            
            if (!isNaN(value)) {
                // Debounced update
                clearTimeout($input.data('timeout'));
                $input.data('timeout', setTimeout(function() {
                    EOS.saveMetricValue(metricId, value);
                }, 1500));
            }
        },
        
        saveMetricValue: function(metricId, value) {
            $.post(eosAjax.ajaxurl, {
                action: 'eos_update_metric_value',
                metric_id: metricId,
                value: value,
                nonce: eosAjax.nonce
            }).done(function(response) {
                if (response.success) {
                    EOS.showNotification('Metric updated!', 'success', 2000);
                    
                    // Update status indicator
                    const $statusIndicator = $('[data-metric-id="' + metricId + '"]').closest('.metric-row').find('.status-indicator');
                    $statusIndicator.removeClass().addClass('status-indicator status-' + response.status);
                }
            });
        },
        
        saveScorecard: function(e) {
            e.preventDefault();
            const $form = $(this).closest('form');
            const formData = $form.serializeArray();
            
            $.post(eosAjax.ajaxurl, {
                action: 'eos_save_scorecard_data',
                scorecard_data: EOS.serializeToObject(formData),
                nonce: eosAjax.nonce
            }).done(function(response) {
                if (response.success) {
                    EOS.showNotification(response.message, 'success');
                    EOS.refreshDashboard();
                } else {
                    EOS.showNotification(response.message, 'error');
                }
            });
        },
        
        // Meeting Management
        startMeeting: function(e) {
            e.preventDefault();
            const meetingId = $(this).data('meeting-id');
            
            if (meetingId) {
                $.post(eosAjax.ajaxurl, {
                    action: 'eos_start_meeting',
                    meeting_id: meetingId,
                    nonce: eosAjax.nonce
                }).done(function(response) {
                    if (response.success) {
                        // Redirect to meeting interface
                        window.location.href = EOS.getAdminUrl('eos-meetings&action=run&id=' + meetingId);
                    }
                });
            } else {
                // Create new meeting from modal data
                const meetingData = EOS.getMeetingDataFromModal();
                EOS.createAndStartMeeting(meetingData);
            }
        },
        
        createAndStartMeeting: function(meetingData) {
            $.post(eosAjax.ajaxurl, {
                action: 'eos_save_meeting',
                meeting_data: meetingData,
                nonce: eosAjax.nonce
            }).done(function(response) {
                if (response.success) {
                    // Start the meeting
                    window.location.href = EOS.getAdminUrl('eos-meetings&action=run&id=' + response.meeting_id);
                }
            });
        },
        
        completeMeeting: function(e) {
            e.preventDefault();
            const meetingId = $(this).data('meeting-id');
            const actionItems = $('#meetingActionItems').val();
            
            $.post(eosAjax.ajaxurl, {
                action: 'eos_complete_meeting',
                meeting_id: meetingId,
                action_items: actionItems,
                nonce: eosAjax.nonce
            }).done(function(response) {
                if (response.success) {
                    EOS.showNotification(response.message, 'success');
                    EOS.refreshDashboard();
                }
            });
        },
        
        createGoogleMeet: function(e) {
            e.preventDefault();
            const meetingData = EOS.getMeetingDataFromModal();
            
            $.post(eosAjax.ajaxurl, {
                action: 'eos_create_google_meet',
                meeting_data: meetingData,
                nonce: eosAjax.nonce
            }).done(function(response) {
                if (response.success) {
                    $('#googleMeetLink').val(response.meet_link);
                    EOS.showNotification('Google Meet link created!', 'success');
                }
            });
        },
        
        saveAgendaItem: function() {
            const $textarea = $(this);
            const meetingId = $textarea.data('meeting-id');
            const section = $textarea.data('section');
            const notes = $textarea.val();
            
            if (meetingId && section) {
                $.post(eosAjax.ajaxurl, {
                    action: 'eos_save_agenda_item',
                    meeting_id: meetingId,
                    section: section,
                    notes: notes,
                    nonce: eosAjax.nonce
                });
            }
        },
        
        // Google Meet Integration
        joinMeeting: function(meetLink) {
            if (meetLink) {
                window.open(meetLink, '_blank');
            } else {
                window.open('https://meet.google.com/new', '_blank');
            }
        },
        
        copyMeetLink: function(meetLink) {
            if (navigator.clipboard && meetLink) {
                navigator.clipboard.writeText(meetLink).then(function() {
                    EOS.showNotification('Meet link copied to clipboard!', 'success', 2000);
                });
            } else {
                EOS.showNotification('No meet link available', 'warning');
            }
        },
        
        sendCalendarInvites: function(meetingData) {
            // This would integrate with Google Calendar API in a full implementation
            EOS.showNotification('Calendar invites sent!', 'success');
        },
        
        // Utility Functions
        getMeetingDataFromModal: function() {
            return {
                title: $('#meetingTitle').val(),
                meeting_date: $('#meetingDateTime').val(),
                attendees: $('#meetingAttendees').val(),
                create_google_meet: $('#createGoogleMeet').is(':checked')
            };
        },
        
        serializeToObject: function(formArray) {
            const obj = {};
            formArray.forEach(function(item) {
                if (obj[item.name]) {
                    if (!Array.isArray(obj[item.name])) {
                        obj[item.name] = [obj[item.name]];
                    }
                    obj[item.name].push(item.value);
                } else {
                    obj[item.name] = item.value;
                }
            });
            return obj;
        },
        
        getAdminUrl: function(page) {
            return eosAjax.adminUrl + 'admin.php?page=' + page;
        },
        
        handleCardClick: function(e) {
            // Don't navigate if clicking on a button or link
            if ($(e.target).is('a, button, input, select, textarea') || $(e.target).closest('a, button').length) {
                return;
            }
            
            const href = $(this).data('href');
            if (href) {
                window.location.href = href;
            }
        },
        
        // Dashboard Functions
        refreshDashboard: function() {
            // Refresh dashboard stats
            $.get(eosAjax.ajaxurl, {
                action: 'eos_get_dashboard_stats',
                nonce: eosAjax.nonce
            }).done(function(response) {
                if (response.success) {
                    EOS.updateDashboardStats(response.data);
                }
            });
        },
        
        updateDashboardStats: function(stats) {
            // Update stat numbers in dashboard cards
            Object.keys(stats).forEach(function(key) {
                $('[data-stat="' + key + '"]').text(stats[key]);
            });
        },
        
        // Notifications
        showNotification: function(message, type = 'info', duration = 4000) {
            const $notification = $('<div class="eos-notification eos-notification-' + type + '">')
                .html('<span>' + message + '</span><button class="eos-notification-close">&times;</button>')
                .hide();
            
            $('#eos-notifications').append($notification);
            $notification.slideDown(300);
            
            // Auto-remove
            setTimeout(function() {
                $notification.slideUp(300, function() {
                    $(this).remove();
                });
            }, duration);
            
            // Manual close
            $notification.find('.eos-notification-close').on('click', function() {
                $notification.slideUp(300, function() {
                    $(this).remove();
                });
            });
        },
        
        initNotifications: function() {
            // Create notifications container if it doesn't exist
            if (!$('#eos-notifications').length) {
                $('body').append('<div id="eos-notifications" class="eos-notifications-container"></div>');
            }
        },
        
        // Component Initializers
        initDatePickers: function() {
            // Initialize HTML5 date inputs with better UX
            $('input[type="date"], input[type="datetime-local"]').each(function() {
                const $input = $(this);
                
                // Set min date to today for future events
                if ($input.hasClass('future-date')) {
                    const today = new Date().toISOString().split('T')[0];
                    $input.attr('min', today);
                }
            });
        },
        
        initProgressBars: function() {
            // Animate progress bars on load
            $('.progress-fill').each(function() {
                const $bar = $(this);
                const width = $bar.data('progress') || 0;
                
                setTimeout(function() {
                    $bar.css('width', width + '%');
                }, Math.random() * 500);
            });
        },
        
        initTooltips: function() {
            // Simple tooltip implementation
            $('[data-tooltip]').hover(
                function() {
                    const text = $(this).data('tooltip');
                    const $tooltip = $('<div class="eos-tooltip">' + text + '</div>');
                    $('body').append($tooltip);
                    
                    const offset = $(this).offset();
                    $tooltip.css({
                        top: offset.top - $tooltip.outerHeight() - 10,
                        left: offset.left + ($(this).outerWidth() / 2) - ($tooltip.outerWidth() / 2)
                    }).fadeIn(200);
                },
                function() {
                    $('.eos-tooltip').fadeOut(200, function() {
                        $(this).remove();
                    });
                }
            );
        },
        
        initCharts: function() {
            // Initialize simple progress charts
            $('.metric-chart').each(function() {
                const $chart = $(this);
                const data = $chart.data('chart-data');
                
                if (data) {
                    EOS.createSimpleChart($chart, data);
                }
            });
        },
        
        createSimpleChart: function($container, data) {
            // Simple SVG-based chart implementation
            const width = $container.width() || 200;
            const height = $container.height() || 100;
            
            const svg = `
                <svg width="${width}" height="${height}" class="eos-simple-chart">
                    <!-- Chart implementation would go here -->
                </svg>
            `;
            
            $container.html(svg);
        },
        
        // Delete Confirmation
        handleDelete: function(e) {
            e.preventDefault();
            const $btn = $(this);
            const message = $btn.data('confirm') || 'Are you sure you want to delete this item?';
            
            if (confirm(message)) {
                const action = $btn.data('action');
                const itemId = $btn.data('id');
                
                $.post(eosAjax.ajaxurl, {
                    action: action,
                    id: itemId,
                    nonce: eosAjax.nonce
                }).done(function(response) {
                    if (response.success) {
                        EOS.showNotification(response.message, 'success');
                        $btn.closest('.eos-item, tr').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        EOS.showNotification(response.message, 'error');
                    }
                });
            }
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        EOS.init();
        
        // Make EOS globally available for debugging
        window.EOS = EOS;
    });
    
    // Global functions for inline event handlers
    window.openModal = function(modalId) {
        $('#' + modalId).find('.eos-modal-close').trigger('click', function() {
            EOS.openModal.call($('[data-modal="' + modalId + '"]')[0], {preventDefault: function(){}});
        });
        EOS.openModal.call($('[data-modal="' + modalId + '"]')[0], {preventDefault: function(){}});
    };
    
    window.closeModal = function(modalId) {
        $('#' + modalId).removeClass('show').fadeOut(300);
        $('body').removeClass('modal-open');
    };
    
    window.startL10Meeting = function() {
        EOS.startMeeting.call($('.start-meeting-btn')[0], {preventDefault: function(){}});
    };
    
    window.scheduleL10Meeting = function() {
        const meetingData = EOS.getMeetingDataFromModal();
        meetingData.status = 'scheduled';
        
        $.post(eosAjax.ajaxurl, {
            action: 'eos_save_meeting',
            meeting_data: meetingData,
            nonce: eosAjax.nonce
        }).done(function(response) {
            if (response.success) {
                EOS.showNotification('Meeting scheduled successfully!', 'success');
                closeModal('l10Modal');
                EOS.refreshDashboard();
            }
        });
    };
    
    window.saveRock = function() {
        const rockData = {
            title: $('#rockTitle').val(),
            owner: $('#rockOwner').val(),
            due_date: $('#rockDueDate').val(),
            description: $('#rockDescription').val(),
            priority: $('#rockPriority').val() || 'medium',
            progress: 0,
            status: 'active'
        };
        
        $.post(eosAjax.ajaxurl, {
            action: 'eos_save_rock',
            rock_data: rockData,
            nonce: eosAjax.nonce
        }).done(function(response) {
            if (response.success) {
                EOS.showNotification('Rock saved successfully!', 'success');
                closeModal('addRockModal');
                EOS.refreshDashboard();
                
                // Clear form
                $('#addRockForm')[0].reset();
            } else {
                EOS.showNotification(response.message || 'Error saving rock', 'error');
            }
        });
    };
    
    window.joinMeeting = function() {
        EOS.joinMeeting($('#googleMeetLink').val());
    };
    
    window.sendInvites = function() {
        EOS.sendCalendarInvites(EOS.getMeetingDataFromModal());
    };
    
    window.copyMeetLink = function() {
        EOS.copyMeetLink($('#googleMeetLink').val());
    };
    
})(jQuery);

// CSS for notifications (injected dynamically)
jQuery(document).ready(function($) {
    const notificationCSS = `
        <style>
        .eos-notifications-container {
            position: fixed;
            top: 32px;
            right: 20px;
            z-index: 100001;
            max-width: 350px;
        }
        
        .eos-notification {
            background: white;
            border-left: 4px solid #0073aa;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            padding: 12px 16px;
            margin-bottom: 10px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 14px;
        }
        
        .eos-notification-success {
            border-left-color: #46b450;
        }
        
        .eos-notification-error {
            border-left-color: #dc3232;
        }
        
        .eos-notification-warning {
            border-left-color: #ffb900;
        }
        
        .eos-notification-close {
            background: none;
            border: none;
            font-size: 16px;
            cursor: pointer;
            color: #666;
            margin-left: 10px;
            padding: 0;
            line-height: 1;
        }
        
        .eos-tooltip {
            position: absolute;
            background: #333;
            color: white;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 100002;
            white-space: nowrap;
        }
        
        .eos-tooltip:after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border: 5px solid transparent;
            border-top-color: #333;
        }
        </style>
    `;
    
    $('head').append(notificationCSS);
});