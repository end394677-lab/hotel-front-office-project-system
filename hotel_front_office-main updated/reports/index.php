
<?php
require_once "../config/database.php";

// ------------------------------------------------------------
// Filters (daily reports are now part of this page)
// Default view = today. Clear both dates to see all dates.
// ------------------------------------------------------------
function validDate(string $value): string {
    $d = DateTime::createFromFormat("Y-m-d", $value);
    return ($d && $d->format("Y-m-d") === $value) ? $value : "";
}

if (isset($_GET["filter"])) {
    $dateFrom = validDate(trim($_GET["date_from"] ?? ""));
    $dateTo   = validDate(trim($_GET["date_to"] ?? ""));
} else {
    $dateFrom = $dateTo = date("Y-m-d");
}

if ($dateFrom !== "" && $dateTo !== "" && $dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

$statusOptions = [];
$res = $conn->query("SELECT DISTINCT reservation_status FROM reservations ORDER BY reservation_status");
while ($res && $row = $res->fetch_row()) {
    $statusOptions[] = $row[0];
}

$roomTypes = ["Standard", "Deluxe", "Suite"];

$status   = trim($_GET["status"] ?? "");
$roomType = trim($_GET["room_type"] ?? "");
if (!in_array($status, $statusOptions, true)) { $status = ""; }
if (!in_array($roomType, $roomTypes, true))   { $roomType = ""; }

// Run a prepared query and return all rows
function fetchAll(mysqli $conn, string $sql, string $types = "", array $params = []): array {
    $stmt = $conn->prepare($sql);
    if (!$stmt) { return []; }
    if ($params) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

// Conditions shared by every stay query (status + room type)
$baseWhere = []; $baseTypes = ""; $baseParams = [];
if ($status !== "")   { $baseWhere[] = "r.reservation_status = ?"; $baseTypes .= "s"; $baseParams[] = $status; }
if ($roomType !== "") { $baseWhere[] = "rm.room_type = ?";         $baseTypes .= "s"; $baseParams[] = $roomType; }

// Build "WHERE ..." from base conditions + extra date conditions
function buildWhere(array $base, string $bt, array $bp, array $extra = [], string $et = "", array $ep = []): array {
    $all = array_merge($base, $extra);
    return [
        $all ? "WHERE " . implode(" AND ", $all) : "",
        $bt . $et,
        array_merge($bp, $ep),
    ];
}

$fromJoin = "FROM reservations r
             INNER JOIN guests g ON r.guest_id = g.guest_id
             LEFT JOIN rooms rm ON r.room_id = rm.room_id";

// Stays active during the selected period (overlap)
$overlap = []; $ot = ""; $op = [];
if ($dateFrom !== "") { $overlap[] = "r.check_out >= ?"; $ot .= "s"; $op[] = $dateFrom; }
if ($dateTo !== "")   { $overlap[] = "r.check_in <= ?";  $ot .= "s"; $op[] = $dateTo; }
[$stayWhere, $stayTypes, $stayParams] = buildWhere($baseWhere, $baseTypes, $baseParams, $overlap, $ot, $op);

// Arrivals (check-in inside the period)
$arr = []; $at = ""; $ap = [];
if ($dateFrom !== "") { $arr[] = "r.check_in >= ?"; $at .= "s"; $ap[] = $dateFrom; }
if ($dateTo !== "")   { $arr[] = "r.check_in <= ?"; $at .= "s"; $ap[] = $dateTo; }
[$arrWhere, $arrTypes, $arrParams] = buildWhere($baseWhere, $baseTypes, $baseParams, $arr, $at, $ap);

// Departures (check-out inside the period)
$dep = []; $dt = ""; $dp = [];
if ($dateFrom !== "") { $dep[] = "r.check_out >= ?"; $dt .= "s"; $dp[] = $dateFrom; }
if ($dateTo !== "")   { $dep[] = "r.check_out <= ?"; $dt .= "s"; $dp[] = $dateTo; }
[$depWhere, $depTypes, $depParams] = buildWhere($baseWhere, $baseTypes, $baseParams, $dep, $dt, $dp);

// Summary figures
$sum = fetchAll($conn,
    "SELECT COUNT(*) AS stays,
            COUNT(DISTINCT r.guest_id) AS guests,
            COALESCE(SUM(CASE WHEN r.reservation_status <> 'Cancelled' THEN r.deposit ELSE 0 END), 0) AS deposits
     $fromJoin $stayWhere", $stayTypes, $stayParams)[0] ?? ["stays" => 0, "guests" => 0, "deposits" => 0];

$totalStays   = (int) $sum["stays"];
$totalGuests  = (int) $sum["guests"];
$totalDeposits = (float) $sum["deposits"];

// Room counts (live inventory; room type filter applies)
$roomWhere = $roomType !== "" ? "WHERE room_type = ?" : "";
$roomTypesP = $roomType !== "" ? "s" : "";
$roomParams = $roomType !== "" ? [$roomType] : [];
$roomCounts = fetchAll($conn,
    "SELECT room_status, COUNT(*) AS total FROM rooms $roomWhere GROUP BY room_status ORDER BY room_status",
    $roomTypesP, $roomParams);

$totalRooms = 0; $availableRooms = 0; $occupiedRooms = 0; $cleaningRooms = 0;
foreach ($roomCounts as $rc) {
    $totalRooms += (int) $rc["total"];
    if ($rc["room_status"] === "Available") { $availableRooms = (int) $rc["total"]; }
    if ($rc["room_status"] === "Occupied")  { $occupiedRooms  = (int) $rc["total"]; }
    if ($rc["room_status"] === "Cleaning")  { $cleaningRooms  = (int) $rc["total"]; }
}

// Stays by status
$statuses = fetchAll($conn,
    "SELECT r.reservation_status, COUNT(*) AS total $fromJoin $stayWhere
     GROUP BY r.reservation_status ORDER BY r.reservation_status",
    $stayTypes, $stayParams);

// Arrivals and departures
$selectCols = "r.reservation_id, g.first_name, g.last_name, g.phone, rm.room_number,
               r.check_in, r.check_out, r.adults, r.children, r.reservation_status, r.deposit";

$arrivals = fetchAll($conn,
    "SELECT $selectCols $fromJoin $arrWhere ORDER BY r.check_in DESC, r.reservation_id DESC",
    $arrTypes, $arrParams);

$departures = fetchAll($conn,
    "SELECT $selectCols $fromJoin $depWhere ORDER BY r.check_out DESC, r.reservation_id DESC",
    $depTypes, $depParams);

// Stay list for the period
$stays = fetchAll($conn,
    "SELECT $selectCols $fromJoin $stayWhere ORDER BY r.reservation_id DESC LIMIT 100",
    $stayTypes, $stayParams);

function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, "UTF-8"); }

if ($dateFrom === "" && $dateTo === "") {
    $periodLabel = "All dates";
} elseif ($dateFrom === $dateTo) {
    $periodLabel = date("F j, Y", strtotime($dateFrom));
} else {
    $periodLabel = ($dateFrom !== "" ? date("M j, Y", strtotime($dateFrom)) : "Start")
                 . " – "
                 . ($dateTo !== "" ? date("M j, Y", strtotime($dateTo)) : "Latest");
}

// Reusable table renderer
function renderStayTable(array $rows, string $emptyText): void { ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Stay ID</th>
                    <th>Guest</th>
                    <th>Phone</th>
                    <th>Room</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Guests</th>
                    <th>Status</th>
                    <th>Deposit</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($rows) > 0): ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>#<?= (int) $row["reservation_id"] ?></td>
                        <td><?= e($row["first_name"] . " " . $row["last_name"]) ?></td>
                        <td><?= e($row["phone"] ?? "") ?></td>
                        <td><?= $row["room_number"] !== null ? e($row["room_number"]) : "Not assigned" ?></td>
                        <td><?= e($row["check_in"]) ?></td>
                        <td><?= e($row["check_out"]) ?></td>
                        <td><?= (int) $row["adults"] + (int) $row["children"] ?></td>
                        <td><?= e($row["reservation_status"]) ?></td>
                        <td>₱<?= number_format((float) $row["deposit"], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="9"><?= e($emptyText) ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php }

function renderBars(array $rows, string $labelKey): void {
    $max = 1;
    foreach ($rows as $row) { $max = max($max, (int) $row["total"]); }
    foreach ($rows as $row):
        $count = (int) $row["total"];
        $pct = ($count / $max) * 100; ?>
        <div class="bar-row">
            <span><?= e($row[$labelKey]) ?></span>
            <div class="bar-track"><div class="bar" style="width: <?= $pct ?>%"></div></div>
            <strong><?= $count ?></strong>
        </div>
    <?php endforeach;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports and Analytics</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #FFFDF8;
            color: #4B3F36;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        h1 {
            margin-bottom: 8px;
        }

        .subtitle {
            color: #8B5258;
            margin-bottom: 25px;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .card {
            background: #FFFDF8;
            border: 1px solid #E6DCC3;
            padding: 22px;
            border-radius: 8px;
            box-shadow: 0 2px 8px #8B525812;
        }

        .card h3 {
            margin: 0 0 12px;
            font-size: 15px;
            color: #8B5258;
        }

        .number {
            font-size: 30px;
            font-weight: bold;
            color: #4B3F36;
        }

        .money {
            font-size: 24px;
            overflow-wrap: anywhere;
        }

        .section {
            background: #FFFDF8;
            border: 1px solid #E6DCC3;
            padding: 22px;
            margin-bottom: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px #8B525812;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #E6DCC3;
        }

        th {
            background: #C99A9A;
            color: #FFFDF8;
        }

        .bar-row {
            display: grid;
            grid-template-columns: 140px minmax(70px, 1fr) 35px;
            gap: 12px;
            align-items: center;
            margin: 16px 0;
        }

        .bar-track {
            height: 18px;
            background: #F4ECD8;
            border-radius: 5px;
            overflow: hidden;
        }

        .bar {
            height: 100%;
            background: #A8666C;
            min-width: 0;
        }

        .back {
            display: inline-block;
            margin-top: 5px;
            color: #A8666C;
            text-decoration: none;
        }

        .print-button {
            padding: 10px 15px;
            background: #A8666C;
            color: #FFFDF8;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 0 0 20px;
        }

        @media print {
            body {
                background: #FFFDF8;
                padding: 0;
            }

            .print-button, .back {
                display: none;
            }

            .card, .section {
                box-shadow: none;
                border: 1px solid #E6DCC3;
                break-inside: avoid;
            }
        }

        @media (max-width: 600px) {
            body {
                padding: 12px;
            }

            .bar-row {
                grid-template-columns: 100px minmax(50px, 1fr) 25px;
                gap: 7px;
            }
        }
    
        /* ---- Dusty Pink / Khaki / Cream theme extras ---- */
        body { color: #4B3F36; }
        input, select, textarea { background-color: #FFFDF8; color: #4B3F36; }
        input:focus, select:focus, textarea:focus {
            outline: 3px solid #D8A9A9; outline-offset: 1px; border-color: #C99A9A;
        }
        button:focus-visible, a:focus-visible { outline: 3px solid #D8A9A9; outline-offset: 2px; }
        ::selection { background: #EBCFCB; }

        .filter {
            background: #FFFDF8;
            border: 1px solid #E6DCC3;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 22px;
            display: flex;
            align-items: end;
            gap: 14px;
            flex-wrap: wrap;
            box-shadow: 0 2px 8px #8B525812;
        }
        .filter label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
            font-size: 14px;
        }
        .filter input, .filter select {
            padding: 10px;
            border: 1px solid #D9CDAE;
            border-radius: 5px;
            min-width: 150px;
        }
        .filter button, .filter .reset {
            padding: 10px 16px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        .filter button { background: #A8666C; color: #FFFDF8; }
        .filter .reset { background: #E6DCC3; color: #4B3F36; }
        .period { color: #8B5258; margin: 0 0 18px; }
        @media print { .filter { display: none; } }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="container">

    <h1>Reports and Analytics</h1>
    <p class="subtitle">
        Daily arrivals and departures, stays, rooms, and recorded deposits in one report.
    </p>

    <form method="GET" class="filter">
        <input type="hidden" name="filter" value="1">

        <div>
            <label for="date_from">From</label>
            <input type="date" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
        </div>

        <div>
            <label for="date_to">To</label>
            <input type="date" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
        </div>

        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach ($statusOptions as $opt): ?>
                    <option value="<?= e($opt) ?>" <?= $status === $opt ? "selected" : "" ?>><?= e($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="room_type">Room Type</label>
            <select id="room_type" name="room_type">
                <option value="">All room types</option>
                <?php foreach ($roomTypes as $opt): ?>
                    <option value="<?= e($opt) ?>" <?= $roomType === $opt ? "selected" : "" ?>><?= e($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit"><i class="ic i-search"></i> Apply Filter</button>
        <a class="reset" href="index.php"><i class="ic i-undo"></i> Reset (Today)</a>
    </form>

    <p class="period">Showing: <strong><?= e($periodLabel) ?></strong>
        <?= $status !== "" ? " · Status: " . e($status) : "" ?>
        <?= $roomType !== "" ? " · Room type: " . e($roomType) : "" ?>
    </p>

    <button class="print-button" onclick="window.print()"><i class="ic i-printer"></i> Print Report</button>

    <div class="summary">
        <div class="card"><h3>Arrivals</h3><div class="number"><?= count($arrivals) ?></div></div>
        <div class="card"><h3>Departures</h3><div class="number"><?= count($departures) ?></div></div>
        <div class="card"><h3>Stays in Period</h3><div class="number"><?= $totalStays ?></div></div>
        <div class="card"><h3>Guests</h3><div class="number"><?= $totalGuests ?></div></div>
        <div class="card"><h3>Total Rooms</h3><div class="number"><?= $totalRooms ?></div></div>
        <div class="card"><h3>Available Rooms</h3><div class="number"><?= $availableRooms ?></div></div>
        <div class="card"><h3>Occupied Rooms</h3><div class="number"><?= $occupiedRooms ?></div></div>
        <div class="card"><h3>Rooms Being Cleaned</h3><div class="number"><?= $cleaningRooms ?></div></div>
        <div class="card">
            <h3>Recorded Deposits</h3>
            <div class="number money">₱<?= number_format($totalDeposits, 2) ?></div>
        </div>
    </div>

    <div class="section">
        <h2>Guest Arrivals</h2>
        <?php renderStayTable($arrivals, "No arrivals found for this filter."); ?>
    </div>

    <div class="section">
        <h2>Guest Departures</h2>
        <?php renderStayTable($departures, "No departures found for this filter."); ?>
    </div>

    <div class="section">
        <h2>Stays by Status</h2>
        <?php if (count($statuses) > 0): ?>
            <?php renderBars($statuses, "reservation_status"); ?>
        <?php else: ?>
            <p>No stay records for this filter.</p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>Rooms by Status</h2>
        <?php if (count($roomCounts) > 0): ?>
            <?php renderBars($roomCounts, "room_status"); ?>
        <?php else: ?>
            <p>No room records available.</p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>All Stays in Period <?= count($stays) === 100 ? "(latest 100)" : "" ?></h2>
        <?php renderStayTable($stays, "No stays found for this filter."); ?>
    </div>

    <a class="back" href="../index.php"><i class="ic i-arrow-left"></i> Back to Dashboard</a>

</div>
</body>
</html>
