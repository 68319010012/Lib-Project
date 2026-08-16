<?php
// Ports templates/reports_select.html. Standalone page (doesn't extend
// base_print.html in the original either), so no render_report_layout() call.
// Reorganized into the report-system redesign's 4 categories (§15) — the
// original 4 cards (dashboard/daily/monthly/department) plus executive/
// compare (added in the previous session) and semester/student (new in this
// one) now total 8, grouped by what they're for rather than a flat grid.
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
<link rel="stylesheet" href="/assets/css/report-fonts.css">
<style>
  :root {
    --primary: #1e3a8a;
    --primary-container: #1e40af;
    --secondary: #2563eb;
    --secondary-container: #60a5fa;
    --surface: #f8fafc;
    --surface-white: #ffffff;
    --outline-variant: #cbd5e1;
    --on-surface-variant: #475569;
    --text-secondary: #475569;
  }
  * { box-sizing: border-box; }
  body {
    font-family: 'Noto Sans Thai', 'Tahoma', sans-serif;
    margin: 0;
    color: #0f172a;
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
      linear-gradient(135deg, var(--primary-container) 0%, #0f172a 100%);
    color: #fff;
    padding: 40px 24px 56px;
  }
  header .inner { max-width: 1040px; margin: 0 auto; }
  header h1 { font-size: clamp(20px, 5vw, 26px); font-weight: 800; margin: 0 0 6px; display: flex; align-items: center; gap: 10px; }
  header p { margin: 0; opacity: .8; font-size: 14px; }

  main { max-width: 1040px; margin: -32px auto 40px; padding: 0 24px; }

  .section-label {
    display: flex; align-items: center; gap: 10px;
    font-size: 13px; font-weight: 800; color: var(--on-surface-variant);
    text-transform: uppercase; letter-spacing: .05em;
    margin: 32px 0 14px;
  }
  .section-label:first-child { margin-top: 0; }
  .section-label .material-symbols-outlined { font-size: 18px; color: var(--primary); }
  .section-label .line { flex: 1; height: 1px; background: var(--outline-variant); }

  .grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    align-items: stretch;
    gap: 20px;
  }
  .card {
    background: var(--surface-white);
    border-radius: 14px;
    box-shadow: 0 4px 20px rgba(0,0,0,.06);
    border: 1px solid var(--outline-variant);
    padding: 22px 24px;
    transition: box-shadow .15s ease;
    /* Cards without a date/month field (e.g. รายงานภาคเรียน) have less
       content than their row-mates — flex column + margin-top:auto on the
       button keeps every card in a row the same height with its button
       pinned to the bottom, instead of a short, oddly-empty box. */
    display: flex;
    flex-direction: column;
  }
  .card button[type="submit"] {
    margin-top: auto;
  }
  .card:hover { box-shadow: 0 10px 28px rgba(30,58,138,.14); }
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
  .card.accent-amber .icon-badge { background: #d97706; }
  .card.accent-slate .icon-badge { background: #475569; }
  .card.accent-indigo .icon-badge { background: #4338ca; }
  .card.accent-rose .icon-badge { background: #be185d; }
  .card h2 { font-size: 16px; margin: 0 0 6px; color: var(--primary); }
  /* min-height reserves space for 2 lines so cards whose description wraps
     (e.g. the dashboard card) don't end up taller than the rest, which
     pushed their date field/button further down than their neighbors'. */
  .card p.desc { font-size: 13px; color: var(--text-secondary); margin: 0 0 16px; line-height: 1.5; min-height: 3em; }
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
  .card input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px rgba(30,58,138,.15); }
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
  .card.accent-amber button { background: #d97706; }
  .card.accent-slate button { background: #475569; }
  .card.accent-indigo button { background: #4338ca; }
  .card.accent-rose button { background: #be185d; }

  .export-note {
    display: flex; align-items: flex-start; gap: 14px;
    background: var(--surface-white); border: 1px dashed var(--outline-variant); border-radius: 14px;
    padding: 18px 22px; font-size: 13px; color: var(--text-secondary); line-height: 1.7;
  }
  .export-note .material-symbols-outlined { color: var(--primary); font-size: 22px; flex-shrink: 0; }
  .export-note b { color: var(--primary); }

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
    <h1><span class="material-symbols-outlined">receipt_long</span> ศูนย์รายงานเช็คชื่อห้องสมุด</h1>
    <p>เลือกรูปแบบรายงานที่ต้องการ กรอกเงื่อนไข แล้วพิมพ์หรือบันทึกเป็น PDF ได้ทันที</p>
  </div>
</header>

<main>

  <div class="section-label"><span class="material-symbols-outlined">dashboard</span> ภาพรวม <span class="line"></span></div>
  <div class="grid">

    <form class="card accent-purple" action="/admin/reports/print/dashboard" method="get">
      <div class="icon-badge"><span class="material-symbols-outlined">monitoring</span></div>
      <h2>รายงานแบบแดชบอร์ด</h2>
      <p class="desc">สรุปภาพรวมแบบ KPI การ์ดและกราฟ พร้อมตัวกรองแผนก/ระดับชั้น/ภาคเรียน เห็นภาพรวมได้ในหน้าเดียว</p>
      <label for="dashboard_month">เดือน</label>
      <input type="month" id="dashboard_month" name="month" value="<?= htmlspecialchars($thisMonth) ?>">
      <button type="submit"><span class="material-symbols-outlined" style="font-size:18px;">visibility</span> ดูรายงาน</button>
    </form>

    <form class="card accent-amber" action="/admin/reports/print/executive" method="get">
      <div class="icon-badge"><span class="material-symbols-outlined">insights</span></div>
      <h2>สรุปสำหรับผู้บริหาร</h2>
      <p class="desc">ภาพรวมระดับผู้บริหาร พร้อม % เทียบกับเดือนก่อนหน้าและแผนกยอดนิยม</p>
      <label for="executive_month">เดือน</label>
      <input type="month" id="executive_month" name="month" value="<?= htmlspecialchars($thisMonth) ?>">
      <button type="submit"><span class="material-symbols-outlined" style="font-size:18px;">visibility</span> ดูรายงาน</button>
    </form>

    <form class="card accent-indigo" action="/admin/reports/print/semester" method="get">
      <div class="icon-badge"><span class="material-symbols-outlined">school</span></div>
      <h2>รายงานภาคเรียน</h2>
      <p class="desc">สรุปผลการใช้ห้องสมุดตลอดภาคเรียน ตามปีการศึกษา/ภาคเรียนที่นักศึกษาลงทะเบียนไว้</p>
      <button type="submit"><span class="material-symbols-outlined" style="font-size:18px;">visibility</span> ดูรายงาน</button>
    </form>

  </div>

  <div class="section-label"><span class="material-symbols-outlined">event</span> รายงานประจำช่วงเวลา <span class="line"></span></div>
  <div class="grid">

    <form class="card accent-primary" action="/admin/reports/print/daily" method="get">
      <div class="icon-badge"><span class="material-symbols-outlined">today</span></div>
      <h2>รายงานประจำวัน</h2>
      <p class="desc">รายชื่อนักศึกษาที่เช็คชื่อในวันที่เลือก พร้อมเวลาเข้า/ออกและระยะเวลาที่ใช้ห้องสมุด</p>
      <label for="date">วันที่</label>
      <input type="date" id="date" name="date" value="<?= htmlspecialchars($today) ?>">
      <button type="submit"><span class="material-symbols-outlined" style="font-size:18px;">visibility</span> ดูรายงาน</button>
    </form>

    <form class="card accent-secondary" action="/admin/reports/print/monthly" method="get">
      <div class="icon-badge"><span class="material-symbols-outlined">calendar_month</span></div>
      <h2>รายงานสรุปรายเดือน</h2>
      <p class="desc">จำนวนครั้งที่เช็คชื่อของนักศึกษาแต่ละคน พร้อมเปรียบเทียบกับเดือนก่อนหน้า</p>
      <label for="month">เดือน</label>
      <input type="month" id="month" name="month" value="<?= htmlspecialchars($thisMonth) ?>">
      <button type="submit"><span class="material-symbols-outlined" style="font-size:18px;">visibility</span> ดูรายงาน</button>
    </form>

  </div>

  <div class="section-label"><span class="material-symbols-outlined">query_stats</span> รายงานวิเคราะห์ <span class="line"></span></div>
  <div class="grid">

    <form class="card accent-teal" action="/admin/reports/print/department" method="get">
      <div class="icon-badge"><span class="material-symbols-outlined">apartment</span></div>
      <h2>รายงานสรุปตามแผนกวิชา</h2>
      <p class="desc">Ranking แผนกวิชา เทียบเดือนนี้กับเดือนก่อน พร้อมดูแนวโน้มรายแผนกได้</p>
      <label for="academic_year">ปีการศึกษา (ไม่ใส่ = ทั้งหมด)</label>
      <input type="text" id="academic_year" name="academic_year" placeholder="เช่น 2568">
      <button type="submit"><span class="material-symbols-outlined" style="font-size:18px;">visibility</span> ดูรายงาน</button>
    </form>

    <form class="card accent-slate" action="/admin/reports/print/compare" method="get">
      <div class="icon-badge"><span class="material-symbols-outlined">compare_arrows</span></div>
      <h2>เปรียบเทียบช่วงเวลา</h2>
      <p class="desc">เทียบตัวชี้วัดและแผนกวิชาระหว่าง 2 เดือนที่เลือก</p>
      <label for="month_a">ช่วง A</label>
      <input type="month" id="month_a" name="month_a" value="<?= htmlspecialchars(date('Y-m', strtotime('-1 month'))) ?>">
      <label for="month_b" style="margin-top:8px;">ช่วง B</label>
      <input type="month" id="month_b" name="month_b" value="<?= htmlspecialchars($thisMonth) ?>">
      <button type="submit"><span class="material-symbols-outlined" style="font-size:18px;">visibility</span> เปรียบเทียบ</button>
    </form>

    <form class="card accent-rose" action="/admin/reports/print/student" method="get">
      <div class="icon-badge"><span class="material-symbols-outlined">person_search</span></div>
      <h2>รายงานผู้ใช้บริการ</h2>
      <p class="desc">ค้นหาประวัติการใช้ห้องสมุดของนักศึกษาเป็นรายบุคคล (สำหรับแอดมินเท่านั้น)</p>
      <label for="student_search">ค้นหารหัส/ชื่อ/แผนก</label>
      <input type="text" id="student_search" name="search" placeholder="เช่น 6800112233">
      <button type="submit"><span class="material-symbols-outlined" style="font-size:18px;">visibility</span> ค้นหา</button>
    </form>

  </div>

  <div class="section-label"><span class="material-symbols-outlined">download</span> การส่งออก <span class="line"></span></div>
  <div class="export-note">
    <span class="material-symbols-outlined">info</span>
    <span>
      ทุกรายงานด้านบนมีปุ่ม <b>พิมพ์ / บันทึกเป็น PDF</b>, <b>ดาวน์โหลด CSV</b> และ <b>ดาวน์โหลด Excel</b>
      อยู่ในแถบเครื่องมือด้านบนของหน้ารายงานเอง (หลังกด "ดูรายงาน") พร้อมปุ่ม <b>ตั้งค่าการพิมพ์</b>
      สำหรับเลือกขนาดกระดาษ ธีมสี และส่วนที่ต้องการพิมพ์
    </span>
  </div>

</main>

<script>
  // Changing a date/month field jumps straight into that report with the
  // new value — no separate "ดูรายงาน" click needed. The button stays for
  // cards with no field to change (e.g. รายงานภาคเรียน) and as a fallback.
  document.querySelectorAll('.card input[type="date"], .card input[type="month"]').forEach(function (el) {
    el.addEventListener('change', function () {
      el.form.submit();
    });
  });
</script>
</body>
</html>
<?php
}
