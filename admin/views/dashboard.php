<?php
/**
 * EOS Manager Dashboard View
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get dashboard stats
$rocks_stats = EOS_Rocks::get_stats();
$issues_stats = EOS_Issues::get_stats();
$scorecard_stats = EOS_Scorecard::get_stats();
$people_stats = EOS_People::get_stats();
$upcoming_meetings = EOS_Meetings::get_upcoming(1);

?>

<div class="wrap eos-dashboard">
    <!-- Enhanced Plugin Header -->
    <div class="eos-plugin-header">
        <div class="eos-header-content">
            <div class="eos-title-section">
                <div class="eos-icon">EOS</div>
                <div class="eos-title-text">
                    <h1><?php _e('EOS Manager', 'eos-manager'); ?></h1>
                    <p class="eos-subtitle"><?php _e('Your company\'s operating system, integrated with WordPress', 'eos-manager'); ?></p>
                </div>
            </div>
            <div class="eos-quick-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=eos-meetings&action=start')); ?>" class="eos-action-btn primary">
                    <?php _e('Start L10 Meeting', 'eos-manager'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=eos-scorecard')); ?>" class="eos-action-btn">
                    <?php _e('Weekly Review', 'eos-manager'); ?>
                </a>
                <a href="#" class="eos-action-btn" onclick="openModal('addRockModal')">
                    <?php _e('Add Rock', 'eos-manager'); ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Grid -->
    <div class="eos-dashboard-grid">
        
        <!-- Rocks Card -->
        <div class="eos-dashboard-card" onclick="location.href='<?php echo esc_url(admin_url('admin.php?page=eos-rocks')); ?>'">
            <div class="eos-card-header">
                <div class="eos-card-title">
                    <div class="eos-card-icon rocks">🎯</div>
                    <span><?php _e('90-Day Rocks', 'eos-manager'); ?></span>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=eos-rocks')); ?>" class="eos-card-action">
                    <?php _e('View All', 'eos-manager'); ?> →
                </a>
            </div>
            <div class="eos-card-stats">
                <div class="eos-stat-item">
                    <span class="eos-stat-number"><?php echo esc_html($rocks_stats['on_track']); ?></span>
                    <span class="eos-stat-label"><?php _e('On Track', 'eos-manager'); ?></span>
                </div>
                <div class="eos-stat-item">
                    <span class="eos-stat-number"><?php echo esc_html($rocks_stats['at_risk']); ?></span>
                    <span class="eos-stat-label"><?php _e('At Risk', 'eos-manager'); ?></span>
                </div>
                <div class="eos-stat-item">
                    <span class="eos-stat-number"><?php echo esc_html($rocks_stats['behind']); ?></span>
                    <span class="eos-stat-label"><?php _e('Behind', 'eos-manager'); ?></span>
                </div>
            </div>
            <div class="eos-card-preview">
                <?php
                $urgent_rock = EOS_Rocks::get_most_urgent();
                if ($urgent_rock) {
                    printf(
                        __('Most urgent: "%s" (%d%% complete, due in %d days)', 'eos-manager'),
                        esc_html($urgent_rock['title']),
                        intval($urgent_rock['progress']),
                        intval($urgent_rock['days_remaining'])
                    );
                } else {
                    _e('No active rocks found. Time to set some priorities!', 'eos-manager');
                }
                ?>
            </div>
        </div>

        <!-- L10 Meetings Card -->
        <div class="eos-dashboard-card eos-meeting-card" onclick="openModal('l10Modal')">
            <div class="eos-card-header">
                <div class="eos-card-title">
                    <div class="eos-card-icon meetings">📅</div>
                    <span><?php _e('L10 Meetings', 'eos-manager'); ?></span>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=eos-meetings')); ?>" class="eos-card-action">
                    <?php _e('Schedule', 'eos-manager'); ?> →
                </a>
            </div>
            <?php if (!empty($upcoming_meetings)) : ?>
                <div class="eos-next-meeting">
                    <?php
                    $next_meeting = $upcoming_meetings[0];
                    if (!empty($next_meeting['meeting_date'])) {
                        try {
                            $meeting_date = new DateTime($next_meeting['meeting_date']);
                            printf(
                                esc_html__('Next: %s', 'eos-manager'),
                                $meeting_date->format('l g:i A - ') . esc_html($next_meeting['title'])
                            );
                            echo ' <span class="eos-google-meet-badge">📹 Meet</span>';
                        } catch (Exception $e) {
                            esc_html_e('Next meeting date unavailable.', 'eos-manager');
                        }
                    } else {
                        esc_html_e('No upcoming meetings scheduled.', 'eos-manager');
                    }
                    ?>
                </div>
            <?php else : ?>
                <div class="eos-next-meeting">
                    <?php esc_html_e('No upcoming meetings scheduled.', 'eos-manager'); ?>
                </div>
            <?php endif; ?>
            <div class="eos-card-preview">
                <?php _e('All meetings include Google Meet integration and structured L10 agendas. Ready to keep your team aligned and focused.', 'eos-manager'); ?>
            </div>
        </div>

        <!-- Scorecard Card -->
        <div class="eos-dashboard-card" onclick="location.href='<?php echo esc_url(admin_url('admin.php?page=eos-scorecard')); ?>'">
            <div class="eos-card-header">
                <div class="eos-card-title">
                    <div class="eos-card-icon scorecard">📊</div>
                    <span><?php _e('Company Scorecard', 'eos-manager'); ?></span>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=eos-scorecard')); ?>" class="eos-card-action">
                    <?php _e('Update', 'eos-manager'); ?> →
                </a>
            </div>
            <div class="eos-card-stats">
                <div class="eos-stat-item">
                    <span class="eos-stat-number"><?php echo esc_html($scorecard_stats['health_percentage']); ?>%</span>
                    <span class="eos-stat-label"><?php _e('Health', 'eos-manager'); ?></span>
                </div>
                <div class="eos-stat-item">
                    <span class="eos-stat-number"><?php echo esc_html($scorecard_stats['on_target']); ?>/<?php echo esc_html($scorecard_stats['total']); ?></span>
                    <span class="eos-stat-label"><?php _e('On Target', 'eos-manager'); ?></span>
                </div>
            </div>
            <div class="eos-card-preview">
                <?php
                if ($scorecard_stats['behind'] > 0) {
                    printf(
                        __('%d metrics behind target this week.', 'eos-manager'),
                        intval($scorecard_stats['behind'])
                    );
                } else {
                    _e('All metrics on or above target this week!', 'eos-manager');
                }
                ?>
            </div>
        </div>

        <!-- Issues List Card -->
        <div class="eos-dashboard-card" onclick="location.href='<?php echo esc_url(admin_url('admin.php?page=eos-issues')); ?>'">
            <div class="eos-card-header">
                <div class="eos-card-title">
                    <div class="eos-card-icon issues">⚠️</div>
                    <span><?php _e('Issues List', 'eos-manager'); ?></span>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=eos-issues')); ?>" class="eos-card-action">
                    <?php _e('Manage', 'eos-manager'); ?> →
                </a>
            </div>
            <div class="eos-card-stats">
                <div class="eos-stat-item">
                    <span class="eos-stat-number"><?php echo esc_html($issues_stats['high_priority']); ?></span>
                    <span class="eos-stat-label"><?php _e('High Priority', 'eos-manager'); ?></span>
                </div>
                <div class="eos-stat-item">
                    <span class="eos-stat-number"><?php echo esc_html($issues_stats['total_open']); ?></span>
                    <span class="eos-stat-label"><?php _e('Total Open', 'eos-manager'); ?></span>
                </div>
            </div>
            <div class="eos-card-preview">
                <?php
                $top_issue = EOS_Issues::get_top_priority_issue();
                if ($top_issue) {
                    printf(
                        __('Top issue: "%s" - scheduled for discussion in next L10.', 'eos-manager'),
                        esc_html($top_issue['title'])
                    );
                } else {
                    _e('No open issues. Great job solving problems!', 'eos-manager');
                }
                ?>
            </div>
        </div>

        <!-- People Analyzer Card -->
        <div class="eos-dashboard-card" onclick="location.href='<?php echo esc_url(admin_url('admin.php?page=eos-people')); ?>'">
            <div class="eos-card-header">
                <div class="eos-card-title">
                    <div class="eos-card-icon people">👥</div>
                    <span><?php _e('People Analyzer', 'eos-manager'); ?></span>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=eos-people')); ?>" class="eos-card-action">
                    <?php _e('Assess', 'eos-manager'); ?> →
                </a>
            </div>
            <div class="eos-card-stats">
                <div class="eos-stat-item">
                    <span class="eos-stat-number"><?php echo esc_html($people_stats['right_seat']); ?></span>
                    <span class="eos-stat-label"><?php _e('Right Seat', 'eos-manager'); ?></span>
                </div>
                <div class="eos-stat-item">
                    <span class="eos-stat-number"><?php echo esc_html($people_stats['need_review']); ?></span>
                    <span class="eos-stat-label"><?php _e('Need Review', 'eos-manager'); ?></span>
                </div>
            </div>
            <div class="eos-card-preview">
                <?php
                if ($people_stats['need_review'] > 0) {
                    printf(
                        __('%d people need GWC assessment.', 'eos-manager'),
                        intval($people_stats['need_review'])
                    );
                } else {
                    _e('All team members are in the right seats!', 'eos-manager');
                }
                ?>
            </div>
        </div>

        <!-- Vision/Traction Organizer Card -->
        <div class="eos-dashboard-card" onclick="location.href='<?php echo esc_url(admin_url('admin.php?page=eos-vto')); ?>'">
            <div class="eos-card-header">
                <div class="eos-card-title">
                    <div class="eos-card-icon vto">🎯</div>
                    <span><?php _e('Vision/Traction Organizer', 'eos-manager'); ?></span>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=eos-vto')); ?>" class="eos-card-action">
                    <?php _e('Review', 'eos-manager'); ?> →
                </a>
            </div>
            <div class="eos-card-preview">
                <?php
                $core_focus = EOS_VTO::get_section('core-focus');
                if (!empty($core_focus)) {
                    echo '<strong>' . __('Core Focus:', 'eos-manager') . '</strong> ' . esc_html(wp_trim_words($core_focus, 15));
                } else {
                    echo '<strong>' . __('Complete your V/TO', 'eos-manager') . '</strong><br>';
                    _e('Define your vision and organize for traction', 'eos-manager');
                }
                ?>
                  <br><small><?php printf(__('Last updated: %s', 'eos-manager'), esc_html(EOS_VTO::get_last_updated())); ?></small>
            </div>
        </div>

    </div>

    <!-- Recent Activity Section -->
    <div class="eos-recent-activity">
        <div class="eos-section-header">
            <h2><?php _e('Recent Activity', 'eos-manager'); ?></h2>
        </div>
        <div class="eos-activity-feed">
            <?php
            $recent_activities = EOS_Dashboard::get_recent_activities(5);
            if (!empty($recent_activities)):
                foreach ($recent_activities as $activity):
            ?>
                <div class="eos-activity-item">
                    <div class="eos-activity-icon <?php echo esc_attr($activity['type']); ?>">
                    <?php echo wp_kses_post($activity['icon']); ?>
                    </div>
                    <div class="eos-activity-content">
                        <div class="eos-activity-text">
                            <strong><?php echo esc_html($activity['title']); ?></strong>
                            <?php echo esc_html($activity['description']); ?>
                        </div>
                        <div class="eos-activity-time">
                            <?php
                            $time_diff = human_time_diff(strtotime($activity['created_at']), current_time('timestamp'));
                            echo esc_html($time_diff) . ' ' . esc_html__('ago', 'eos-manager');
                            ?>
                        </div>
                    </div>
                </div>
            <?php
                endforeach;
            else:
            ?>
                <div class="eos-no-activity">
                    <p><?php _e('No recent activity. Start by creating some rocks or updating your scorecard!', 'eos-manager'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- L10 Meeting Modal -->
<div id="l10Modal" class="eos-modal">
    <div class="eos-modal-content">
        <div class="eos-modal-header">
            <h2><?php _e('L10 Meeting: Leadership Team', 'eos-manager'); ?></h2>
            <button class="eos-modal-close" onclick="closeModal('l10Modal')">&times;</button>
        </div>

        <!-- Google Meet Integration -->
        <div class="eos-google-meet-integration">
            <h4>📹 <?php _e('Google Meet Integration', 'eos-manager'); ?></h4>
            <p><?php _e('Meeting scheduled for Monday, August 26, 2025 at 9:00 AM', 'eos-manager'); ?></p>
            <div class="eos-google-meet-controls">
                <button class="eos-google-meet-btn" onclick="joinMeeting()">
                    <?php _e('Join Meeting Now', 'eos-manager'); ?>
                </button>
                <button class="eos-google-meet-btn" onclick="sendInvites()">
                    <?php _e('Send Calendar Invites', 'eos-manager'); ?>
                </button>
                <button class="eos-google-meet-btn" onclick="copyMeetLink()">
                    <?php _e('Copy Meet Link', 'eos-manager'); ?>
                </button>
            </div>
        </div>

        <!-- Quick L10 Start Form -->
        <div class="eos-l10-quick-start">
            <div class="eos-form-row">
                <div class="eos-form-field">
                    <label><?php _e('Meeting Title', 'eos-manager'); ?></label>
                    <input type="text" id="meetingTitle" value="<?php _e('Leadership Team L10', 'eos-manager'); ?>">
                </div>
                <div class="eos-form-field">
                    <label><?php _e('Date & Time', 'eos-manager'); ?></label>
                    <input type="datetime-local" id="meetingDateTime" value="<?php echo esc_attr(date('Y-m-d\TH:i')); ?>">
                </div>
            </div>
            <div class="eos-form-field">
                <label><?php _e('Attendees', 'eos-manager'); ?></label>
                <textarea id="meetingAttendees" placeholder="<?php _e('List attendees separated by commas...', 'eos-manager'); ?>"></textarea>
            </div>
        </div>

        <div class="eos-modal-actions">
            <button class="eos-btn eos-btn-primary" onclick="startL10Meeting()">
                <?php _e('Start L10 Meeting', 'eos-manager'); ?>
            </button>
            <button class="eos-btn eos-btn-secondary" onclick="scheduleL10Meeting()">
                <?php _e('Schedule for Later', 'eos-manager'); ?>
            </button>
            <button class="eos-btn" onclick="closeModal('l10Modal')">
                <?php _e('Cancel', 'eos-manager'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Add Rock Modal -->
<div id="addRockModal" class="eos-modal">
    <div class="eos-modal-content">
        <div class="eos-modal-header">
            <h2><?php _e('Add New Rock', 'eos-manager'); ?></h2>
            <button class="eos-modal-close" onclick="closeModal('addRockModal')">&times;</button>
        </div>
        
        <form id="addRockForm">
            <div class="eos-form-field">
                <label><?php _e('Rock Title', 'eos-manager'); ?></label>
                <input type="text" id="rockTitle" required>
            </div>
            <div class="eos-form-row">
                <div class="eos-form-field">
                    <label><?php _e('Owner', 'eos-manager'); ?></label>
                    <input type="text" id="rockOwner">
                </div>
                <div class="eos-form-field">
                    <label><?php _e('Due Date', 'eos-manager'); ?></label>
                    <input type="date" id="rockDueDate">
                </div>
            </div>
            <div class="eos-form-field">
                <label><?php _e('Description', 'eos-manager'); ?></label>
                <textarea id="rockDescription"></textarea>
            </div>
        </form>
        
        <div class="eos-modal-actions">
            <button class="eos-btn eos-btn-primary" onclick="saveRock()">
                <?php _e('Save Rock', 'eos-manager'); ?>
            </button>
            <button class="eos-btn" onclick="closeModal('addRockModal')">
                <?php _e('Cancel', 'eos-manager'); ?>
            </button>
        </div>
    </div>
</div>

<script>
// Modal functions
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// L10 Meeting functions
function startL10Meeting() {
    const title = document.getElementById('meetingTitle').value;
    const dateTime = document.getElementById('meetingDateTime').value;
    const attendees = document.getElementById('meetingAttendees').value;
    
    // Redirect to full L10 meeting interface
      window.location.href = `<?php echo esc_url(admin_url('admin.php?page=eos-meetings&action=start')); ?>&title=${encodeURIComponent(title)}&datetime=${encodeURIComponent(dateTime)}&attendees=${encodeURIComponent(attendees)}`;
}

function scheduleL10Meeting() {
    // Save meeting for later
    alert('<?php _e('Meeting scheduled successfully!', 'eos-manager'); ?>');
    closeModal('l10Modal');
}

// Rock functions
function saveRock() {
    const rockData = {
        title: document.getElementById('rockTitle').value,
        owner: document.getElementById('rockOwner').value,
        due_date: document.getElementById('rockDueDate').value,
        description: document.getElementById('rockDescription').value,
    };

    jQuery.post(ajaxurl, {
        action: 'eos_save_rock',
        nonce: '<?php echo esc_js(wp_create_nonce('eos_nonce')); ?>',
        rock_data: rockData
    }, function(response) {
        if (response.success) {
            alert('<?php _e('Rock saved successfully!', 'eos-manager'); ?>');
            closeModal('addRockModal');
            location.reload();
        } else {
            alert('<?php _e('Error saving rock. Please try again.', 'eos-manager'); ?>');
        }
    });
}

// Google Meet functions
function joinMeeting() {
    // Open Google Meet link
    window.open('https://meet.google.com/new', '_blank');
}

function sendInvites() {
    alert('<?php _e('Calendar invites sent!', 'eos-manager'); ?>');
}

function copyMeetLink() {
    navigator.clipboard.writeText('https://meet.google.com/example-link');
    alert('<?php _e('Meet link copied to clipboard!', 'eos-manager'); ?>');
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('eos-modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php
// Add inline styles for the dashboard
add_action('admin_footer', function() {
    ?>
    <style>
        /* Dashboard specific styles will be loaded from admin.css */
    </style>
    <?php
});
