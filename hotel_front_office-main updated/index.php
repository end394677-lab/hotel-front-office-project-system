<?php
require_once __DIR__ . '/config/database.php';
date_default_timezone_set('Asia/Manila');

function ov_h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function ov_money($v) { return '₱' . number_format((float) $v, 2); }
function ov_esc($conn, $v) { return $conn->real_escape_string((string) $v); }
function ov_val($conn, $sql) {
    $r = $conn->query($sql);
    if (!$r) { return 0; }
    $row = $r->fetch_row();
    return $row ? $row[0] : 0;
}
function ov_rows($conn, $sql) {
    $r = $conn->query($sql);
    $rows = [];
    if ($r) { while ($row = $r->fetch_assoc()) { $rows[] = $row; } }
    return $rows;
}
function ov_walkins($conn, $from, $to) {
    $f = ov_esc($conn, $from); $t = ov_esc($conn, $to);
    return (int) ov_val($conn, "SELECT COUNT(*) FROM reservations
        WHERE booking_source = 'Walk-in' AND reservation_status <> 'Cancelled'
          AND check_in BETWEEN '$f' AND '$t'");
}
function ov_revenue($conn, $from, $to) {
    $f = ov_esc($conn, $from); $t = ov_esc($conn, $to);
    $payments = ov_val($conn, "SELECT COALESCE(SUM(amount), 0) FROM guest_folio
        WHERE transaction_type = 'Payment' AND DATE(transaction_date) BETWEEN '$f' AND '$t'");
    $deposits = ov_val($conn, "SELECT COALESCE(SUM(deposit), 0) FROM reservations
        WHERE reservation_status <> 'Cancelled' AND DATE(created_at) BETWEEN '$f' AND '$t'");
    return (float) $payments + (float) $deposits;
}

/* ---------- selected month ---------- */
$today = date('Y-m-d');
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) { $month = date('Y-m'); }
$mStart = $month . '-01';
$mEnd   = date('Y-m-t', strtotime($mStart));
$anchor = min($today, $mEnd);                 // "as of" date for the Day / Week rows
$monthLabel = date('F Y', strtotime($mStart));

/* ---------- stat cards ---------- */
$walkMonth = ov_walkins($conn, $mStart, $mEnd);
$walkToday = ov_walkins($conn, $today, $today);
$walkTotal = (int) ov_val($conn, "SELECT COUNT(*) FROM reservations
    WHERE booking_source = 'Walk-in' AND reservation_status <> 'Cancelled'");

$roomCounts = ['Available' => 0, 'Occupied' => 0, 'Reserved' => 0, 'Cleaning' => 0, 'Maintenance' => 0];
foreach (ov_rows($conn, "SELECT room_status, COUNT(*) AS c FROM rooms GROUP BY room_status") as $r) {
    $roomCounts[$r['room_status']] = (int) $r['c'];
}
$totalRooms = array_sum($roomCounts);
$occPct = $totalRooms > 0 ? round($roomCounts['Occupied'] / $totalRooms * 100) : 0;

$tdy = ov_esc($conn, $today);
$arrivals = (int) ov_val($conn, "SELECT COUNT(*) FROM reservations
    WHERE check_in = '$tdy' AND reservation_status IN ('Pending','Confirmed','Checked-in')");
$departures = (int) ov_val($conn, "SELECT COUNT(*) FROM reservations
    WHERE check_out = '$tdy' AND reservation_status IN ('Checked-in','Checked-out')");

$reqPending = 0; $reqProgress = 0;
foreach (ov_rows($conn, "SELECT request_status, COUNT(*) AS c FROM guest_requests
        WHERE request_status IN ('Pending','In Progress') GROUP BY request_status") as $r) {
    if ($r['request_status'] === 'Pending') { $reqPending = (int) $r['c']; } else { $reqProgress = (int) $r['c']; }
}

$out = ov_rows($conn, "SELECT COUNT(*) AS n, COALESCE(SUM(bal), 0) AS total FROM (
        SELECT r.reservation_id,
               COALESCE(SUM(CASE WHEN f.transaction_type = 'Charge'  THEN f.amount END), 0)
             - COALESCE(SUM(CASE WHEN f.transaction_type = 'Payment' THEN f.amount END), 0)
             - r.deposit AS bal
        FROM reservations r
        LEFT JOIN guest_folio f ON f.reservation_id = r.reservation_id
        WHERE r.reservation_status <> 'Cancelled'
        GROUP BY r.reservation_id, r.deposit
    ) t WHERE bal > 0");
$outCount = (int) ($out[0]['n'] ?? 0);
$outTotal = (float) ($out[0]['total'] ?? 0);

$totalGuests = (int) ov_val($conn, "SELECT COUNT(*) FROM guests");

/* ---------- 6-month chart: walk-ins vs check-outs ---------- */
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $d = date('Y-m', strtotime("$mStart -$i month"));
    $months[$d] = ['label' => date('M', strtotime($d . '-01')), 'walk' => 0, 'out' => 0];
}
$firstKey = array_key_first($months);
$first = ov_esc($conn, $firstKey . '-01');
$last  = ov_esc($conn, $mEnd);
foreach (ov_rows($conn, "SELECT DATE_FORMAT(check_in, '%Y-%m') AS ym, COUNT(*) AS c FROM reservations
        WHERE booking_source = 'Walk-in' AND reservation_status <> 'Cancelled'
          AND check_in BETWEEN '$first' AND '$last' GROUP BY ym") as $r) {
    if (isset($months[$r['ym']])) { $months[$r['ym']]['walk'] = (int) $r['c']; }
}
foreach (ov_rows($conn, "SELECT DATE_FORMAT(check_out, '%Y-%m') AS ym, COUNT(*) AS c FROM reservations
        WHERE reservation_status = 'Checked-out'
          AND check_out BETWEEN '$first' AND '$last' GROUP BY ym") as $r) {
    if (isset($months[$r['ym']])) { $months[$r['ym']]['out'] = (int) $r['c']; }
}
$peak = 0;
foreach ($months as $m) { $peak = max($peak, $m['walk'], $m['out']); }
$niceMax = max(4, (int) (ceil($peak / 4) * 4));

/* ---------- summary table ---------- */
$wkStart = date('Y-m-d', strtotime('monday this week', strtotime($anchor)));
$wkEnd   = date('Y-m-d', strtotime($wkStart . ' +6 days'));
$yr      = substr($month, 0, 4);
$summary = [
    ['Day',   date('M j, Y', strtotime($anchor)), $anchor,       $anchor],
    ['Week',  date('M j', strtotime($wkStart)) . ' – ' . date('M j', strtotime($wkEnd)), $wkStart, $wkEnd],
    ['Month', $monthLabel, $mStart, $mEnd],
    ['Year',  $yr, $yr . '-01-01', $yr . '-12-31'],
];

/* ---------- recent walk-ins ---------- */
$recent = ov_rows($conn, "SELECT r.reservation_id, g.first_name, g.last_name, rm.room_number,
        r.check_in, r.check_out, r.reservation_status
    FROM reservations r
    JOIN guests g ON g.guest_id = r.guest_id
    LEFT JOIN rooms rm ON rm.room_id = r.room_id
    WHERE r.booking_source = 'Walk-in'
    ORDER BY r.created_at DESC, r.reservation_id DESC LIMIT 6");
$badge = ['Checked-in' => 'ok', 'Confirmed' => 'ok', 'Pending' => 'warn', 'Checked-out' => 'neutral', 'Cancelled' => 'bad', 'No-show' => 'bad'];
$roomColors = ['Available' => '#9FB394', 'Occupied' => '#D9A3A5', 'Reserved' => '#C3B091', 'Cleaning' => '#E8D894', 'Maintenance' => '#B9AFA0'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Front Office Management</title>
    <style>
        .ov { max-width: 1280px; margin: 0 auto; padding: 30px 36px 48px; }
        .ov-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
        .ov-head h2 { font-family: var(--serif); font-size: 32px; font-weight: 600; margin: 0; }
        .ov-head p { margin: 6px 0 0; color: var(--muted); }
        .ov-month label { display: block; margin-bottom: 6px !important; font-size: 14px !important; color: var(--ink) !important; }
        .ov-kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; }
        .ov-kpi { position: relative; background: var(--surface); border: 1px solid var(--line); border-radius: 18px; padding: 22px 24px; box-shadow: 0 6px 18px rgba(107, 95, 69, .06); }
        .ov-kpi small { display: block; font-size: 12px; font-weight: 600; letter-spacing: .9px; text-transform: uppercase; color: var(--muted); padding-right: 56px; }
        .ov-kpi .val { font-family: var(--serif); font-size: 34px; font-weight: 600; margin: 8px 0 6px; line-height: 1.1; }
        .ov-kpi .val.rose { color: var(--pink-d); }
        .ov-kpi .sub { color: var(--muted); font-size: 13px; }
        .ov-ico { color: var(--pink-d); font-size: 22px; position: absolute; top: 20px; right: 22px; width: 46px; height: 46px; border-radius: 12px; background: var(--cream-2); border: 1px solid var(--line); display: flex; align-items: center; justify-content: center; font-size: 21px; }
        .ov-grid { display: grid; gap: 20px; margin-top: 22px; }
        .ov-grid.a { grid-template-columns: minmax(0, 1.9fr) minmax(0, 1fr); }
        .ov-grid.b { grid-template-columns: minmax(0, 1.9fr) minmax(0, 1fr); }
        .ov-panel { background: var(--surface); border: 1px solid var(--line); border-radius: 18px; padding: 22px 24px; box-shadow: 0 6px 18px rgba(107, 95, 69, .06); min-width: 0; }
        .ov-panel h3 { font-family: var(--serif); font-size: 19px; font-weight: 600; margin: 0; color: var(--ink); }
        .ov-ph { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 18px; flex-wrap: wrap; }
        .ov-ph a { font-size: 13px; font-weight: 600; text-decoration: none; }
        .ov-legend { display: flex; gap: 16px; font-size: 12.5px; color: var(--muted); }
        .ov-legend i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 6px; }
        .ov-chart { display: grid; grid-template-columns: 40px 1fr; gap: 10px; height: 270px; padding-top: 10px; }
        .ov-y { position: relative; height: calc(100% - 28px); }
        .ov-y span { position: absolute; right: 0; transform: translateY(50%); font-size: 12px; color: var(--muted); }
        .ov-plot { position: relative; height: 100%; }
        .ov-lines { position: absolute; left: 0; right: 0; top: 0; bottom: 28px; }
        .ov-lines i { position: absolute; left: 0; right: 0; border-top: 1px dashed var(--line); }
        .ov-cols { position: absolute; left: 0; right: 0; top: 0; bottom: 28px; display: flex; }
        .ov-col { flex: 1; height: 100%; display: flex; align-items: flex-end; justify-content: center; gap: 6px; }
        .ov-bar { position: relative; width: 22px; min-height: 3px; border-radius: 7px 7px 0 0; }
        .ov-bar span { position: absolute; top: -18px; left: 50%; transform: translateX(-50%); font-size: 11px; font-weight: 600; color: var(--muted); }
        .ov-x { position: absolute; left: 0; right: 0; bottom: 0; height: 28px; display: flex; }
        .ov-x span { flex: 1; text-align: center; padding-top: 9px; font-size: 12px; color: var(--muted); }
        .ov-note { margin: 10px 0 0; font-size: 12.5px; color: var(--muted); }
        .ov-sum th:not(:first-child), .ov-sum td:not(:first-child) { text-align: right; }
        .ov-sum td:first-child b { display: block; }
        .ov-sum td:first-child span { color: var(--muted); font-size: 12.5px; }
        .ov-tbl { width: 100%; }
        .ov-tablebox { margin: 0 -24px -22px; overflow-x: auto; border-radius: 0 0 18px 18px; }
        .ov-rs { display: grid; grid-template-columns: 1fr auto; gap: 6px 12px; align-items: center; margin-bottom: 16px; }
        .ov-rs .nm { display: flex; align-items: center; gap: 9px; font-weight: 500; }
        .ov-rs .nm i { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
        .ov-rs b { font-family: var(--serif); font-size: 17px; }
        .ov-rs .trk { grid-column: 1 / -1; height: 7px; border-radius: 4px; background: var(--cream-2); overflow: hidden; }
        .ov-rs .trk em { display: block; height: 100%; border-radius: 4px; }
        .ov-empty { color: var(--muted); padding: 22px 0; text-align: center; }
        @media (max-width: 1100px) { .ov-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .ov-grid.a, .ov-grid.b { grid-template-columns: 1fr; } }
        @media (max-width: 640px) { .ov { padding: 16px; } .ov-kpis { grid-template-columns: 1fr; } .ov-head h2 { font-size: 26px; } }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ov">
    <div class="ov-head">
        <div>
            <h2>Front office overview</h2>
            <p>Showing <?= ov_h($monthLabel) ?></p>
        </div>
        <form class="ov-month" method="get">
            <label for="month">Month</label>
            <input type="month" id="month" name="month" value="<?= ov_h($month) ?>" max="<?= ov_h(date('Y-m')) ?>"
                   onchange="this.form.submit()">
        </form>
    </div>

    <section class="ov-kpis">
        <div class="ov-kpi">
            <span class="ov-ico"><i class="ic i-log-in"></i></span>
            <small>Walk-in guests</small>
            <div class="val"><?= $walkMonth ?></div>
            <div class="sub"><?= $walkToday ?> today · <?= $walkTotal ?> all-time</div>
        </div>
        <div class="ov-kpi">
            <span class="ov-ico"><i class="ic i-trending-up"></i></span>
            <small>Room occupancy</small>
            <div class="val"><?= $occPct ?>%</div>
            <div class="sub"><?= $roomCounts['Occupied'] ?> of <?= $totalRooms ?> rooms occupied</div>
        </div>
        <div class="ov-kpi">
            <span class="ov-ico"><i class="ic i-key"></i></span>
            <small>Available rooms</small>
            <div class="val"><?= $roomCounts['Available'] ?></div>
            <div class="sub"><?= $roomCounts['Cleaning'] ?> cleaning · <?= $roomCounts['Maintenance'] ?> maintenance</div>
        </div>
        <div class="ov-kpi">
            <span class="ov-ico"><i class="ic i-concierge"></i></span>
            <small>Arrivals today</small>
            <div class="val"><?= $arrivals ?></div>
            <div class="sub"><?= $departures ?> departure(s) today</div>
        </div>
        <div class="ov-kpi">
            <span class="ov-ico"><i class="ic i-clipboard"></i></span>
            <small>Open guest requests</small>
            <div class="val"><?= $reqPending + $reqProgress ?></div>
            <div class="sub"><?= $reqPending ?> pending · <?= $reqProgress ?> in progress</div>
        </div>
        <div class="ov-kpi">
            <span class="ov-ico"><i class="ic i-hourglass"></i></span>
            <small>Outstanding balances</small>
            <div class="val <?= $outTotal > 0 ? 'rose' : '' ?>"><?= ov_money($outTotal) ?></div>
            <div class="sub"><?= $outCount ?> open folio(s) · <?= $totalGuests ?> guests on file</div>
        </div>
    </section>

    <section class="ov-grid a">
        <div class="ov-panel">
            <div class="ov-ph">
                <h3>Walk-ins and check-outs, last 6 months</h3>
                <div class="ov-legend">
                    <span><i style="background:#9FB394"></i>Walk-ins</span>
                    <span><i style="background:#D9A3A5"></i>Check-outs</span>
                </div>
            </div>
            <div class="ov-chart">
                <div class="ov-y">
                    <?php for ($i = 0; $i <= 4; $i++): ?>
                        <span style="bottom:<?= $i * 25 ?>%"><?= (int) round($niceMax * $i / 4) ?></span>
                    <?php endfor; ?>
                </div>
                <div class="ov-plot">
                    <div class="ov-lines">
                        <?php for ($i = 0; $i <= 4; $i++): ?><i style="bottom:<?= $i * 25 ?>%"></i><?php endfor; ?>
                    </div>
                    <div class="ov-cols">
                        <?php foreach ($months as $m): ?>
                            <div class="ov-col">
                                <div class="ov-bar" style="height:<?= round($m['walk'] / $niceMax * 100, 1) ?>%;background:#9FB394"><?= $m['walk'] > 0 ? '<span>' . $m['walk'] . '</span>' : '' ?></div>
                                <div class="ov-bar" style="height:<?= round($m['out'] / $niceMax * 100, 1) ?>%;background:#D9A3A5"><?= $m['out'] > 0 ? '<span>' . $m['out'] . '</span>' : '' ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="ov-x">
                        <?php foreach ($months as $m): ?><span><?= ov_h($m['label']) ?></span><?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php if ($peak === 0): ?><p class="ov-note">No walk-ins or check-outs recorded in this period yet.</p><?php endif; ?>
        </div>

        <div class="ov-panel">
            <div class="ov-ph"><h3>Summary</h3></div>
            <div class="ov-tablebox">
                <table class="ov-tbl ov-sum">
                    <thead><tr><th>Period</th><th>Walk-ins</th><th>Revenue</th></tr></thead>
                    <tbody>
                    <?php foreach ($summary as $s): ?>
                        <tr>
                            <td><b><?= ov_h($s[0]) ?></b><span><?= ov_h($s[1]) ?></span></td>
                            <td><?= ov_walkins($conn, $s[2], $s[3]) ?></td>
                            <td><?= ov_money(ov_revenue($conn, $s[2], $s[3])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="ov-grid b">
        <div class="ov-panel">
            <div class="ov-ph">
                <h3>Recent walk-ins</h3>
                <a href="walk_in/">Register walk-in →</a>
            </div>
            <?php if (!$recent): ?>
                <div class="ov-empty">No walk-in guests registered yet.</div>
            <?php else: ?>
                <div class="ov-tablebox">
                    <table class="ov-tbl">
                        <thead><tr><th>Guest</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recent as $r): ?>
                            <tr>
                                <td><b><?= ov_h($r['first_name'] . ' ' . $r['last_name']) ?></b></td>
                                <td><?= $r['room_number'] !== null ? ov_h($r['room_number']) : '—' ?></td>
                                <td><?= ov_h(date('M j, Y', strtotime($r['check_in']))) ?></td>
                                <td><?= ov_h(date('M j, Y', strtotime($r['check_out']))) ?></td>
                                <td><span class="hfo-badge hfo-b-<?= $badge[$r['reservation_status']] ?? 'neutral' ?>"><?= ov_h($r['reservation_status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="ov-panel">
            <div class="ov-ph">
                <h3>Room status</h3>
                <a href="room_status/">Manage →</a>
            </div>
            <?php foreach ($roomCounts as $name => $count): ?>
                <div class="ov-rs">
                    <span class="nm"><i style="background:<?= $roomColors[$name] ?>"></i><?= ov_h($name) ?></span>
                    <b><?= $count ?></b>
                    <div class="trk"><em style="width:<?= $totalRooms > 0 ? round($count / $totalRooms * 100) : 0 ?>%;background:<?= $roomColors[$name] ?>"></em></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

</body>
</html>
