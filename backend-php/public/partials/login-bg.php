<?php
// พื้นหลังของหน้าเข้าสู่ระบบและหน้าสมัครสมาชิก — ภาพห้องสมุด 3 รูปซ้อนกัน
// เป็นฉากเดียว (ไม่ใช่สไลด์สลับทีละใบ)
//
// เลือกชุดรูปที่ฝั่งเซิร์ฟเวอร์ ไม่ใช่ที่เบราว์เซอร์ เพราะการให้ JavaScript
// "ลองโหลดดูก่อนว่าไฟล์มีไหม" หมายถึงต้องยิงคำขอที่รู้อยู่แล้วว่าจะพัง แล้ว
// ได้ 404 สามรายการค้างอยู่ในคอนโซลทุกครั้งที่เปิดหน้า ตราบใดที่ยังไม่ได้วาง
// รูปจริง PHP รู้ได้ตรงๆ ด้วย file_exists() จึงไม่มีคำขอที่เสียเปล่าเลย
//
// ลำดับความสำคัญ:
//   1. รูปจริงของห้องสมุดใน assets/img/login/bg-1.jpg … bg-3.jpg
//   2. ถ้ายังไม่มี ใช้ภาพห้องสมุดจาก Unsplash (อยู่ในรายการอนุญาตของ CSP)
//   3. ถ้าออกเน็ตไม่ได้ ชั้นภาพจะโปร่ง เหลือไล่เฉดสีด้านล่างซึ่งดูตั้งใจอยู่แล้ว
//
// วางไฟล์ทั้งสามลงในโฟลเดอร์นั้นเมื่อไร หน้าเว็บเปลี่ยนมาใช้รูปจริงเองทันที
// ไม่ต้องแก้โค้ด — ดูรายละเอียดว่ารูปไหนควรเป็นไฟล์ไหนได้ที่ README.txt
$localBg = ['bg-1.jpg', 'bg-2.jpg', 'bg-3.jpg'];
$haveLocal = true;
foreach ($localBg as $name) {
    if (!is_file(__DIR__ . '/../assets/img/login/' . $name)) {
        $haveLocal = false;
        break;
    }
}

if ($haveLocal) {
    $bgSources = array_map(
        static fn ($n) => '/assets/img/login/' . $n . '?v=' . ntc_asset_v('assets/img/login/' . $n),
        $localBg
    );
} else {
    $bgSources = [
        // ห้องอ่านหนังสือโล่งกว้าง เพดานสูง — ใช้เป็นชั้นหลัก
        'https://images.unsplash.com/photo-1765394715510-9bdf7dacc77f?auto=format&fit=crop&w=1920&q=75',
        // ทางเดินระหว่างชั้นหนังสือ เส้นนำสายตาแนวตั้ง — ใช้เป็นชั้นขวา
        'https://images.unsplash.com/photo-1747515204290-6bf2511fb05b?auto=format&fit=crop&w=1920&q=75',
        // ผนังหนังสือ ลายซ้ำถี่ — ใช้เป็นชั้นล่างซ้ายที่เบลอมากที่สุด
        'https://images.unsplash.com/photo-1771172193679-cce75ed26588?auto=format&fit=crop&w=1920&q=75',
    ];
}
?>
<!-- ชั้นล่างสุดเป็นไล่เฉดสีไว้รองตอนรูปยังมาไม่ถึง ถัดขึ้นมาเป็นรูปสามชั้นที่
     CSS ไล่ขอบด้วย mask จนกลืนกันเป็นภาพเดียว แล้วปิดท้ายด้วยชั้นปรับสีให้
     ทั้งสามรูปอยู่ในโทนเดียวกัน และชั้นหรี่แสงเฉพาะบริเวณหลังการ์ด
     (ดู .login-bg-* ใน assets/css/styles.css) -->
<div class="login-bg" id="login-bg" aria-hidden="true">
  <div class="login-bg-base"></div>
  <?php foreach ($bgSources as $i => $src): ?>
  <div class="login-bg-layer layer-<?= $i + 1 ?>" style="background-image: url('<?= htmlspecialchars($src, ENT_QUOTES) ?>')"></div>
  <?php endforeach; ?>
  <div class="login-bg-grade"></div>
  <div class="login-bg-scrim"></div>
  <div class="login-bg-overlay"></div>
</div>
