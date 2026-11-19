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
<meta name="theme-color" content="#071525">
<title>NM TUNNEL | پنل اشتراک</title>
<style>
:root{color-scheme:dark;--bg:#071525;--panel:#0d2034;--line:#1d3852;--muted:#9bb0c4;--white:#f3f7fb;--cyan:#42d6c5;--blue:#3d8bff}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(ellipse at 85% -10%,#16435a 0,transparent 42%),linear-gradient(145deg,#06111e,#0a1b2d 55%,#071525);color:var(--white);font-family:Tahoma,Arial,sans-serif;min-height:100vh;padding:32px 16px}
.wrap{max-width:900px;margin:auto}.top{display:flex;align-items:center;justify-content:space-between;margin-bottom:26px}.brand{display:flex;gap:13px;align-items:center}.logo{width:48px;height:48px;border-radius:15px;background:linear-gradient(135deg,var(--cyan),#247caa);display:grid;place-items:center;color:#041923;font-weight:900;font-size:19px;box-shadow:0 8px 28px #42d6c533}.brand b{letter-spacing:2px;font-size:19px}.brand small{display:block;color:var(--muted);font-size:11px;margin-top:5px;letter-spacing:1px}.secure{font-size:12px;color:var(--muted);border:1px solid var(--line);border-radius:30px;padding:9px 13px}.hero,.card{background:linear-gradient(145deg,#10263b,#0b1b2c);border:1px solid var(--line);border-radius:22px;box-shadow:0 18px 55px #0003}.hero{padding:30px;margin-bottom:17px;position:relative;overflow:hidden}.hero:after{content:"NM";position:absolute;left:20px;bottom:-40px;font-size:150px;font-weight:900;color:#ffffff08;pointer-events:none}.eyebrow{color:var(--cyan);font-size:12px;letter-spacing:1px}.hero h1{font-size:25px;margin:12px 0 8px}.hero p{color:var(--muted);margin:0;font-size:13px;line-height:2}.status{display:inline-flex;align-items:center;gap:8px;margin-top:20px;padding:9px 13px;border-radius:30px;background:#42d6c51a;color:var(--cyan);font-size:12px}.status.off{background:#ff77771a;color:#ff9999}.dot{width:7px;height:7px;border-radius:50%;background:currentColor}.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-bottom:17px}.card{padding:21px}.card h2{font-size:14px;margin:0 0 17px}.metric{color:var(--muted);font-size:11px;margin-bottom:8px}.value{font-size:17px;font-weight:700;direction:ltr;text-align:right}.bar{height:8px;background:#1a344c;border-radius:20px;overflow:hidden;margin-top:13px}.bar i{display:block;height:100%;background:linear-gradient(90deg,var(--blue),var(--cyan));border-radius:20px}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.btn{border:0;border-radius:12px;padding:13px 17px;font-weight:700;font-size:13px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px}.primary{background:linear-gradient(100deg,#42d6c5,#39a8d9);color:#041923}.secondary{background:#142d44;border:1px solid #29455e;color:var(--white)}.servers{display:grid;gap:10px}.server{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:13px 14px;border:1px solid var(--line);border-radius:13px;background:#091a2a}.server strong{font-size:12px;direction:ltr;overflow-wrap:anywhere}.server span{font-size:11px;color:var(--muted);white-space:nowrap}.footer{text-align:center;color:#6f879e;font-size:11px;padding:24px 0 5px;line-height:2}
@media(max-width:600px){body{padding:22px 12px}.hero{padding:23px}.hero h1{font-size:21px}.grid{grid-template-columns:1fr}.top{align-items:flex-start}.secure{font-size:10px}.actions .btn{flex:1}}
</style></head>
<body><main class="wrap">
<header class="top"><div class="brand"><div class="logo">NM</div><div><b>NM TUNNEL</b><small>SECURE CONNECTION SERVICES</small></div></div><div class="secure">● پنل اختصاصی اشتراک</div></header>
<section class="hero"><div class="eyebrow">SUBSCRIPTION OVERVIEW</div><h1>مدیریت اشتراک شما</h1><p>وضعیت سرویس و اطلاعات اتصال خود را در این صفحه مشاهده و مدیریت کنید.</p><div class="status <?=nm_h($statusClass)?>"><i class="dot"></i><?=nm_h($statusLabel)?></div></section>
<section class="grid">
<div class="card"><div class="metric">نام کاربری سرویس</div><div class="value"><?=nm_h($invoice['username'] ?? '—')?></div></div>
<div class="card"><div class="metric">موقعیت سرویس</div><div class="value"><?=nm_h($invoice['Service_location'] ?? '—')?></div></div>
<div class="card"><div class="metric">ترافیک مصرف‌شده</div><div class="value"><?=nm_h(nm_bytes($used))?></div><div class="bar"><i style="width:<?=$percent?>%"></i></div></div>
<div class="card"><div class="metric">حجم کل سرویس</div><div class="value"><?=nm_h(nm_bytes($limit))?></div></div>
<div class="card"><div class="metric">تعداد کانفیگ‌ها</div><div class="value"><?=count($links)?></div></div>
<div class="card"><div class="metric">تاریخ انقضا</div><div class="value"><?=nm_h(nm_date($info['expire'] ?? null))?></div></div>
</section>
<section class="card"><h2>مدیریت لینک اشتراک</h2><div class="metric">این لینک را در برنامه کلاینت خود به‌عنوان Subscription وارد کنید.</div><div class="actions"><button class="btn primary" id="copy">کپی لینک اشتراک</button><a class="btn secondary" href="<?=nm_h($rawUrl)?>" target="_blank" rel="noopener">مشاهده خروجی خام</a><button class="btn secondary" id="refresh">به‌روزرسانی اطلاعات</button></div></section>
<section class="card" style="margin-top:17px"><h2>سرورهای موجود در اشتراک</h2><div class="servers"><?php if (!$links): ?><div class="metric">در حال حاضر کانفیگی برای نمایش موجود نیست.</div><?php else: foreach ($links as $i=>$link): $fragment=parse_url($link, PHP_URL_FRAGMENT); $proto=strtoupper((string)strtok($link, ':')); ?><div class="server"><strong><?=nm_h($fragment ?: ('سرور '.($i+1)))?></strong><span><?=nm_h($proto)?></span></div><?php endforeach; endif; ?></div></section>
<footer class="footer">NM TUNNEL · پنل رسمی مدیریت اشتراک<br>برای حفظ امنیت، لینک اشتراک خود را در اختیار دیگران قرار ندهید.</footer>
</main>
<script>
const subUrl = <?=json_encode($rawUrl, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>;
document.getElementById('copy').addEventListener('click', async()=>{try{await navigator.clipboard.writeText(subUrl);document.getElementById('copy').textContent='لینک کپی شد ✓';}catch(e){prompt('لینک اشتراک:',subUrl);}});
document.getElementById('refresh').addEventListener('click',()=>location.reload());
</script></body></html>
