function togglePassword() {
    const password = document.getElementById('password');

    if (!password) return;

    password.type =
        password.type === 'password' ? 'text' : 'password';
}

// CAPTCHA REFRESH
document.addEventListener('DOMContentLoaded', function () {

    const refreshButton = document.querySelector('.captcha-refresh');
    const captchaDisplay = document.querySelector('.captcha-code');
    const captchaInput = document.querySelector('input[name="captcha"]');

    if (refreshButton && captchaDisplay) {

        refreshButton.addEventListener('click', async function () {

            try {
                refreshButton.disabled = true;

                const response = await fetch(
                    '/Baug_Member_Savings_and_Loan_System/auth/refresh_captcha.php',
                    {
                        method: 'GET',
                        cache: 'no-store'
                    }
                );

                const data = await response.json();

                if (data.success) {
                    captchaDisplay.textContent = data.captcha;

                    if (captchaInput) {
                        captchaInput.value = '';
                    }
                } else {
                    alert('Unable to refresh CAPTCHA. Please try again.');
                }

            } catch (error) {
                alert('An error occurred while refreshing CAPTCHA.');
            } finally {
                refreshButton.disabled = false;
            }

        });
    }
});

// ==========================================
// SESSION IDLE WARNING
// ==========================================

document.addEventListener('DOMContentLoaded', function () {

    // Only run on authenticated pages.
    const sessionWarningEnabled =
        document.body.dataset.sessionWarning === 'enabled';

    if (!sessionWarningEnabled) return;

    const IDLE_LIMIT = 6 * 60 * 1000;
    const WARNING_TIME = 5 * 60 * 1000;

    const ACTIVITY_URL =
        '/Baug_Member_Savings_and_Loan_System/auth/session_activity.php';

    const LOGIN_URL =
        '/Baug_Member_Savings_and_Loan_System/auth/login.php?timeout=1';

    let lastActivity = Date.now();
    let warningVisible = false;
    let activityRequestRunning = false;
    let lastServerUpdate = 0;

    // Create warning modal.
    const modal = document.createElement('div');

    modal.id = 'session-warning-modal';

    modal.style.cssText = `
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.55);
        z-index: 99999;
        justify-content: center;
        align-items: center;
        padding: 20px;
    `;

    modal.innerHTML = `
        <div style="
            background: white;
            width: 100%;
            max-width: 420px;
            padding: 30px;
            border-radius: 14px;
            text-align: center;
            box-shadow: 0 10px 35px rgba(0,0,0,0.2);
        ">

            <h2 style="color:#176b3a; margin-top:0;">
                Session Expiring Soon
            </h2>

            <p>
                You have been inactive for a while.
                Your session will expire in:
            </p>

            <h1 id="session-countdown"
                style="color:#c0392b; font-size:36px;">
                60
            </h1>

            <p>seconds</p>

            <button type="button"
                id="continue-session"
                style="
                    background:#176b3a;
                    color:white;
                    border:none;
                    padding:13px 24px;
                    border-radius:7px;
                    cursor:pointer;
                    font-weight:bold;
                ">
                Continue Session
            </button>

        </div>
    `;

    document.body.appendChild(modal);

    const countdownDisplay =
        document.getElementById('session-countdown');

    const continueButton =
        document.getElementById('continue-session');

    // Update server-side session activity.
    async function updateServerActivity() {

        if (activityRequestRunning) return false;

        activityRequestRunning = true;

        try {

            const response = await fetch(ACTIVITY_URL, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                window.location.href = LOGIN_URL;
                return false;
            }

            const data = await response.json();

            if (!data.success) {
                window.location.href = LOGIN_URL;
                return false;
            }

            lastServerUpdate = Date.now();

            return true;

        } catch (error) {

            console.error('Session activity error:', error);

            return false;

        } finally {

            activityRequestRunning = false;

        }
    }

    // Reset local activity timer.
    async function recordActivity() {

        lastActivity = Date.now();

        if (warningVisible) {
            warningVisible = false;
            modal.style.display = 'none';
        }

        // Update server at most once every 30 seconds.
        if (Date.now() - lastServerUpdate >= 30000) {
            await updateServerActivity();
        }
    }

    // Detect user activity.
    [
        'mousemove',
        'mousedown',
        'keydown',
        'scroll',
        'touchstart',
        'click'
    ].forEach(function (eventName) {

        document.addEventListener(
            eventName,
            recordActivity,
            { passive: true }
        );

    });

    // Continue session button.
    continueButton.addEventListener('click', async function () {

        continueButton.disabled = true;

        const success = await updateServerActivity();

        if (success) {

            lastActivity = Date.now();

            warningVisible = false;

            modal.style.display = 'none';

        }

        continueButton.disabled = false;

    });

    // Session countdown.
    setInterval(function () {

        const inactiveTime = Date.now() - lastActivity;

        const remainingTime = IDLE_LIMIT - inactiveTime;

        if (remainingTime <= 0) {

            window.location.href = LOGIN_URL;

            return;
        }

        if (inactiveTime >= WARNING_TIME) {

            warningVisible = true;

            modal.style.display = 'flex';

            countdownDisplay.textContent =
                Math.ceil(remainingTime / 1000);

        }

    }, 1000);

});

function toggleLoginPassword() {

    const password = document.getElementById('loginPassword');
    const button = document.querySelector('.toggle-password');

    if (!password || !button) return;

    if (password.type === 'password') {

        password.type = 'text';
        button.textContent = 'Hide';
        button.setAttribute('aria-label', 'Hide password');

    } else {

        password.type = 'password';
        button.textContent = 'Show';
        button.setAttribute('aria-label', 'Show password');

    }
}

document.addEventListener('DOMContentLoaded', function () {

    const timer = document.getElementById('lockoutTimer');
    const loginButton = document.getElementById('loginButton');
    const registerLink = document.getElementById('registerLink');

    if (!timer) return;

    let remaining = parseInt(timer.textContent, 10);

    const countdown = setInterval(function () {

        remaining--;

        timer.textContent = Math.max(remaining, 0);

        if (remaining <= 0) {

            clearInterval(countdown);

            if (loginButton) {
                loginButton.disabled = false;
            }

            if (registerLink && registerLink.tagName === 'SPAN') {

                const link = document.createElement('a');

                link.href = 'register.php';
                link.id = 'registerLink';
                link.textContent = 'Register Here';

                registerLink.replaceWith(link);
            }
        }

    }, 1000);

});