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
.footer{text-align:center;color:#71816b;font-size:10px;padding:24px 0 5px;line-height:2;font-family:'Vazirmatn',sans-serif}
@keyframes pulse{50%{opacity:.25}}
@media(max-width:600px){body{padding:20px 12px}.hero{padding:22px}.hero h1{font-size:21px}.grid{grid-template-columns:1fr}.top{align-items:flex-start}.secure{font-size:9px;padding:7px}.actions .btn{flex:1}.brand b{font-size:18px}}
@media(prefers-reduced-motion:reduce){*,*:before,*:after{animation:none!important;transition:none!important}}
</style></head>
<body><main class="wrap">
<header class="top"><div class="brand"><div class="logo">NM</div><div><b>NM TUNNEL</b><small>SECURE CONNECTION SERVICES</small></div></div><div class="secure">■ SYSTEM ONLINE</div></header><div class="social"><a href="https://t.me/NmTunnel_bot" target="_blank">@NmTunnel_bot</a><a href="https://t.me/NM_Tunnel" target="_blank">@NM_Tunnel</a></div>
<section class="hero"><div class="eyebrow">[ NM SYSTEM // SUBSCRIPTION ]</div><h1>مدیریت اشتراک شما</h1><p>وضعیت سرویس و اطلاعات اتصال خود را در این صفحه مشاهده و مدیریت کنید.</p><div class="status <?=nm_h($statusClass)?>"><i class="dot"></i><?=nm_h($statusLabel)?></div></section>
<section class="grid">
<div class="card"><div class="metric">نام کاربری سرویس</div><div class="value"><?=nm_h($invoice['username'] ?? '—')?></div></div>
<div class="card"><div class="metric">موقعیت سرویس</div><div class="value"><?=nm_h($invoice['Service_location'] ?? '—')?></div></div>
<div class="card square-metric"><div class="metric">حجم مصرف‌شده</div><div class="value"><?=nm_h(nm_bytes($used))?></div><div class="bar"><i style="width:<?=$percent?>%"></i></div></div>
<div class="card square-metric"><div class="metric">حجم کل اشتراک</div><div class="value"><?=nm_h(nm_bytes($limit))?></div></div>
<div class="card square-metric"><div class="metric">تعداد کانفیگ‌ها</div><div class="value"><?=count($links)?></div></div>
<div class="card square-metric"><div class="metric">تاریخ انقضا</div><div class="value"><?=nm_h(nm_date($info['expire'] ?? null))?></div></div>
</section>
<section class="card"><h2>مدیریت لینک اشتراک</h2><div class="metric">این لینک را در برنامه کلاینت خود به‌عنوان Subscription وارد کنید.</div><div class="actions"><button class="btn primary" id="copy">کپی لینک اشتراک</button><button class="btn secondary" id="refresh">به‌روزرسانی اطلاعات</button></div></section>
<section class="card" style="margin-top:17px"><h2>سرورهای موجود در اشتراک</h2><div class="servers"><?php if (!$links): ?><div class="metric">در حال حاضر کانفیگی برای نمایش موجود نیست.</div><?php else: foreach ($links as $i=>$link): $fragment=parse_url($link, PHP_URL_FRAGMENT); $proto=strtoupper((string)strtok($link, ':')); ?><div class="server"><div><strong><?=nm_h(rawurldecode($fragment ?: ('کانفیگ '.($i+1))) )?></strong><code><?=nm_h($link)?></code></div><span><?=nm_h($proto)?></span></div><?php endforeach; endif; ?></div></section>
<footer class="footer">NM TUNNEL · OFFICIAL SUBSCRIPTION INTERFACE<br>برای حفظ امنیت، لینک اشتراک خود را در اختیار دیگران قرار ندهید.</footer>
</main>
<script>
const subUrl = <?=json_encode($rawUrl, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>;
document.getElementById('copy').addEventListener('click', async()=>{try{await navigator.clipboard.writeText(subUrl);document.getElementById('copy').textContent='لینک کپی شد ✓';}catch(e){prompt('لینک اشتراک:',subUrl);}});
document.getElementById('refresh').addEventListener('click',()=>location.reload());
</script></body></html>
