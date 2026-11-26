<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_admin();

// Record administrator access.
log_activity(
    $pdo,
    'ADMIN_VIEWED_DASHBOARD',
    current_user()['username'] ?? null,
    (int) current_user()['id'],
    'Administrator viewed the security monitoring dashboard.'
);

$pageTitle = 'Security Monitoring Dashboard';

require __DIR__ . '/../includes/header.php';

?>

<div class="app-layout">

    <?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <main class="app-main">

        <h1 class="page-title">Security Monitoring</h1>

        <p class="muted">
            Monitor security events, failed login attempts,
            blocked IP addresses, and suspicious activities.
        </p>

        <div id="dashboardStatus" class="security-status">
            Loading security monitoring data...
        </div>

        <!-- SUMMARY CARDS -->

            <section class="security-stats-grid">

            <div class="security-stat-card">
                <strong id="successfulLogins">0</strong>
                <small>Successful Logins (24 Hours)</small>
            </div>

            <div class="security-stat-card">
                <strong id="failedLogins">0</strong>
                <small>Failed Logins (24 Hours)</small>
            </div>

            <div class="security-stat-card">
                <strong id="lockedAccounts">0</strong>
                <small>Accounts Locked Now</small>
            </div>

            <div class="security-stat-card">
                <strong id="blockedIps">0</strong>
                <small>IPs Blocked Now</small>
            </div>

            <div class="security-stat-card">
                <strong id="otpSent">0</strong>
                <small>OTPs Sent (24 Hours)</small>
            </div>

            <div class="security-stat-card">
                <strong id="otpFailed">0</strong>
                <small>OTPs Failed (24 Hours)</small>
            </div>

            <div class="security-stat-card">
                <strong id="alertEmailsSent">0</strong>
                <small>Alert Emails Sent (24 Hours)</small>
            </div>

            <div class="security-stat-card">
                <strong id="totalEvents">0</strong>
                <small>Total Events (24 Hours)</small>
            </div>

            <div class="security-stat-card">
                <strong id="securityFlags">0</strong>
                <small>Security Flags (24 Hours)</small>
            </div>

        </section>

        <!-- FAILED LOGIN CHART -->

        <section class="card security-section">

            <h2>Failed Login Attempts</h2>

            <p class="muted">
                Hourly failed login attempts over the last 24 hours.
            </p>

            <div class="security-chart-container">
                <canvas id="failedLoginChart"></canvas>
            </div>

        </section>

        <!-- TOP IP ADDRESSES -->

        <section class="card security-section">

            <h2>Top 5 IP Addresses</h2>

            <div class="table-responsive">

                <table class="security-table">

                    <thead>
                        <tr>
                            <th>IP Address</th>
                            <th>Failed Attempts</th>
                            <th>Block Status</th>
                        </tr>
                    </thead>

                    <tbody id="topIpsBody">
                        <tr>
                            <td colspan="3">Loading...</td>
                        </tr>
                    </tbody>

                </table>

            </div>

        </section>

        <!-- SECURITY FLAGS -->

        <section class="card security-section">

            <h2>Security Monitoring Flags</h2>

            <div class="table-responsive">

                <table class="security-table">

                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>IP Address</th>
                            <th>Description</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>

                    <tbody id="securityFlagsBody">
                        <tr>
                            <td colspan="6">Loading...</td>
                        </tr>
                    </tbody>

                </table>

            </div>

        </section>

        <!-- LATEST ACTIVITY EVENTS -->

        <section class="card security-section">

            <h2>Latest 50 Security Events</h2>

            <div class="security-filters">

            <div>
                <label for="dateFromFilter">Date From</label>
                <input type="date" id="dateFromFilter">
            </div>

            <div>
                <label for="dateToFilter">Date To</label>
                <input type="date" id="dateToFilter">
            </div>

            <div>
                <label for="eventFilter">Event Type</label>
                <select id="eventFilter">
                    <option value="">All Events</option>
                    <option value="LOGIN_SUCCESS">LOGIN_SUCCESS</option>
                    <option value="LOGIN_FAILED">LOGIN_FAILED</option>
                    <option value="OTP_SENT">OTP_SENT</option>
                    <option value="OTP_FAILED">OTP_FAILED</option>
                    <option value="IP_BLOCKED">IP_BLOCKED</option>
                    <option value="PASSWORD_CHANGED">PASSWORD_CHANGED</option>
                    <option value="CAPTCHA_FAILED">CAPTCHA_FAILED</option>
                    <option value="CSRF_REJECTED">CSRF_REJECTED</option>
                    <option value="ADMIN_ACCESS_DENIED">ADMIN_ACCESS_DENIED</option>
                </select>
            </div>

            <div>
                <label for="usernameFilter">Username</label>
                <input type="text" id="usernameFilter" placeholder="Search username">
            </div>

            <div>
                <label for="ipFilter">IP Address</label>
                <input type="text" id="ipFilter" placeholder="Search IP address">
            </div>

            <button type="button" id="refreshDashboard">
                Refresh
            </button>

        </div>

            <div class="table-responsive">

                <table class="security-table">

                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Username</th>
                            <th>IP Address</th>
                            <th>Details</th>
                            <th>Date</th>
                        </tr>
                    </thead>

                    <tbody id="activityEventsBody">
                        <tr>
                            <td colspan="5">Loading...</td>
                        </tr>
                    </tbody>

                </table>

            </div>

        </section>

        <p class="security-last-updated">
            Last updated: <span id="lastUpdated">Not yet updated</span>
        </p>

    </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
<script src="../assets/js/security_dashboard.js" defer></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>