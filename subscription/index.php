<?php
ini_set('error_log', 'error_log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../panels/Api/Marzban.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';

function nm_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function nm_bytes($bytes): string {
    if ($bytes === null || $bytes === '' || (float)$bytes <= 0) return 'نامحدود / ثبت نشده';
    $units = ['B','KB','MB','GB','TB']; $v=(float)$bytes; $i=0;
    while ($v>=1024 && $i<count($units)-1) { $v/=1024; $i++; }
    return number_format($v, $i===0?0:2).' '.$units[$i];
}
function nm_date($value): string {
    if (!$value) return 'نامشخص';
    $ts=is_numeric($value)?(int)$value:strtotime((string)$value);
    return $ts ? date('Y-m-d H:i', $ts) : 'نامشخص';
}

$token = trim((string)($_GET['id'] ?? ''));
if (!preg_match('/^[a-f0-9]{4,64}$/i', $token)) { http_response_code(404); exit('اشتراک معتبر نیست.'); }
try {
    $invoice = select('invoice', '*', 'id_invoice', $token, 'select');
    $allowed = ['active','end_of_time','end_of_volume','sendedwarn','send_on_hold'];
    if (!is_array($invoice) || !in_array($invoice['Status'] ?? '', $allowed, true)) { http_response_code(404); exit('اشتراک پیدا نشد یا معتبر نیست.'); }
    $manager = new ManagePanel();
    $info = $manager->DataUser($invoice['Service_location'], $invoice['username']);
    if (!is_array($info) || ($info['status'] ?? '') === 'Unsuccessful') { http_response_code(502); exit('در دریافت اطلاعات اشتراک مشکلی پیش آمد.'); }
    $links = array_values(array_filter(array_map('trim', is_array($info['links'] ?? null) ? $info['links'] : [])));
    $base = 'https://' . $domainhosts;
    $rawUrl = $base . '/sub/' . rawurlencode($token);
    $status = (string)($info['status'] ?? $invoice['Status'] ?? 'unknown');
    $statusMap = ['active'=>['فعال','ok'],'disabled'=>['غیرفعال','off'],'on_hold'=>['متوقف','off'],'limited'=>['محدود شده','off'],'expired'=>['منقضی شده','off']];
    [$statusLabel,$statusClass] = $statusMap[$status] ?? [($invoice['Status'] ?? 'نامشخص'),'off'];
    $used = (float)($info['used_traffic'] ?? 0);
    $limit = (float)($info['data_limit'] ?? 0);
    $percent = $limit > 0 ? min(100, max(0, $used / $limit * 100)) : 0;
} catch (Throwable $e) {
    error_log('NM subscription page error: '.$e->getMessage());
    http_response_code(500); exit('خطای موقت در نمایش اشتراک.');
}
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#050805">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Vazirmatn:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<title>NM TUNNEL | پنل اشتراک</title>
<style>
:root{color-scheme:dark;--bg:#050805;--panel:#0b100b;--panel2:#101810;--line:#263b25;--muted:#91a08c;--white:#f1f7ee;--green:#b7ff35;--green2:#75d927;--danger:#ff7777}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;padding:30px 16px;color:var(--white);font-family:'Vazirmatn',Tahoma,sans-serif;background-color:var(--bg);background-image:radial-gradient(#a7ff3210 1px,transparent 1px),linear-gradient(135deg,#050805,#0a1009 55%,#050805);background-size:6px 6px,auto}
body:before{content:"";position:fixed;inset:0;pointer-events:none;background:linear-gradient(transparent 50%,#00000012 50%);background-size:100% 4px;opacity:.25;z-index:0}
.wrap{position:relative;z-index:1;max-width:920px;margin:auto}
.top{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px}
.brand{display:flex;gap:13px;align-items:center}
.logo{width:52px;height:52px;border:1px solid var(--green);background:#0c1609;display:grid;place-items:center;color:var(--green);font-family:'Share Tech Mono',monospace;font-weight:900;font-size:19px;box-shadow:0 0 22px #a7ff3225;clip-path:polygon(0 0,100% 0,100% 76%,76% 100%,0 100%)}
.brand b{font-family:'Share Tech Mono','Vazirmatn',sans-serif;letter-spacing:2px;font-size:20px;color:var(--green)}
.brand small{display:block;color:var(--muted);font-family:'Share Tech Mono',sans-serif;font-size:10px;margin-top:4px;letter-spacing:1.5px}
.secure{font-size:11px;color:var(--green);border:1px solid #38542c;padding:8px 12px;background:#a7ff3208;font-family:'Share Tech Mono','Vazirmatn',sans-serif;letter-spacing:.4px}
.hero,.card{background:linear-gradient(145deg,#101810,#090e09);border:1px solid var(--line);box-shadow:0 14px 40px #0008}
.hero{padding:30px;margin-bottom:15px;position:relative;overflow:hidden}
.hero:before{content:"";position:absolute;inset:0 auto 0 0;width:4px;background:var(--green);box-shadow:0 0 18px var(--green)}
.hero:after{content:"NM";position:absolute;left:24px;bottom:-46px;font-family:'Share Tech Mono',monospace;font-size:160px;font-weight:900;color:#b7ff3509;pointer-events:none}
.eyebrow{color:var(--green);font-family:'Share Tech Mono',sans-serif;font-size:12px;letter-spacing:2px}
.hero h1{font-size:26px;margin:12px 0 8px;font-weight:800}
.hero p{color:var(--muted);margin:0;font-size:13px;line-height:2}
.status{display:inline-flex;align-items:center;gap:9px;margin-top:19px;padding:8px 12px;border:1px solid #45652c;background:#a7ff3210;color:var(--green);font-size:12px}
.status.off{border-color:#633434;background:#ff777710;color:#ff9999}
.dot{width:8px;height:8px;background:currentColor;box-shadow:0 0 10px currentColor;animation:pulse 1.7s steps(2,end) infinite}
.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:15px}
.card{padding:20px;transition:transform .2s,border-color .2s,background .2s}
.card:hover{transform:translateY(-3px);border-color:#83b943;background:linear-gradient(145deg,#142014,#0a1009)}
.card h2{font-size:14px;margin:0 0 15px;color:var(--green);font-weight:700}
.metric{color:var(--muted);font-size:11px;margin-bottom:8px}
.value{font-size:17px;font-weight:700;direction:ltr;text-align:right;font-family:'Share Tech Mono','Vazirmatn',sans-serif;color:var(--white)}
.bar{height:9px;background:#1c291a;border:1px solid #31472a;overflow:hidden;margin-top:13px}
.bar i{display:block;height:100%;background:repeating-linear-gradient(90deg,var(--green) 0 8px,#75d927 8px 11px);box-shadow:0 0 14px #a7ff3270;transition:width 1s ease}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
.btn{border:1px solid #425a31;padding:12px 17px;font-family:'Vazirmatn',sans-serif;font-weight:800;font-size:12px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;transition:all .18s}
.primary{background:var(--green);border-color:var(--green);color:#071006}
.primary:hover{background:#d0ff83;box-shadow:0 0 22px #a7ff3245}
.secondary{background:#101810;color:var(--white)}
.secondary:hover{border-color:var(--green);color:var(--green)}
.servers{display:grid;gap:9px}.server{display:grid;grid-template-columns:1fr auto;gap:12px;align-items:center;padding:14px;border:1px solid #263b25;background:#080e08}.server strong{display:block;color:var(--green);font-size:14px}.server code{display:block;margin-top:6px;color:#71816b;font:10px 'Share Tech Mono',monospace;direction:ltr;text-align:left;overflow-wrap:anywhere;white-space:normal}.social{display:flex;gap:10px;margin:-10px 0 18px;flex-wrap:wrap}.social a{color:var(--green);font:12px 'Share Tech Mono','Vazirmatn',sans-serif;text-decoration:none;border:1px solid #36502b;padding:9px 12px;background:#a7ff3208}.square-metric{min-height:135px;display:flex;flex-direction:column;justify-content:space-between}.square-metric .value{color:var(--green);font-size:19px}
.server{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:13px 14px;border:1px solid #263b25;background:#080e08}
.server strong{font-size:12px;direction:ltr;overflow-wrap:anywhere;font-family:'Share Tech Mono','Vazirmatn',sans-serif}
.server span{font-size:10px;color:var(--green);white-space:nowrap;font-family:'Share Tech Mono',sans-serif;border:1px solid #38542c;padding:4px 7px}
.map-wrap{height:290px;margin-top:12px;background:#050a05;border:1px solid #294326}.map-wrap svg{width:100%;height:100%}.map-grid{stroke:#274322;stroke-dasharray:3 5;fill:none}.map-legend{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.map-legend button{background:#101810;border:1px solid #38542c;color:var(--green);padding:7px 10px;cursor:pointer}.map-marker{fill:#b7ff35;stroke:#e8ffc5;stroke-width:2;filter:drop-shadow(0 0 6px #b7ff35);cursor:pointer}.chart-head{display:flex;align-items:center;justify-content:space-between;gap:12px}.chart-head select{background:#0b130a;border:1px solid #38542c;color:var(--green);padding:7px}.chart{height:180px;margin-top:12px;border:1px solid #294326;background:repeating-linear-gradient(0deg,transparent 0 35px,#29432655 36px)}.chart svg{width:100%;height:100%}.chart-line{fill:none;stroke:#b7ff35;stroke-width:3;filter:drop-shadow(0 0 5px #b7ff35)}.chart-area{fill:url(#chartFill)}.chart-note{font-size:10px;color:#71816b;line-height:2}.tabs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 14px}.tab{flex:1;min-width:110px;background:#0b130a;border:1px solid #263b25;color:var(--muted);padding:12px 10px;font:700 12px Vazirmatn;cursor:pointer}.tab.active{background:#b7ff3515;border-color:var(--green);color:var(--green);box-shadow:0 0 18px #a7ff3212}.tab-panel{display:none}.tab-panel.active{display:block}.map-wrap{height:390px;position:relative;overflow:hidden}.map-legend button{font-family:Vazirmatn,sans-serif}.server{cursor:pointer}.server:hover{border-color:var(--green)}.usage-layout{display:grid;grid-template-columns:190px 1fr;gap:24px;align-items:center}.usage-ring{width:170px;height:170px;border-radius:50%;display:grid;place-items:center;background:conic-gradient(var(--green) var(--usage),#263b25 0);position:relative;margin:auto;box-shadow:0 0 24px #a7ff3218}.usage-ring:before{content:"";position:absolute;inset:13px;border-radius:50%;background:#0b100b;border:1px solid #263b25}.usage-ring>div{position:relative;display:grid;text-align:center;gap:4px}.usage-ring strong{font:25px 'Share Tech Mono',sans-serif;color:var(--green)}.usage-ring small{font-size:11px;color:var(--muted)}.usage-details{display:grid;gap:10px}.usage-details>div{display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #263b25;padding:7px 0;font-size:12px}.usage-details span{color:var(--muted)}.usage-details b{color:var(--green);direction:ltr;font-family:'Share Tech Mono',sans-serif}.tabs{display:flex;gap:10px;margin:0 0 15px}.tab{flex:1;padding:13px;background:#0b100b;border:1px solid #263b25;color:var(--muted);font:700 13px Vazirmatn;cursor:pointer}.tab.active{border-color:var(--green);background:#b7ff3514;color:var(--green);box-shadow:0 0 18px #b7ff3512}.tab-content{display:none}.tab-content.active{display:block}.config-row{display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:64px}.config-row strong{font-size:14px;color:var(--white);font-family:Vazirmatn,sans-serif;overflow-wrap:anywhere}.config-row .btn{flex-shrink:0;padding:9px 14px}.servers{display:grid;gap:9px}.map-wrap{height:390px;width:100%;position:relative;overflow:hidden}.map-wrap canvas{display:block;width:100%!important;height:100%!important}.map-legend button{font-family:Vazirmatn,sans-serif}.footer{text-align:center;color:#71816b;font-size:10px;padding:24px 0 5px;line-height:2;font-family:'Vazirmatn',sans-serif}
@keyframes pulse{50%{opacity:.25}}
@media(max-width:600px){.usage-layout{grid-template-columns:1fr}.usage-details{width:100%}body{padding:20px 12px}.hero{padding:22px}.hero h1{font-size:21px}.grid{grid-template-columns:1fr}.top{align-items:flex-start}.secure{font-size:9px;padding:7px}.actions .btn{flex:1}.brand b{font-size:18px}}
@media(prefers-reduced-motion:reduce){*,*:before,*:after{animation:none!important;transition:none!important}}
</style></head>
<body><main class="wrap">
<header class="top"><div class="brand"><div class="logo">NM</div><div><b>NM TUNNEL</b><small>SECURE CONNECTION SERVICES</small></div></div><div class="secure">■ SYSTEM ONLINE</div></header><div class="social"><a href="https://t.me/NmTunnel_bot" target="_blank" rel="noopener noreferrer">@NmTunnel_bot</a><a href="https://t.me/NM_Tunnel" target="_blank" rel="noopener noreferrer">@NM_Tunnel</a></div>
<section class="hero"><div class="eyebrow">[ NM SYSTEM // SUBSCRIPTION ]</div><h1>مدیریت اشتراک شما</h1><p>وضعیت سرویس و اطلاعات اتصال خود را در این صفحه مشاهده و مدیریت کنید.</p><div class="status <?=nm_h($statusClass)?>"><i class="dot"></i><?=nm_h($statusLabel)?></div></section>


<nav class="tabs" aria-label="بخش‌های اشتراک">
 <button type="button" class="tab active" data-panel="mainPanel">نمای کلی</button>
 <button type="button" class="tab" data-panel="configPanel">کانفیگ‌ها (<?=count($links)?>)</button>
</nav>
<div id="mainPanel" class="tab-content active">
<section class="grid">
<div class="card"><div class="metric">نام کاربری سرویس</div><div class="value"><?=nm_h($invoice['username'] ?? '—')?></div></div>
<div class="card"><div class="metric">موقعیت سرویس</div><div class="value"><?=nm_h($invoice['Service_location'] ?? '—')?></div></div>
<div class="card square-metric"><div class="metric">حجم مصرف‌شده</div><div class="value"><?=nm_h(nm_bytes($used))?></div><div class="bar"><i style="width:<?=$percent?>%"></i></div></div>
<div class="card square-metric"><div class="metric">حجم کل اشتراک</div><div class="value"><?=nm_h(nm_bytes($limit))?></div></div>
<div class="card square-metric"><div class="metric">تعداد کانفیگ‌ها</div><div class="value"><?=count($links)?></div></div>
<div class="card square-metric"><div class="metric">تاریخ انقضا</div><div class="value"><?=nm_h(nm_date($info['expire'] ?? null))?></div></div>
</section>
<section class="card" style="margin-top:15px"><h2>مصرف فعلی اشتراک</h2><div class="usage-layout"><div class="usage-ring" style="--usage:<?=$percent?>%"><div><strong><?=number_format($percent,1)?>٪</strong><small>مصرف‌شده</small></div></div><div class="usage-details"><div><span>مصرف فعلی</span><b><?=nm_h(nm_bytes($used))?></b></div><div><span>حجم کل</span><b><?=nm_h(nm_bytes($limit))?></b></div><div><span>باقی‌مانده</span><b><?=nm_h($limit>0?nm_bytes(max(0,$limit-$used)):'نامحدود')?></b></div><p class="chart-note">نمایش بر اساس آمار فعلی همین اشتراک از پنل سرور.</p></div></div></section>
<section class="card" style="margin-top:15px"><div class="chart-head"><h2>موقعیت سرورها روی کره زمین</h2><span class="metric" id="geoStatus">در حال شناسایی موقعیت سرورها…</span></div><div id="globeViz" class="map-wrap"></div><div id="mapLegend" class="map-legend"></div></section>
<section class="card" style="margin-top:15px"><h2>مدیریت لینک اشتراک</h2><div class="metric">لینک را در برنامه کلاینت خود به‌عنوان Subscription وارد کنید.</div><div class="actions"><button class="btn primary" id="copySub">کپی لینک اشتراک</button><button class="btn secondary" id="refresh">به‌روزرسانی اطلاعات</button></div></section>
</div>
<div id="configPanel" class="tab-content">
<section class="card"><h2>کانفیگ‌های اشتراک</h2><p class="metric">برای کپی، دکمه کنار نام هر کانفیگ را بزنید.</p><div class="servers">
<?php if (!$links): ?><div class="metric">در حال حاضر کانفیگی برای نمایش موجود نیست.</div><?php else: foreach ($links as $i=>$link): $fragment=parse_url($link, PHP_URL_FRAGMENT); $configName=rawurldecode($fragment ?: ('کانفیگ '.($i+1))); ?>
<div class="server config-row"><strong><?=nm_h($configName)?></strong><button type="button" class="btn secondary copy-config" data-config="<?=nm_h($link)?>">کپی کانفیگ</button></div>
<?php endforeach; endif; ?></div></section>
</div>
<footer class="footer">NM TUNNEL · OFFICIAL SUBSCRIPTION INTERFACE<br>برای حفظ امنیت، لینک اشتراک خود را در اختیار دیگران قرار ندهید.</footer>
</main>
<script src="https://unpkg.com/globe.gl"></script>
<script>
'use strict';

document.querySelectorAll('.tab').forEach(btn => btn.addEventListener('click', () => {
 document.querySelectorAll('.tab').forEach(tab => tab.classList.toggle('active', tab === btn));
 document.querySelectorAll('.tab-content').forEach(panel => panel.classList.toggle('active', panel.id === btn.dataset.panel));
}));

const subscriptionUrl = <?=json_encode($rawUrl, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>;
const copySubscriptionButton = document.getElementById('copySub');

async function copyText(value) {
 if (navigator.clipboard && window.isSecureContext) {
  await navigator.clipboard.writeText(value);
  return true;
 }
 const field = document.createElement('textarea');
 field.value = value;
 field.setAttribute('readonly', '');
 field.style.position = 'fixed';
 field.style.opacity = '0';
 document.body.appendChild(field);
 field.select();
 let copied = false;
 try { copied = document.execCommand('copy'); } finally { field.remove(); }
 if (!copied) throw new Error('Clipboard unavailable');
 return true;
}

copySubscriptionButton.addEventListener('click', async () => {
 try {
  await copyText(subscriptionUrl);
  copySubscriptionButton.textContent = 'کپی شد ✓';
  setTimeout(() => copySubscriptionButton.textContent = 'کپی لینک اشتراک', 1800);
 } catch (error) {
  prompt('لینک اشتراک:', subscriptionUrl);
 }
});
document.getElementById('refresh').addEventListener('click', () => location.reload());

document.querySelectorAll('.copy-config').forEach(button => button.addEventListener('click', async () => {
 const originalLabel = 'کپی کانفیگ';
 try {
  await copyText(button.dataset.config || '');
  button.textContent = 'کپی شد ✓';
  setTimeout(() => button.textContent = originalLabel, 1800);
 } catch (error) {
  prompt('کپی کانفیگ:', button.dataset.config || '');
 }
}));

function decodeBase64Url(value) {
 let normalized = value.replace(/-/g, '+').replace(/_/g, '/');
 while (normalized.length % 4) normalized += '=';
 return atob(normalized);
}

function hostFromConfig(rawValue) {
 const raw = String(rawValue || '').trim();
 try {
  const lower = raw.toLowerCase();
  if (lower.startsWith('vmess://')) {
   const payload = raw.slice(raw.indexOf('://') + 3).split('#')[0];
   const data = JSON.parse(decodeBase64Url(payload));
   return String(data.add || data.server || data.host || '').replace(/^\[|\]$/g, '');
  }
  if (lower.startsWith('ss://')) {
   const body = raw.slice(raw.indexOf('://') + 3).split(/[?#]/)[0];
   const at = body.lastIndexOf('@');
   if (at < 0) {
    try {
     const decoded = decodeBase64Url(body);
     const match = decoded.match(/@([^:]+|\[[^\]]+\]):\d+/);
     if (match) return match[1].replace(/^\[|\]$/g, '');
    } catch (error) {}
   }
  }
  const parsed = new URL(raw);
  return String(parsed.hostname || '').replace(/^\[|\]$/g, '');
 } catch (error) {
  return '';
 }
}

async function fetchJson(url, timeoutMs = 6500) {
 const controller = new AbortController();
 const timer = setTimeout(() => controller.abort(), timeoutMs);
 try {
  const response = await fetch(url, { signal: controller.signal, headers: { accept: 'application/json' }, cache: 'no-store' });
  if (!response.ok) throw new Error('HTTP ' + response.status);
  return await response.json();
 } finally {
  clearTimeout(timer);
 }
}

function isPublicAddress(ip) {
 if (/^\d{1,3}(\.\d{1,3}){3}$/.test(ip)) {
  const p = ip.split('.').map(Number);
  if (p.some(n => n < 0 || n > 255)) return false;
  if (p[0] === 0 || p[0] === 10 || p[0] === 127 || p[0] >= 224) return false;
  if (p[0] === 169 && p[1] === 254) return false;
  if (p[0] === 172 && p[1] >= 16 && p[1] <= 31) return false;
  if (p[0] === 192 && p[1] === 168) return false;
  if (p[0] === 100 && p[1] >= 64 && p[1] <= 127) return false;
  return true;
 }
 const v6 = ip.toLowerCase();
 if (v6.includes(':')) {
  if (v6 === '::' || v6 === '::1' || v6.startsWith('fe8') || v6.startsWith('fe9') ||
      v6.startsWith('fea') || v6.startsWith('feb') || v6.startsWith('fc') ||
      v6.startsWith('fd') || v6.startsWith('ff')) return false;
  return true;
 }
 return false;
}

async function resolvePublicIp(host) {
 if (isPublicAddress(host)) return host;
 if (host.includes(':')) return '';
 for (const type of ['A', 'AAAA']) {
  try {
   const result = await fetchJson('https://dns.google/resolve?name=' + encodeURIComponent(host) + '&type=' + type);
   const answer = (result.Answer || []).find(item => type === 'A' ? item.type === 1 : item.type === 28);
   if (answer && isPublicAddress(answer.data)) return answer.data;
  } catch (error) {}
 }
 return '';
}

async function locateIp(ip) {
 try {
  const result = await fetchJson('https://ipwho.is/' + encodeURIComponent(ip));
  if (result.success && Number.isFinite(Number(result.latitude)) && Number.isFinite(Number(result.longitude))) {
   return { lat: Number(result.latitude), lng: Number(result.longitude), country: result.country || 'نامشخص' };
  }
 } catch (error) {}
 try {
  const result = await fetchJson('https://ipapi.co/' + encodeURIComponent(ip) + '/json/');
  if (!result.error && Number.isFinite(Number(result.latitude)) && Number.isFinite(Number(result.longitude))) {
   return { lat: Number(result.latitude), lng: Number(result.longitude), country: result.country_name || result.country || 'نامشخص' };
  }
 } catch (error) {}
 return null;
}

const geoStatus = document.getElementById('geoStatus');
const legend = document.getElementById('mapLegend');

async function buildGlobe() {
 const buttons = [...document.querySelectorAll('.copy-config')];
 const points = [];
 let nextIndex = 0;

 async function worker() {
  while (nextIndex < buttons.length) {
   const index = nextIndex++;
   const button = buttons[index];
   const host = hostFromConfig(button.dataset.config);
   if (!host) continue;
   try {
    const ip = await resolvePublicIp(host);
    if (!ip) continue;
    const location = await locateIp(ip);
    if (!location) continue;
    const row = button.closest('.config-row');
    const name = row ? row.querySelector('strong')?.textContent || ('کانفیگ ' + (index + 1)) : ('کانفیگ ' + (index + 1));
    points.push({ ...location, name, ip });
   } catch (error) {}
  }
 }
 await Promise.all(Array.from({ length: Math.min(4, buttons.length) }, () => worker()));

 geoStatus.textContent = points.length + ' موقعیت از ' + buttons.length + ' کانفیگ شناسایی شد';
 if (points.length) {
  const unique = new Map();
  points.forEach(point => {
   const key = point.lat.toFixed(2) + ',' + point.lng.toFixed(2);
   if (!unique.has(key)) unique.set(key, []);
   unique.get(key).push(point.name);
  });
  unique.forEach((names, coordinates) => {
   const button = document.createElement('button');
   const country = points.find(p => (p.lat.toFixed(2) + ',' + p.lng.toFixed(2)) === coordinates)?.country || '';
   button.textContent = names.join('، ') + (country ? ' · ' + country : '');
   legend.appendChild(button);
  });
 } else {
  legend.textContent = 'موقعیت دقیق از IP عمومی سرورها دریافت نشد. ممکن است آدرس خصوصی، CDN یا محدودیت سرویس موقعیت‌یابی باشد.';
 }

 const globeElement = document.getElementById('globeViz');
 if (!window.Globe) {
  geoStatus.textContent = 'کتابخانه کره زمین بارگذاری نشد؛ اتصال اینترنت را بررسی کنید.';
  return;
 }
 const width = Math.max(320, globeElement.clientWidth);
 const height = Math.max(300, globeElement.clientHeight || 390);
 window.nmGlobe = Globe()(globeElement)
  .width(width)
  .height(height)
  .globeImageUrl('https://unpkg.com/three-globe/example/img/earth-blue-marble.jpg')
  .bumpImageUrl('https://unpkg.com/three-globe/example/img/earth-topology.png')
  .backgroundColor('#050805')
  .showAtmosphere(true)
  .atmosphereColor('#83d936')
  .atmosphereAltitude(0.18)
  .pointsData(points)
  .pointLat('lat')
  .pointLng('lng')
  .pointColor(() => '#b7ff35')
  .pointAltitude(0.025)
  .pointRadius(0.65)
  .pointLabel(point => point.name + ' · ' + point.country);
 window.nmGlobe.pointOfView({ lat: 25, lng: 20, altitude: 3.1 }, 0);
 window.nmGlobe.controls().autoRotate = true;
 window.nmGlobe.controls().autoRotateSpeed = 0.35;
 window.addEventListener('resize', () => {
  if (window.nmGlobe) {
   window.nmGlobe.width(Math.max(320, globeElement.clientWidth));
   window.nmGlobe.height(Math.max(300, globeElement.clientHeight || 390));
  }
 });
}

buildGlobe().catch(error => {
 geoStatus.textContent = 'در بارگذاری نقشه خطایی رخ داد.';
});
</script></body></html>