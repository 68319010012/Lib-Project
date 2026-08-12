// Port of frontend-react/src/pages/ProfilePage.jsx.

document.addEventListener('DOMContentLoaded', () => {
  apiGet('/me')
    .then((user) => {
      if (!user) return;
      const displayName = `${user.prefix || ''}${user.first_name || ''} ${user.last_name || ''}`.trim() || user.username;
      document.getElementById('profile-student-id').value = user.student_id || user.username || '…';
      document.getElementById('profile-display-name').value = displayName;
      document.getElementById('profile-department').value = user.department || '-';
      document.getElementById('profile-level').value = user.level || '-';
      document.getElementById('profile-year-level').value = user.year_level || '-';
    })
    .catch(() => {});

  const form = document.getElementById('profile-password-form');
  const submitBtn = document.getElementById('profile-password-submit');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    submitBtn.disabled = true;
    const currentPassword = document.getElementById('profile-current-password').value;
    const newPassword = document.getElementById('profile-new-password').value;
    try {
      await apiPostJson('/profile/change-password', {
        current_password: currentPassword,
        new_password: newPassword,
      });
      showToast('เปลี่ยนรหัสผ่านสำเร็จ', { type: 'success' });
      form.reset();
    } catch (err) {
      showToast(err.message, { type: 'error' });
    } finally {
      submitBtn.disabled = false;
    }
  });
});
