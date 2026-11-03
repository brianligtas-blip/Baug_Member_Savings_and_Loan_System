function togglePassword() {
    const password = document.getElementById('password');

    if (!password) return;

    password.type =
        password.type === 'password' ? 'text' : 'password';
}