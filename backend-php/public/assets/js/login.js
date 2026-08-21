// Port of frontend-react/src/pages/LoginPage.jsx.
//
// One set of fields for everyone. The page used to ask which kind of account
// you were before it had seen the username, which is a question the account
// itself already answers: /login looks the user up, and its response carries
// the role that decides where to land. Picking the wrong tab only ever
// produced an error on a login that was otherwise perfectly valid.

function showLoginError(message) {
  const el = document.getElementById('login-error');
  if (!message) {
    el.classList.add('hidden');
    el.textContent = '';
    return;
  }
  el.textContent = message;
  el.classList.remove('hidden');
}

// Where each role's session belongs. Server-side guards (partials/guard.php)
// enforce this independently — a student who types /admin-dashboard is sent
// back regardless of what this function decides — so this is routing, not
// access control.
function landingPathForRole(role) {
  return role === 'admin' ? '/admin-dashboard' : '/dashboard';
}

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('login-form');
  const submitBtn = document.getElementById('login-submit');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    showLoginError('');
    submitBtn.disabled = true;
    const username = document.getElementById('login-username').value.trim();
    const password = document.getElementById('login-password').value;
    try {
      const data = await apiPostJson('/login', { username, password });
      window.location.href = landingPathForRole(data.role);
    } catch (err) {
      showLoginError(err.message);
      submitBtn.disabled = false;
    }
  });
});
