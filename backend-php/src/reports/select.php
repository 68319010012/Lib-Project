<?php
// Ports templates/reports_select.html. Standalone page (doesn't extend
// base_print.html in the original either), so no render_report_layout() call.
function handle_report_select(): void
{
    require_login();
    require_admin();

    $today = date('Y-m-d');
    $thisMonth = date('Y-m');

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เลือกเทมเพลตรายงาน | ห้องสมุด NNTC</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>
  :root {
    --primary: #5c101f;
    --primary-container: #7a2734;
    --secondary: #a53d00;
    --secondary-container: #ff7e44;
    --surface: #f8f9fb;
    --surface-white: #ffffff;
    --outline-variant: #dbc0c1;
    --on-surface-variant: #554243;
    --text-secondary: #6B7280;
  }
  * { box-sizing: border-box; }
  body {
    font-family: 'Noto Sans Thai', 'Tahoma', sans-serif;
    margin: 0;
    color: #191c1e;
    background: var(--surface);
    min-height: 100vh;
  }
  .material-symbols-outlined {
    font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    vertical-align: middle;
  }
  header {
    background-image:
      repeating-linear-gradient(90deg, rgba(255,255,255,.05) 0px, rgba(255,255,255,.05) 1px, transparent 1px, transparent 24px),
      linear-gradient(135deg, var(--primary-container) 0%, #40000f 100%);
    color: #fff;
    padding: 40px 24px 56px;
  }
  header .inner { max-width: 900px; margin: 0 auto; }
  header h1 { font-size: clamp(20px, 5vw, 26px); font-weight: 800; margin: 0 0 6px; display: flex; align-items: center; gap: 10px; }
  header p { margin: 0; opacity: .8; font-size: 14px; }

  main { max-width: 900px; margin: -32px auto 40px; padding: 0 24px; }
  .grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
  }
  .card {
    background: var(--surface-white);
    border-radius: 14px;
    box-shadow: 0 4px 20px rgba(0,0,0,.06);
    border: 1px solid var(--outline-variant);
    padding: 22px 24px;
    transition: box-shadow .15s ease;
  }
  .card:hover { box-shadow: 0 10px 28px rgba(92,16,31,.14); }
  .card .icon-badge {
    width: 44px; height: 44px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 14px;
    color: #fff;
  }
  .card.accent-primary .icon-badge { background: var(--primary); }
  .card.accent-secondary .icon-badge { background: var(--secondary); }
  .card.accent-teal .icon-badge { background: #2f7d6b; }
  .card.accent-purple .icon-badge { background: #8B5FA3; }
  .card h2 { font-size: 16px; margin: 0 0 6px; color: var(--primary); }
  .card p.desc { font-size: 13px; color: var(--text-secondary); margin: 0 0 16px; line-height: 1.5; }
  .card label { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: var(--on-surface-variant); margin-bottom: 4px; font-weight: 600; }
  .card input {
    width: 100%;
    font-size: 14px;
    padding: 9px 10px;
    border: 1px solid var(--outline-variant);
    border-radius: 8px;
    background: var(--surface);
    margin-bottom: 14px;
  }
  .card input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px rgba(92,16,31,.15); }
  .card button {
    width: 100%;
    font-size: 14px;
    font-weight: 700;
    padding: 11px;
    border: none;
    border-radius: 999px;
    color: #fff;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 6px;
    transition: filter .15s ease, transform .1s ease;
  }
  .card button:hover { filter: brightness(1.1); }
  .card button:active { transform: scale(.98); }
  .card.accent-primary button { background: var(--primary); }
  .card.accent-secondary button { background: var(--secondary); }
  .card.accent-teal button { background: #2f7d6b; }
  .card.accent-purple button { background: #8B5FA3; }

  @media (max-width: 640px) {
    header { padding: 28px 16px 44px; }
    main { margin-top: -24px; padding: 0 16px; }
    .grid { gap: 14px; }
    .card { padding: 18px 18px; }
  }
</style>
</head>
<body>
<header>
  <div class="inner">
    <h1><span class="material-symbols-outlined">receipt_long</span> เลือกเทมเพลตรายงานเช็คชื่อ</h1>
    <p>เลือกรูปแบบรายงานที่ต้องการ กรอกเงื่อนไข แล้วพิมพ์หรือบันทึกเป็น PDF ได้ทันที</p>
  </div>
</header>

<main>
  <div class="grid">

    <form class="card accent-purple" action="/admin/reports/print/dashboard" method="get">
      <div class="icon-badge"><span class="material-symbols-outlined">monitoring</span></div>
      <h2>รายงานแบบแดชบอร์ด</h2>
      <p class="desc">สรุปภาพรวมประจำเดือนแบบ KPI การ์ดและกราฟ เห็นภาพรวมได้ในหน้าเดียว</p>
      <label for="dashboard_month">เดือน</label>
      <input type="month" id="dashboard_month" name="month" value="<?= htmlspecialchars($thisMonth) ?>">
      <button type="submit"><span class="material-symbols-outlined" style="font-size:18px;">visibility</span> ดูรายงาน</button>
    </form>

    <form class="card accent-primary" action="/admin/reports/print/daily" method="get">
      <div class="icon-badge"><span class="material-symbols-outlined">today</span></div>
      <h2>รายงานประจำวัน</h2>
      <p class="desc">รายชื่อนักศึกษาที่เช็คชื่อในวันที่เลือก พร้อมเวลาเข้า/ออก</p>
      <label for="date">วันที่</label>
      <input type="date" id="date" name="date" value="<?= htmlspecialchars($today) ?>">
      <button type="submit"><span class="material-symbols-outlined" style="font-size:18px;">visibility</span> ดูรายงาน</button>
    </form>

    <form class="card accent-secondary" action="/admin/reports/print/monthly" method="get">
      <div class="icon-badge"><span class="material-symbols-outlined">calendar_month</span></div>
      <h2>รายงานสรุปรายเดือน</h2>
      <p class="desc">จำนวนครั้งที่เช็คชื่อของนักศึกษาแต่ละคนในเดือนที่เลือก</p>
      <label for="month">เดือน</label>
      <input type="month" id="month" name="month" value="<?= htmlspecialchars($thisMonth) ?>">
      <button type="submit"><span class="material-symbols-outlined" style="font-size:18px;">visibility</span> ดูรายงาน</button>
    </form>

    <form class="card accent-teal" action="/admin/reports/print/department" method="get">
      <div class="icon-badge"><span class="material-symbols-outlined">apartment</span></div>
      <h2>รายงานสรุปตามแผนกวิชา</h2>
      <p class="desc">ยอดรวมการเช็คชื่อแยกตามแผนก กรองตามปีการศึกษาได้</p>
      <label for="academic_year">ปีการศึกษา (ไม่ใส่ = ทั้งหมด)</label>
      <input type="text" id="academic_year" name="academic_year" placeholder="เช่น 2568">
      <button type="submit"><span class="material-symbols-outlined" style="font-size:18px;">visibility</span> ดูรายงาน</button>
    </form>

  </div>
</main>
</body>
</html>
<?php
}
