<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Only administrators may access security monitoring data.
require_admin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

try {

    /* ==========================================
       SUMMARY STATISTICS
    ========================================== */

    $totalEvents = (int) $pdo->query("
        SELECT COUNT(*)
        FROM activity_log
        WHERE created_at >= NOW() - INTERVAL 24 HOUR
    ")->fetchColumn();

    $failedLogins = (int) $pdo->query("
        SELECT COUNT(*)
        FROM activity_log
        WHERE event_type = 'LOGIN_FAILED'
        AND created_at >= NOW() - INTERVAL 24 HOUR
    ")->fetchColumn();

    $blockedIps = (int) $pdo->query("
        SELECT COUNT(*)
        FROM ip_blocks
        WHERE blocked_until > NOW()
    ")->fetchColumn();

    $securityFlags = (int) $pdo->query("
        SELECT COUNT(*)
        FROM security_flags
        WHERE created_at >= NOW() - INTERVAL 24 HOUR
    ")->fetchColumn();


    /* ==========================================
       HOURLY FAILED LOGIN CHART
    ========================================== */

    $chartStmt = $pdo->query("
        SELECT
            DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') AS event_hour,
            COUNT(*) AS total
        FROM activity_log
        WHERE event_type = 'LOGIN_FAILED'
        AND created_at >= NOW() - INTERVAL 24 HOUR
        GROUP BY event_hour
        ORDER BY event_hour ASC
    ");

    $chartRows = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

    $hourlyFailures = [];

    for ($i = 23; $i >= 0; $i--) {

        $hour = date(
            'Y-m-d H:00:00',
            strtotime("-{$i} hours")
        );

        $hourlyFailures[$hour] = 0;
    }

    foreach ($chartRows as $row) {

        $hour = $row['event_hour'];

        if (array_key_exists($hour, $hourlyFailures)) {
            $hourlyFailures[$hour] = (int) $row['total'];
        }
    }

    $chartLabels = [];
    $chartValues = [];

    foreach ($hourlyFailures as $hour => $total) {

        $chartLabels[] = date('M d H:i', strtotime($hour));
        $chartValues[] = $total;
    }


    /* ==========================================
       TOP 5 IP ADDRESSES
    ========================================== */

    $topIpsStmt = $pdo->query("
        SELECT
            a.ip_address,
            COUNT(*) AS failed_attempts,
            CASE
                WHEN MAX(
                    CASE
                        WHEN i.blocked_until > NOW() THEN 1
                        ELSE 0
                    END
                ) = 1 THEN 1
                ELSE 0
            END AS is_blocked
        FROM activity_log a
        LEFT JOIN ip_blocks i
            ON a.ip_address = i.ip_address
        WHERE a.event_type = 'LOGIN_FAILED'
        AND a.ip_address IS NOT NULL
        AND a.ip_address <> ''
        AND a.created_at >= NOW() - INTERVAL 24 HOUR
        GROUP BY a.ip_address
        ORDER BY failed_attempts DESC
        LIMIT 5
    ");

    $topIps = $topIpsStmt->fetchAll(PDO::FETCH_ASSOC);


    /* ==========================================
       SECURITY MONITORING FLAGS
    ========================================== */

    $flagsStmt = $pdo->query("
        SELECT
            flag_type,
            ip_address,
            description,
            severity,
            is_reviewed,
            created_at
        FROM security_flags
        ORDER BY created_at DESC
        LIMIT 20
    ");

    $flags = $flagsStmt->fetchAll(PDO::FETCH_ASSOC);


    /* ==========================================
       LATEST 50 ACTIVITY EVENTS
    ========================================== */

    $eventFilter = trim($_GET['event_type'] ?? '');

    $allowedEvents = [
        'REGISTERED',
        'ACCOUNT_VERIFIED',
        'LOGIN_SUCCESS',
        'LOGIN_FAILED',
        'OTP_SENT',
        'OTP_FAILED',
        'LOCKOUT_TIER_1',
        'LOCKOUT_TIER_2',
        'LOCKOUT_TIER_3',
        'IP_BLOCKED',
        'ALERT_EMAIL_SENT',
        'RESET_REQUESTED',
        'PASSWORD_CHANGED',
        'CAPTCHA_FAILED',
        'CSRF_REJECTED',
        'ADMIN_VIEWED_DASHBOARD',
        'ADMIN_ACCESS_DENIED'
    ];

    $sql = "
        SELECT
            a.event_type,
            COALESCE(u.username, a.username_typed, 'Unknown') AS username,
            a.ip_address,
            a.details,
            a.created_at
        FROM activity_log a
        LEFT JOIN users u
            ON a.user_id = u.id
    ";

    if (
        $eventFilter !== '' &&
        in_array($eventFilter, $allowedEvents, true)
    ) {

        $sql .= "
            WHERE a.event_type = :event_type
        ";
    }

    $sql .= "
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT 50
    ";

    $eventsStmt = $pdo->prepare($sql);

    if (
        $eventFilter !== '' &&
        in_array($eventFilter, $allowedEvents, true)
    ) {

        $eventsStmt->execute([
            ':event_type' => $eventFilter
        ]);

    } else {

        $eventsStmt->execute();
    }

    $events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);


    /* ==========================================
       JSON RESPONSE
    ========================================== */

    echo json_encode([
        'success' => true,

        'summary' => [
            'total_events' => $totalEvents,
            'failed_logins' => $failedLogins,
            'blocked_ips' => $blockedIps,
            'security_flags' => $securityFlags
        ],

        'chart' => [
            'labels' => $chartLabels,
            'values' => $chartValues
        ],

        'top_ips' => $topIps,

        'flags' => $flags,

        'events' => $events,

        'last_updated' => date('Y-m-d H:i:s')

    ], JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Throwable $e) {

    error_log(
        'Security dashboard data error: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to retrieve security monitoring data.'
    ]);

}