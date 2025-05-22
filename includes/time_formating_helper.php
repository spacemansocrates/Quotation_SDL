<?php
function formatRelativeTime($timestamp) {
    if (!$timestamp) return 'Never';

    $now = new DateTime();
    $past = new DateTime($timestamp);
    $diff = $now->diff($past);

    // Exact timestamp for tooltip
    $exactTimestamp = date('Y-m-d H:i:s', strtotime($timestamp));

    // Determine relative time string
    if ($diff->y > 0) {
        $relativeTime = $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    } elseif ($diff->m > 0) {
        $relativeTime = $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    } elseif ($diff->d > 0) {
        $relativeTime = $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    } elseif ($diff->h > 0) {
        $relativeTime = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    } elseif ($diff->i > 0) {
        $relativeTime = $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    } else {
        $relativeTime = 'Just now';
    }

    return "<span class='timestamp' title='Exact time'>{$relativeTime}<span class='tooltip'>{$exactTimestamp}</span></span>";
}

// Modify the role display to add color classes
function formatRoleDisplay($role) {
    $formattedRole = ucfirst($role);
    return "<span class='role-{$role}'>{$formattedRole}</span>";
}

// Modify the status display
function formatStatusDisplay($isActive) {
    return $isActive 
        ? "<span class='status-active'>Active</span>" 
        : "<span class='status-inactive'>Inactive</span>";
}
?>