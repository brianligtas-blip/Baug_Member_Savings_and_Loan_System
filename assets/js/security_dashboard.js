'use strict';

document.addEventListener('DOMContentLoaded', () => {

    const dataUrl = 'security_dashboard_data.php';

    const statusElement = document.getElementById('dashboardStatus');
    const eventFilter = document.getElementById('eventFilter');
    const refreshButton = document.getElementById('refreshDashboard');

    let failedLoginChart = null;

    // Safely create table cells using textContent.
    function createCell(value) {

        const cell = document.createElement('td');
        cell.textContent = value ?? '—';

        return cell;
    }

    // Display a message inside a table.
    function showTableMessage(elementId, message, columnCount) {

        const body = document.getElementById(elementId);
        const row = document.createElement('tr');
        const cell = document.createElement('td');

        cell.colSpan = columnCount;
        cell.textContent = message;

        row.appendChild(cell);

        body.replaceChildren(row);
    }

    // Update summary cards.
    function updateSummary(summary) {

        document.getElementById('totalEvents').textContent =
            Number(summary.total_events).toLocaleString();

        document.getElementById('failedLogins').textContent =
            Number(summary.failed_logins).toLocaleString();

        document.getElementById('blockedIps').textContent =
            Number(summary.blocked_ips).toLocaleString();

        document.getElementById('securityFlags').textContent =
            Number(summary.security_flags).toLocaleString();
    }

    // Render the failed-login chart.
    function updateChart(chartData) {

        const canvas = document.getElementById('failedLoginChart');

        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        if (failedLoginChart) {

            failedLoginChart.data.labels = chartData.labels;
            failedLoginChart.data.datasets[0].data = chartData.values;

            failedLoginChart.update();

            return;
        }

        failedLoginChart = new Chart(canvas, {

            type: 'line',

            data: {
                labels: chartData.labels,

                datasets: [{
                    label: 'Failed Login Attempts',
                    data: chartData.values,
                    borderWidth: 2,
                    tension: 0.3,
                    fill: false
                }]
            },

            options: {

                responsive: true,
                maintainAspectRatio: false,

                interaction: {
                    intersect: false,
                    mode: 'index'
                },

                plugins: {
                    legend: {
                        display: true
                    }
                },

                scales: {

                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }

                }

            }

        });
    }

    // Display the top five IP addresses.
    function updateTopIps(ips) {

        const body = document.getElementById('topIpsBody');

        if (!ips.length) {

            showTableMessage(
                'topIpsBody',
                'No failed login attempts recorded in the last 24 hours.',
                3
            );

            return;
        }

        const rows = ips.map(ip => {

            const row = document.createElement('tr');

            row.appendChild(createCell(ip.ip_address));
            row.appendChild(createCell(ip.failed_attempts));

            const statusCell = document.createElement('td');
            const status = document.createElement('span');

            status.textContent = Number(ip.is_blocked) === 1
                ? 'Blocked'
                : 'Not Blocked';

            status.className = Number(ip.is_blocked) === 1
                ? 'status-blocked'
                : 'status-clear';

            statusCell.appendChild(status);
            row.appendChild(statusCell);

            return row;
        });

        body.replaceChildren(...rows);
    }

    // Display security flags.
    function updateFlags(flags) {

        const body = document.getElementById('securityFlagsBody');

        if (!flags.length) {

            showTableMessage(
                'securityFlagsBody',
                'No security monitoring flags recorded.',
                6
            );

            return;
        }

        const rows = flags.map(flag => {

            const row = document.createElement('tr');

            row.appendChild(createCell(flag.flag_type));
            row.appendChild(createCell(flag.ip_address));
            row.appendChild(createCell(flag.description));

            const severityCell = document.createElement('td');
            const severity = document.createElement('span');

            severity.textContent = flag.severity ?? 'medium';
            severity.className = 'severity-' + (
                ['low', 'medium', 'high', 'critical'].includes(flag.severity)
                    ? flag.severity
                    : 'medium'
            );

            severityCell.appendChild(severity);
            row.appendChild(severityCell);

            row.appendChild(
                createCell(Number(flag.is_reviewed) === 1 ? 'Reviewed' : 'Pending')
            );

            row.appendChild(createCell(flag.created_at));

            return row;
        });

        body.replaceChildren(...rows);
    }

    // Display the latest activity events.
    function updateEvents(events) {

        const body = document.getElementById('activityEventsBody');

        if (!events.length) {

            showTableMessage(
                'activityEventsBody',
                'No matching activity events found.',
                5
            );

            return;
        }

        const rows = events.map(event => {

            const row = document.createElement('tr');

            row.appendChild(createCell(event.event_type));
            row.appendChild(createCell(event.username));
            row.appendChild(createCell(event.ip_address));
            row.appendChild(createCell(event.details));
            row.appendChild(createCell(event.created_at));

            return row;
        });

        body.replaceChildren(...rows);
    }

    // Retrieve dashboard data.
    async function loadDashboard() {

        statusElement.textContent = 'Refreshing security monitoring data...';

        refreshButton.disabled = true;

        try {

            const selectedEvent = eventFilter.value;

            const url = new URL(dataUrl, window.location.href);

            if (selectedEvent) {
                url.searchParams.set('event_type', selectedEvent);
            }

            const response = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Server returned HTTP ' + response.status);
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Unable to load dashboard data.');
            }

            updateSummary(data.summary);
            updateChart(data.chart);
            updateTopIps(data.top_ips);
            updateFlags(data.flags);
            updateEvents(data.events);

            document.getElementById('lastUpdated').textContent =
                data.last_updated;

            statusElement.textContent = 'Security monitoring data loaded successfully.';

        } catch (error) {

            console.error('Security dashboard error:', error);

            statusElement.textContent =
                'Unable to load security monitoring data. Please try again.';

        } finally {

            refreshButton.disabled = false;

        }
    }

    // Manual refresh.
    refreshButton.addEventListener('click', loadDashboard);

    // Filter activity events.
    eventFilter.addEventListener('change', loadDashboard);

    // Initial dashboard load.
    loadDashboard();

    // Automatically refresh every 60 seconds.
    setInterval(loadDashboard, 60000);

});