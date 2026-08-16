<?php
// Decorative animated college banner — port of
// frontend-react/src/components/LibBanner.jsx (login page hero only, no
// state/API — purely presentational). Styling lives in the .lib-banner rules
// appended to assets/css/styles.css (ported from LibBanner.css verbatim).
$dotDelays = [
    0.00, 0.15, 0.30, 0.45, 0.60, 0.75, 0.90, 0.10, 0.25, 0.40, 0.55, 0.70, 0.85,
    1.00, 0.20, 0.35, 0.50, 0.65, 0.80, 0.95, 1.10, 0.30, 0.45, 0.60, 0.75, 0.90,
    1.05, 1.20, 0.40, 0.55, 0.70, 0.85, 1.00, 1.15, 1.30,
];
$sparks = [
    ['width' => 5, 'height' => 5, 'right' => 120, 'top' => 70, 'delay' => 0.3],
    ['width' => 4, 'height' => 4, 'right' => 280, 'top' => 180, 'delay' => 1.1],
    ['width' => 6, 'height' => 6, 'right' => 60, 'top' => 260, 'delay' => 1.9],
    ['width' => 3, 'height' => 3, 'right' => 360, 'top' => 100, 'delay' => 2.5],
];
?>
<div class="lib-banner">
  <div class="top-stripe"></div>

  <svg class="corner-deco left" width="52" height="52" viewBox="0 0 48 48" fill="none" stroke="#93c5fd" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
    <path d="M24 13 C20 10 13 9 8 10 L8 33 C13 32 20 33 24 36 C28 33 35 32 40 33 L40 10 C35 9 28 10 24 13 Z" />
    <line x1="24" y1="13" x2="24" y2="36" />
    <line x1="12" y1="16" x2="19" y2="15.3" />
    <line x1="12" y1="21" x2="19" y2="20.3" />
    <line x1="29" y1="15.3" x2="36" y2="16" />
    <line x1="29" y1="20.3" x2="36" y2="21" />
  </svg>
  <svg class="corner-deco right" width="52" height="52" viewBox="0 0 48 48" fill="none" stroke="#93c5fd" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
    <rect x="8" y="30" width="32" height="7" rx="1.5" />
    <rect x="10" y="22" width="28" height="7" rx="1.5" transform="rotate(-2 24 25)" />
    <rect x="9" y="14" width="30" height="7" rx="1.5" transform="rotate(2 24 17)" />
  </svg>

  <div class="dotgrid">
    <?php foreach ($dotDelays as $delay): ?>
      <span style="animation-delay: <?= number_format($delay, 2) ?>s"></span>
    <?php endforeach; ?>
  </div>
  <div class="divider"></div>

  <svg class="wave" viewBox="0 0 1440 90" preserveAspectRatio="none">
    <path d="M0,90 L0,50 C220,10 380,80 560,45 C760,5 880,70 1020,40 C1180,8 1300,55 1440,35 L1440,90 Z" fill="#000000" opacity="0.15" />
    <path d="M0,90 L0,60 C240,20 420,85 620,50 C800,18 940,72 1100,45 C1240,20 1340,60 1440,42" fill="none" stroke="#60a5fa" stroke-width="2.5" opacity="0.6" />
  </svg>

  <?php foreach ($sparks as $s): ?>
    <div class="spark" style="width: <?= $s['width'] ?>px; height: <?= $s['height'] ?>px; right: <?= $s['right'] ?>px; top: <?= $s['top'] ?>px; animation-delay: <?= $s['delay'] ?>s"></div>
  <?php endforeach; ?>

  <svg class="illust" viewBox="0 0 600 380" fill="none">
    <g class="float1" stroke="#93c5fd" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
      <rect x="130" y="40" width="150" height="130" rx="4" />
      <line x1="130" y1="84" x2="280" y2="84" />
      <line x1="130" y1="134" x2="280" y2="134" />
      <line x1="166" y1="40" x2="166" y2="84" />
      <line x1="200" y1="40" x2="200" y2="84" />
      <line x1="234" y1="40" x2="234" y2="84" />
      <path d="M144,92 L144,126 M160,90 L160,126 M176,94 L176,126 M194,90 L194,126 M210,92 L210,126 M228,90 L228,126 M250,92 L250,126" />
      <path d="M144,142 L144,162 M170,140 L170,162 M196,144 L196,162 M222,140 L222,162 M248,142 L248,162" />
    </g>
    <g stroke="#93c5fd" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
      <rect x="360" y="55" width="44" height="60" rx="4" />
      <rect x="366" y="63" width="32" height="34" rx="2" />
      <path class="kiosk-check" d="M375,80 L380,85 L390,74" stroke="#059669" />
      <line x1="374" y1="123" x2="374" y2="135" />
      <line x1="390" y1="123" x2="390" y2="135" />
      <line x1="366" y1="135" x2="398" y2="135" />
    </g>
    <g class="person" stroke="#93c5fd" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" transform="translate(60,210)">
      <circle cx="0" cy="-20" r="8" />
      <path d="M-14,18 C-14,2 -8,-4 0,-4 C8,-4 14,2 14,18 Z" />
      <path d="M-9,10 L0,6 L9,10" />
    </g>
    <g class="person" stroke="#93c5fd" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" transform="translate(160,232)">
      <circle cx="0" cy="-20" r="8" />
      <path d="M-14,18 C-14,2 -8,-4 0,-4 C8,-4 14,2 14,18 Z" />
      <path d="M0,-4 C-11,-9 -22,-8 -22,-8 L-22,10 C-22,10 -11,9 0,15 C11,9 22,10 22,10 L22,-8 C22,-8 11,-9 0,-4 Z" transform="translate(0,14) scale(0.5)" />
    </g>
    <g class="person" stroke="#93c5fd" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" transform="translate(260,210)">
      <circle cx="0" cy="-20" r="8" />
      <path d="M-14,18 C-14,2 -8,-4 0,-4 C8,-4 14,2 14,18 Z" />
      <path d="M-9,10 L0,6 L9,10" />
    </g>
    <line x1="20" y1="255" x2="300" y2="255" stroke="#93c5fd" stroke-width="2.4" stroke-linecap="round" opacity="0.85" />
    <line x1="20" y1="255" x2="20" y2="275" stroke="#93c5fd" stroke-width="2.4" stroke-linecap="round" opacity="0.85" />
    <line x1="300" y1="255" x2="300" y2="275" stroke="#93c5fd" stroke-width="2.4" stroke-linecap="round" opacity="0.85" />

    <g class="gear-spin" transform="translate(495,150)" stroke="#2563eb" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" opacity="0.9">
      <circle cx="0" cy="0" r="17" />
      <circle cx="0" cy="0" r="6.5" />
      <line x1="0" y1="-26" x2="0" y2="-34" />
      <line x1="0" y1="26" x2="0" y2="34" />
      <line x1="-26" y1="0" x2="-34" y2="0" />
      <line x1="26" y1="0" x2="34" y2="0" />
      <line x1="-18.4" y1="-18.4" x2="-24" y2="-24" />
      <line x1="18.4" y1="18.4" x2="24" y2="24" />
      <line x1="24" y1="-24" x2="18.4" y2="-18.4" />
      <line x1="-24" y1="24" x2="-18.4" y2="18.4" />
    </g>
    <g class="gear-spin-rev" transform="translate(540,190)" stroke="#93c5fd" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity="0.55">
      <circle cx="0" cy="0" r="11" />
      <circle cx="0" cy="0" r="4" />
      <line x1="0" y1="-17" x2="0" y2="-22" />
      <line x1="0" y1="17" x2="0" y2="22" />
      <line x1="-17" y1="0" x2="-22" y2="0" />
      <line x1="17" y1="0" x2="22" y2="0" />
      <line x1="-12" y1="-12" x2="-15.5" y2="-15.5" />
      <line x1="12" y1="12" x2="15.5" y2="15.5" />
      <line x1="15.5" y1="-15.5" x2="12" y2="-12" />
      <line x1="-15.5" y1="15.5" x2="-12" y2="12" />
    </g>

    <g stroke="#93c5fd" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity="0.55" transform="translate(438,250) scale(0.62)">
      <rect x="-30" y="-28" width="60" height="56" rx="3" />
      <line x1="-30" y1="-6" x2="30" y2="-6" />
      <line x1="-16" y1="-28" x2="-16" y2="-6" />
      <line x1="-2" y1="-28" x2="-2" y2="-6" />
      <line x1="12" y1="-28" x2="12" y2="-6" />
      <path d="M-24,4 L-24,22 M-14,2 L-14,22 M-2,6 L-2,22 M10,2 L10,22 M22,4 L22,22" />
    </g>
  </svg>

  <div class="content">
    <div class="logo-row">
      <div class="logo-badge">
        <img src="/assets/img/logo-badge.svg?v=<?= ntc_asset_v('assets/img/logo-badge.svg') ?>" alt="logo" />
      </div>
      <div>
        <p class="eyebrow">Nakhonnayok Technical College</p>
        <p class="brand">วิทยาลัยเทคนิคนครนายก</p>
      </div>
    </div>

    <div class="title-frame">
      <h1>ห้องสมุด</h1>
      <h2>วิทยาลัยเทคนิคนครนายก</h2>
      <p class="sub">ศูนย์กลางแห่งการเรียนรู้ นวัตกรรม และความสำเร็จ</p>
    </div>

    <p class="footer-line"><span>เปิดให้บริการทุกวันทำการ</span> <span class="footer-line-mid">| <span>ครอบคลุมทุกแผนกวิชา</span></span> | <span>ระบบบันทึกเวลาเข้า-ออกดิจิทัล</span></p>
  </div>
</div>
