// Port of frontend-react/src/pages/ProfilePage.jsx.
//
// หน้านี้แสดงข้อมูลผู้ใช้อย่างเดียว การเปลี่ยนรหัสผ่านย้ายไปเป็นหน้าของตัวเอง
// ที่ /change-password (assets/js/change-password.js) แล้ว

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
});
