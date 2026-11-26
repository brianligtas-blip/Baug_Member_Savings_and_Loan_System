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

    $successfulLogins = (int) $pdo->query("
        SELECT COUNT(*)
        FROM activity_log
        WHERE LOWER(event_type) = 'login_success'
        AND created_at >= NOW() - INTERVAL 24 HOUR
    ")->fetchColumn();

    $failedLogins = (int) $pdo->query("
        SELECT COUNT(*)
        FROM activity_log
        WHERE LOWER(event_type) = 'login_failed'
        AND created_at >= NOW() - INTERVAL 24 HOUR
    ")->fetchColumn();

    $lockedAccounts = (int) $pdo->query("
        SELECT COUNT(*)
        FROM users
        WHERE account_status = 'locked'
        AND locked_until > NOW()
    ")->fetchColumn();

    $blockedIps = (int) $pdo->query("
        SELECT COUNT(*)
        FROM ip_blocks
        WHERE blocked_until > NOW()
    ")->fetchColumn();

    $otpSent = (int) $pdo->query("
        SELECT COUNT(*)
        FROM activity_log
        WHERE event_type = 'OTP_SENT'
        AND created_at >= NOW() - INTERVAL 24 HOUR
    ")->fetchColumn();

    $otpFailed = (int) $pdo->query("
        SELECT COUNT(*)
        FROM activity_log
        WHERE event_type = 'OTP_FAILED'
        AND created_at >= NOW() - INTERVAL 24 HOUR
    ")->fetchColumn();

    $alertEmailsSent = (int) $pdo->query("
        SELECT COUNT(*)
        FROM activity_log
        WHERE event_type = 'ALERT_EMAIL_SENT'
        AND created_at >= NOW() - INTERVAL 24 HOUR
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
    $dateFrom = trim($_GET['date_from'] ?? '');
    $dateTo = trim($_GET['date_to'] ?? '');
    $usernameFilter = trim($_GET['username'] ?? '');
    $ipFilter = trim($_GET['ip_address'] ?? '');

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
            ON a.user_id = u.id_number
    ";

    $conditions = [];
    $params = [];

    // Event type filter.
    if (
        $eventFilter !== '' &&
        in_array($eventFilter, $allowedEvents, true)
    ) {
        $conditions[] = "a.event_type = :event_type";
        $params[':event_type'] = $eventFilter;
    }

    // Validate date format before applying date filters.
    $validDate = function ($date) {
        if ($date === '') {
            return false;
        }

        $parsed = DateTime::createFromFormat('!Y-m-d', $date);

        return $parsed && $parsed->format('Y-m-d') === $date;
    };

    if ($validDate($dateFrom)) {
        $conditions[] = "a.created_at >= :date_from";
        $params[':date_from'] = $dateFrom . ' 00:00:00';
    }

    if ($validDate($dateTo)) {
        $conditions[] = "a.created_at < :date_to";
        $params[':date_to'] = date(
            'Y-m-d 00:00:00',
            strtotime($dateTo . ' +1 day')
        );
    }

    // Username filter.
    if ($usernameFilter !== '') {
        $conditions[] = "
            COALESCE(u.username, a.username_typed, 'Unknown')
            LIKE :username
        ";
        $params[':username'] = '%' . $usernameFilter . '%';
    }

    // IP address filter.
    if ($ipFilter !== '') {
        $conditions[] = "a.ip_address LIKE :ip_address";
        $params[':ip_address'] = '%' . $ipFilter . '%';
    }

    // Apply all selected filters together.
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= "
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT 50
    ";

    $eventsStmt = $pdo->prepare($sql);
    $eventsStmt->execute($params);

    $events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

    /* ==========================================
       JSON RESPONSE
    ========================================== */

    echo json_encode([
        'success' => true,

                'summary' => [
                    'total_events' => $totalEvents,
                    'successful_logins' => $successfulLogins,
                    'failed_logins' => $failedLogins,
                    'locked_accounts' => $lockedAccounts,
                    'blocked_ips' => $blockedIps,
                    'otp_sent' => $otpSent,
                    'otp_failed' => $otpFailed,
                    'alert_emails_sent' => $alertEmailsSent,
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