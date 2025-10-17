<?php
// Function to format time difference
function timeAgo($datetime) {
    try {
        // Handle null or empty datetime
        if (empty($datetime)) {
            return 'Recently';
        }
        
        $time = new DateTime($datetime);
        $now = new DateTime();
        $interval = $now->diff($time);

        if ($interval->y > 0 || $interval->m > 0 || $interval->d >= 30) {
            return $time->format('M j, Y');
        }
        if ($interval->d >= 7) {
            $weeks = floor($interval->d / 7);
            return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
        }
        if ($interval->d > 0) {
            return $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
        }
        if ($interval->h > 0) {
            return $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
        }
        if ($interval->i > 0) {
            return $interval->i . ' min' . ($interval->i > 1 ? 's' : '') . ' ago';
        }
        return 'Just now';
    } catch (Exception $e) {
        error_log("timeAgo error: " . $e->getMessage() . " for datetime: " . $datetime);
        // Fallback: try to format the date directly
        if (is_numeric($datetime)) {
            return date('M j, Y', $datetime);
        } else {
            return date('M j, Y', strtotime($datetime));
        }
    }
}

// Function to detect language from tags
function detectLanguage($tags) {
    $tags_array = array_map('trim', explode(',', $tags));
    $lang_map = [
        'js' => 'javascript',
        'javascript' => 'javascript',
        'python' => 'python',
        'php' => 'php',
        'html' => 'xml',
        'css' => 'css',
        'sql' => 'sql',
        'java' => 'java',
        'c#' => 'csharp',
        'c++' => 'cpp',
        'react' => 'javascript',
        'node' => 'javascript',
    ];

    foreach ($tags_array as $tag) {
        $tag_lower = strtolower($tag);
        if (isset($lang_map[$tag_lower])) {
            return $lang_map[$tag_lower];
        }
    }
    return 'plaintext';
}

function formatNumber($number) {
    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M';
    } elseif ($number >= 1000) {
        return round($number / 1000, 1) . 'K';
    }
    return $number;
}
?>