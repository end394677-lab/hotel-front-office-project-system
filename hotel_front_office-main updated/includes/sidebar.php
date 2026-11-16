<?php
/**
 * Shared shell: sidebar + top header. Include right after <body> on every page:
 *   subpages: <?php include __DIR__ . '/../includes/sidebar.php'; ?>
 *   root:     <?php include __DIR__ . '/includes/sidebar.php'; ?>
 *
 * To add a module, add one line to $hfoGroups below: 'folder' => ['emoji', 'Label'].
 */

// Web path back to the project root + current module, so links work at any depth.
$hfoRoot    = realpath(__DIR__ . '/..');
$hfoScript  = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
$hfoRel     = ltrim(str_replace('\\', '/', substr($hfoScript, strlen($hfoRoot))), '/');
$hfoDepth   = substr_count($hfoRel, '/');
$hfoBase    = str_repeat('../', $hfoDepth);
$hfoCurrent = $hfoDepth > 0 ? explode('/', $hfoRel)[0] : '';

// Logged-in user shown in the header.
$hfoUserName = 'Ronver Gappi';
$hfoUserRole = 'Front Desk Administrator';

$hfoGroups = [
    'Guests' => ['icon' => 'users', 'items' => [
        'guests'         => ['user', 'Guest Management'],
        'guest_history'  => ['history', 'Guest History'],
        'guest_requests' => ['clipboard', 'Guest Requests'],
    ]],
    'Front Desk' => ['icon' => 'concierge', 'items' => [
        'walk_in'               => ['log-in', 'Walk-in'],
        'checkin_checkout'      => ['key', 'Check-in / Check-out'],
        'stay_extension'        => ['calendar', 'Stay Extension'],
        'stay_guests'           => ['users', 'Stay Guests'],
        'early_late_management' => ['alarm', 'Early / Late Records'],
        'room_assignment'       => ['tag', 'Room Assignment'],
        'room_transfer'         => ['swap', 'Room Transfer'],
        'guest_folio'           => ['receipt', 'Guest Folio'],
    ]],
    'Rooms' => ['icon' => 'bed', 'items' => [
        'rooms'             => ['door', 'Rooms'],
        'room_status'       => ['circle-dot', 'Room Status'],
        'room_availability' => ['search', 'Room Availability'],
    ]],
    'Reports' => ['icon' => 'bar-chart', 'items' => [
        'reports' => ['bar-chart', 'Reports'],
    ]],
];

// Page title for the header.
$hfoTitle = 'Dashboard';
foreach ($hfoGroups as $hfoGName => $hfoG) {
    foreach ($hfoG['items'] as $hfoDir => $hfoIt) {
        if ($hfoDir === $hfoCurrent) { $hfoTitle = $hfoIt[1]; }
    }
}

// Bell badge = open guest requests (Pending + In Progress). Never breaks a page.
$hfoPending = 0;
try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        require_once $hfoRoot . '/config/database.php';
    }
    if (isset($conn) && $conn instanceof mysqli) {
        $hfoRes = $conn->query("SELECT COUNT(*) AS c FROM guest_requests WHERE request_status IN ('Pending','In Progress')");
        if ($hfoRes) { $hfoPending = (int) $hfoRes->fetch_assoc()['c']; }
    }
} catch (Throwable $hfoErr) {
    $hfoPending = 0;
}

$hfoEsc = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= $hfoEsc($hfoBase) ?>includes/icons.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap">
<style>
:root{
  --bg:#FAF5EC;--surface:#FFFDF9;--side:#FBF8F2;--line:#EEE5D3;--line-2:#E4D8C0;
  --khaki:#C3B091;--khaki-d:#6B5F45;--khaki-l:#E6DCC3;--cream-2:#F4ECD8;
  --pink:#D9A3A5;--pink-d:#A8666C;--pink-dd:#8B5258;--pink-l:#F5E1DE;
  --sage:#9FB394;--sage-d:#5F6B3A;--ink:#3F342C;--muted:#8C7D6E;
  --serif:'Playfair Display',Georgia,'Times New Roman',serif;
  --sans:'Inter','Segoe UI',system-ui,-apple-system,Arial,sans-serif;
  --side-w:264px;--bar-h:72px;
}
html{background:var(--bg)}
body{background:var(--bg)!important;color:var(--ink)!important;font-family:var(--sans)!important;font-size:14px;padding:0!important}

/* ---------- sidebar ---------- */
.hfo-toggle{position:absolute;opacity:0;pointer-events:none}
.hfo-sidebar{position:fixed;top:0;left:0;bottom:0;width:var(--side-w);z-index:1000;overflow-y:auto;box-sizing:border-box;
  background:var(--side);border-right:1px solid var(--line);padding:22px 14px 28px}
.hfo-brand{display:flex;align-items:center;gap:12px;padding:2px 8px 22px;text-decoration:none!important;color:var(--ink)!important}
.hfo-logo{flex:none;width:46px;height:46px;border-radius:14px;background:var(--pink);display:flex;align-items:center;justify-content:center;font-size:24px;color:#FFFDF8;box-shadow:0 5px 12px rgba(168,102,108,.28)}
.hfo-logo .ic{margin:0;vertical-align:0}
.hfo-brandtxt b{display:block;font-family:var(--serif);font-size:16px;line-height:1.2;font-weight:700}
.hfo-brandtxt small{display:block;margin-top:4px;font-size:10px;letter-spacing:1.6px;color:var(--muted);font-weight:600}
.hfo-nav{display:flex;flex-direction:column;gap:4px}
.hfo-sidebar a.hfo-link,.hfo-sidebar summary.hfo-link{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:12px;
  color:var(--ink)!important;text-decoration:none!important;font-size:14.5px;font-weight:500;cursor:pointer;list-style:none;transition:background .15s}
.hfo-sidebar summary.hfo-link::-webkit-details-marker{display:none}
.hfo-sidebar a.hfo-link:hover,.hfo-sidebar summary.hfo-link:hover{background:var(--cream-2)}
.hfo-sidebar a.hfo-link.is-active{background:var(--pink);color:#3F2428!important;font-weight:600;box-shadow:0 4px 10px rgba(168,102,108,.22)}
.hfo-sidebar details.has-active>summary.hfo-link{font-weight:600}
.hfo-ico{width:22px;display:flex;align-items:center;justify-content:center;font-size:17px;flex:none;color:var(--khaki-d)}
.hfo-ico .ic{margin:0;vertical-align:0}
.hfo-sidebar a.is-active .hfo-ico{color:#3F2428}
.hfo-chev{margin-left:auto;color:var(--muted);font-size:18px;line-height:1;transition:transform .15s}
.hfo-sidebar details[open]>summary .hfo-chev{transform:rotate(90deg)}
.hfo-sub{display:flex;flex-direction:column;gap:2px;margin:2px 0 6px 24px;padding-left:10px;border-left:2px solid var(--line)}
.hfo-sidebar a.hfo-sublink{display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:10px;font-size:13.5px;color:#5A4D42!important;text-decoration:none!important}
.hfo-sidebar a.hfo-sublink:hover{background:var(--cream-2)}
.hfo-sidebar a.hfo-sublink.is-active{background:var(--pink);color:#3F2428!important;font-weight:600}
.hfo-sublink .hfo-ico{font-size:15px;width:18px}

/* ---------- header ---------- */
.hfo-appbar{position:fixed;top:0;left:var(--side-w);right:0;height:var(--bar-h);z-index:900;box-sizing:border-box;
  background:rgba(251,247,240,.94);backdrop-filter:blur(6px);border-bottom:1px solid var(--line);
  display:flex;align-items:center;justify-content:space-between;padding:0 36px}
.hfo-title{margin:0;font-family:var(--serif);font-size:26px;font-weight:600;color:var(--ink)}
.hfo-right{display:flex;align-items:center;gap:22px}
.hfo-bell{position:relative;font-size:22px;color:var(--khaki-d)!important;display:inline-flex;text-decoration:none!important;line-height:1}
.hfo-badge-n{position:absolute;top:-8px;right:-10px;min-width:18px;height:18px;padding:0 4px;box-sizing:border-box;border-radius:9px;background:#D2574F;color:#fff;font:700 11px/18px var(--sans);text-align:center}
.hfo-userbox{text-align:right;line-height:1.25}
.hfo-userbox b{display:block;font-size:14px;letter-spacing:.3px;text-transform:uppercase;color:var(--ink)}
.hfo-userbox small{color:var(--muted);font-size:12.5px}
.hfo-topbar,.hfo-overlay{display:none}
@media(min-width:901px){body{margin-left:var(--side-w)!important;margin-top:var(--bar-h)!important}}
@media(max-width:900px){
  body{margin-top:60px!important}
  .hfo-appbar{left:0;top:60px;position:static;height:auto;padding:12px 16px;background:var(--bg)}
  .hfo-title{font-size:22px}.hfo-userbox{display:none}
  .hfo-topbar{display:flex;align-items:center;gap:12px;position:fixed;top:0;left:0;right:0;height:60px;padding:0 14px;z-index:999;box-sizing:border-box;background:var(--side);border-bottom:1px solid var(--line);font:600 16px var(--serif)}
  .hfo-burger{cursor:pointer;padding:8px 12px;border-radius:10px;background:var(--pink);color:#3F2428;font:700 16px var(--sans)}
  .hfo-sidebar{transform:translateX(-100%);transition:transform .2s ease}
  .hfo-toggle:checked ~ .hfo-sidebar{box-shadow:6px 0 24px rgba(107,95,69,.18)}
  .hfo-toggle:checked ~ .hfo-sidebar{transform:translateX(0)}
  .hfo-toggle:checked ~ .hfo-overlay{display:block;position:fixed;inset:0;z-index:999;background:rgba(63,52,44,.4)}
}

/* ---------- shared page content ---------- */
body>.container,body>main{max-width:1280px!important;margin:0 auto!important;padding:30px 36px 48px!important}
body>header:not(.hfo-appbar){display:block!important;max-width:1280px;margin:0 auto!important;padding:30px 36px 0!important;background:transparent!important;border:0!important;box-shadow:none!important;color:var(--ink)!important;text-align:left!important}
body>header:not(.hfo-appbar)+main{padding-top:22px!important}
@media(max-width:900px){body>.container,body>main{padding:16px!important}body>header:not(.hfo-appbar){padding:16px 16px 0!important}}
h1,h2,h3{font-family:var(--serif)}
body>.container h1:first-child,body>.container>.top h1,body>header:not(.hfo-appbar) h1{font-family:var(--serif)!important;font-size:32px!important;font-weight:600!important;color:var(--ink)!important;margin:0 0 6px!important;line-height:1.15}
body>.container>h1+p,body>.container>.top+p,body>header:not(.hfo-appbar) p{color:var(--muted)!important;margin:0 0 22px!important;font-size:14px!important}
body>.container>.top{display:flex!important;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:18px}
h2{font-size:19px;color:var(--ink)}h3{color:var(--pink-dd)}
.subtitle,.description{color:var(--muted)!important;margin-bottom:22px!important}
a.back{display:inline-flex;align-items:center;gap:6px;margin-top:18px;padding:9px 16px;border-radius:10px;background:var(--cream-2);border:1px solid var(--line-2);color:var(--khaki-d)!important}
a.back:hover{background:var(--khaki-l)}
.card,.panel,.section,.table-wrapper,.table-container,.table-wrap,.filter,.summary-card{background:var(--surface)!important;border:1px solid var(--line)!important;border-radius:18px!important;box-shadow:0 6px 18px rgba(107,95,69,.06)!important}
.table-wrap,.table-wrapper,.table-container{padding:0!important;overflow:hidden}
.panel,.section,.card{padding:22px 24px!important}
.cards .card,.summary .card,.summary-box,.summary-card{border-top:3px solid var(--pink)!important}
.summary-box{background:var(--surface)!important;border:1px solid var(--line);border-radius:18px!important}
.summary-box h3,.card h2,.card h3{font-family:var(--sans)!important;font-size:12px!important;text-transform:uppercase;letter-spacing:.8px;color:var(--muted)!important}
.number,.summary-box p{font-family:var(--serif);color:var(--ink)!important;font-size:30px!important;font-weight:600!important}
.panel>h2,.section>h2,.card>h2:first-child{padding-bottom:12px;margin:0 0 14px;border-bottom:1px solid var(--line)}
.cards .card>h2{border:0;padding:0;margin:0 0 6px}
table{border-collapse:separate!important;border-spacing:0!important;margin-top:0!important;font-size:13.5px}
th{text-align:left;background:#F3ECDC!important;color:var(--khaki-d)!important;font-size:11.5px!important;text-transform:uppercase;letter-spacing:.8px;font-weight:700;padding:12px 16px!important;border-bottom:1px solid var(--line-2)!important;position:sticky;top:0}
td{padding:12px 16px!important;border-bottom:1px solid #F0E8D6!important;background:var(--surface)}
tbody tr:nth-child(even) td{background:#FCF9F2}
tbody tr:hover td{background:var(--pink-l)!important}
label{font-size:12px!important;font-weight:600!important;color:var(--khaki-d);margin-bottom:5px!important;letter-spacing:.2px}
input,select,textarea{background:var(--surface)!important;border:1px solid var(--line-2)!important;border-radius:10px!important;padding:10px 12px!important;font-size:14px!important;color:var(--ink)!important;font-family:inherit}
input:focus,select:focus,textarea:focus{outline:none!important;border-color:var(--pink-d)!important;box-shadow:0 0 0 3px rgba(217,163,165,.45)}
button,.btn,.print-button,.save-button,.update-button{background:var(--pink-d)!important;color:#FFFDF8!important;border:0!important;border-radius:10px!important;padding:10px 16px!important;font-weight:600!important;font-size:13px!important;font-family:inherit;cursor:pointer;text-decoration:none;transition:background .15s}
button:hover,.btn:hover{background:var(--pink-dd)!important}
.btn-secondary,.reset,.btn-light{background:var(--cream-2)!important;color:var(--khaki-d)!important;border:1px solid var(--khaki)!important}
.btn-secondary:hover,.reset:hover{background:var(--khaki-l)!important}
.btn-edit{background:var(--khaki-d)!important}
.btn-small{padding:6px 10px!important;font-size:12px!important}
button:disabled{background:#E6DCC3!important;color:#A59A84!important}
.checkin{background:#6F7A45!important}.checkout{background:#A8666C!important}
a{color:var(--pink-dd)}.back,.select-link,.view-link{color:var(--pink-dd)!important;font-weight:600;text-decoration:none}
.message,.success,.error,.info,.note{border-radius:12px;padding:12px 16px;margin-bottom:16px;border:1px solid transparent}
.message.success,.success,p.message{background:#EEEBCF;color:#55601F;border-color:#D9D6A8}
.message.error,.error{background:var(--pink-l);color:#8A2F2C;border-color:#E6BDB6}
p.message,p.error{display:block}
.hfo-badge,span.status,.type,.tag{display:inline-block;padding:3px 11px;border-radius:12px;font-size:11.5px;font-weight:700;letter-spacing:.2px;border:1px solid transparent}
.hfo-b-ok{background:#EEEBCF;color:#55601F;border-color:#D9D6A8}
.hfo-b-bad{background:var(--pink-l);color:#8A2F2C;border-color:#E6BDB6}
.hfo-b-warn{background:#F6EBC4;color:#7A5C10;border-color:#E8D894}
.hfo-b-neutral{background:var(--cream-2);color:var(--khaki-d);border-color:var(--khaki-l)}
span.status.available,span.status.cleaning,span.status.checked-in{background:#EEEBCF;color:#55601F}span.status.occupied{background:var(--pink-l);color:#8A2F2C}span.status.reserved{background:#F6EBC4;color:#7A5C10}span.status.maintenance,span.status.checked-out{background:var(--cream-2);color:var(--khaki-d)}
.bar{background:var(--pink-d)!important}.bar-track{background:var(--cream-2)!important}
@media print{.hfo-sidebar,.hfo-appbar,.hfo-topbar{display:none!important}body{margin:0!important;background:#fff!important}}
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
 var map={ok:['available','completed','confirmed','checked-in','checked in','payment','adult','active'],
  bad:['occupied','cancelled','no-show','charge','maintenance'],
  warn:['reserved','pending','in progress','cleaning','early check-in','late check-out'],neutral:['checked-out','checked out','child']};
 document.querySelectorAll('td').forEach(function(td){
  if(td.children.length)return;var t=td.textContent.trim().toLowerCase();
  for(var k in map){if(map[k].indexOf(t)>-1){var s=document.createElement('span');s.className='hfo-badge hfo-b-'+k;s.textContent=td.textContent.trim();td.textContent='';td.appendChild(s);break;}}
 });
});
</script>

<input type="checkbox" id="hfo-toggle" class="hfo-toggle" aria-label="Toggle menu">
<div class="hfo-topbar">
    <label for="hfo-toggle" class="hfo-burger">☰</label>
    <span>Front Office Management</span>
</div>
<label for="hfo-toggle" class="hfo-overlay"></label>

<aside class="hfo-sidebar" aria-label="Main menu">
    <a class="hfo-brand" href="<?= $hfoEsc($hfoBase) ?>index.php">
        <span class="hfo-logo"><i class="ic i-hotel"></i></span>
        <span class="hfo-brandtxt"><b>Front Office Management</b><small>HOTEL FRONT DESK</small></span>
    </a>

    <div class="hfo-nav" role="navigation">
        <a class="hfo-link <?= $hfoCurrent === '' ? 'is-active' : '' ?>" href="<?= $hfoEsc($hfoBase) ?>index.php">
            <span class="hfo-ico"><i class="ic i-dashboard"></i></span>Dashboard
        </a>

        <?php foreach ($hfoGroups as $hfoGName => $hfoG): ?>
            <?php if (count($hfoG['items']) === 1):
                $hfoDir = array_key_first($hfoG['items']); ?>
                <a class="hfo-link <?= $hfoCurrent === $hfoDir ? 'is-active' : '' ?>"
                   href="<?= $hfoEsc($hfoBase . $hfoDir) ?>/">
                    <span class="hfo-ico"><i class="ic i-<?= $hfoEsc($hfoG['icon']) ?>"></i></span><?= $hfoEsc($hfoGName) ?>
                </a>
            <?php else:
                $hfoHasActive = array_key_exists($hfoCurrent, $hfoG['items']); ?>
                <details class="<?= $hfoHasActive ? 'has-active' : '' ?>" <?= $hfoHasActive ? 'open' : '' ?>>
                    <summary class="hfo-link">
                        <span class="hfo-ico"><i class="ic i-<?= $hfoEsc($hfoG['icon']) ?>"></i></span><?= $hfoEsc($hfoGName) ?>
                        <span class="hfo-chev">›</span>
                    </summary>
                    <div class="hfo-sub">
                        <?php foreach ($hfoG['items'] as $hfoDir => $hfoIt): ?>
                            <a class="hfo-sublink <?= $hfoCurrent === $hfoDir ? 'is-active' : '' ?>"
                               <?= $hfoCurrent === $hfoDir ? 'aria-current="page"' : '' ?>
                               href="<?= $hfoEsc($hfoBase . $hfoDir) ?>/">
                                <span class="hfo-ico"><i class="ic i-<?= $hfoEsc($hfoIt[0]) ?>"></i></span><?= $hfoEsc($hfoIt[1]) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</aside>

<header class="hfo-appbar">
    <h1 class="hfo-title"><?= $hfoEsc($hfoTitle) ?></h1>
    <div class="hfo-right">
        <a class="hfo-bell" href="<?= $hfoEsc($hfoBase) ?>guest_requests/"
           title="<?= (int) $hfoPending ?> open guest request(s)"><i class="ic i-bell"></i><?php if ($hfoPending > 0): ?><span class="hfo-badge-n"><?= $hfoPending > 9 ? '9+' : (int) $hfoPending ?></span><?php endif; ?></a>
        <div class="hfo-userbox">
            <b><?= $hfoEsc($hfoUserName) ?></b>
            <small><?= $hfoEsc($hfoUserRole) ?></small>
        </div>
    </div>
</header>
