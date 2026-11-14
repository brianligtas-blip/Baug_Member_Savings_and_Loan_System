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

    if (!refreshButton || !captchaDisplay) return;

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

});