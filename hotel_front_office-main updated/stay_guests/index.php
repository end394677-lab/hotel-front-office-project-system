<?php
ob_start();
require_once "../config/database.php";
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/*
 * Stay Guests
 * Add, edit or remove the extra people staying in a room on an existing
 * stay (Confirmed or Checked-in), and correct the adult / children headcount.
 */

$ID_TYPES = ["Passport", "Driver's License", "National ID", "School ID", "Other"];
$AGE_GROUPS = ["Adult", "Child"];
$RELATIONSHIPS = ["Spouse / Partner", "Child", "Parent", "Sibling", "Relative", "Friend", "Colleague", "Other"];
$ELIGIBLE = ["Confirmed", "Checked-in"];
$MAX_COMPANIONS = 20;
$MAX_HEADCOUNT = 30;

$message = "";
$messageType = "";
$tableReady = true;

// The table is created automatically the first time this page is opened.
try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS reservation_guests (
            stay_guest_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
            reservation_id INT UNSIGNED NOT NULL,
            first_name     VARCHAR(100) NOT NULL,
            last_name      VARCHAR(100) NOT NULL,
            age_group      ENUM('Adult','Child') NOT NULL DEFAULT 'Adult',
            relationship   VARCHAR(50)  NOT NULL DEFAULT '',
            phone          VARCHAR(30)  NOT NULL DEFAULT '',
            id_type        VARCHAR(50)  NOT NULL DEFAULT '',
            id_number      VARCHAR(100) NOT NULL DEFAULT '',
            created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (stay_guest_id),
            KEY idx_rg_reservation (reservation_id),
            CONSTRAINT fk_rg_reservation FOREIGN KEY (reservation_id)
                REFERENCES reservations (reservation_id)
                ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");
} catch (Throwable $e) {
    $tableReady = false;
    $message = "Unable to prepare the stay guests table. Please import database/hotel_front_office.sql again or check the database user permissions.";
    $messageType = "error";
}

/** Read and validate the guest form. Returns [data, errorMessage]. */
function readGuestInput(array $idTypes, array $ageGroups): array
{
    $d = [
        "first_name"   => trim($_POST["first_name"] ?? ""),
        "last_name"    => trim($_POST["last_name"] ?? ""),
        "age_group"    => trim($_POST["age_group"] ?? "Adult"),
        "relationship" => trim($_POST["relationship"] ?? ""),
        "phone"        => trim($_POST["phone"] ?? ""),
        "id_type"      => trim($_POST["id_type"] ?? ""),
        "id_number"    => trim($_POST["id_number"] ?? ""),
    ];

    $error = "";

    if ($d["first_name"] === "" || $d["last_name"] === "") {
        $error = "First name and last name are required.";
    } elseif (!in_array($d["age_group"], $ageGroups, true)) {
        $error = "Please choose Adult or Child.";
    } elseif ($d["id_type"] !== "" && !in_array($d["id_type"], $idTypes, true)) {
        $error = "Please choose a valid ID type.";
    } elseif (
        strlen($d["first_name"]) > 100 || strlen($d["last_name"]) > 100 ||
        strlen($d["relationship"]) > 50 || strlen($d["phone"]) > 30 ||
        strlen($d["id_number"]) > 100
    ) {
        $error = "One of the fields is too long.";
    }

    return [$d, $error];
}

/**
 * Keep the reservation headcount at least as big as the people named on it.
 * $dropAdult / $dropChild (0 or 1) lower the count when a named person is
 * removed or moved to the other group.
 */
function syncHeadcount(mysqli $conn, int $rid, int $dropAdult, int $dropChild): void
{
    $stmt = $conn->prepare("SELECT adults, children FROM reservations WHERE reservation_id = ?");
    $stmt->bind_param("i", $rid);
    $stmt->execute();
    $cur = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT
            COALESCE(SUM(age_group = 'Adult'), 0) AS named_adults,
            COALESCE(SUM(age_group = 'Child'), 0) AS named_children
        FROM reservation_guests
        WHERE reservation_id = ?
    ");
    $stmt->bind_param("i", $rid);
    $stmt->execute();
    $named = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // The primary guest always counts as one adult.
    $adults = max(1 + (int) $named["named_adults"], (int) $cur["adults"] - $dropAdult);
    $children = max((int) $named["named_children"], (int) $cur["children"] - $dropChild);

    $stmt = $conn->prepare("UPDATE reservations SET adults = ?, children = ? WHERE reservation_id = ?");
    $stmt->bind_param("iii", $adults, $children, $rid);
    $stmt->execute();
    $stmt->close();
}

/** Lock a reservation row and return it if it can still be changed. */
function lockEligibleStay(mysqli $conn, int $rid, array $eligible): ?array
{
    $stmt = $conn->prepare("
        SELECT reservation_id, adults, children, reservation_status
        FROM reservations
        WHERE reservation_id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("i", $rid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !in_array($row["reservation_status"], $eligible, true)) {
        return null;
    }

    return $row;
}

$reservationId = (int) ($_GET["reservation_id"] ?? 0);
$editId = (int) ($_GET["edit"] ?? 0);
$form = [
    "first_name" => "", "last_name" => "", "age_group" => "Adult",
    "relationship" => "", "phone" => "", "id_type" => "", "id_number" => "",
];

$flash = [
    "added"     => "Guest added to the stay.",
    "updated"   => "Guest details updated.",
    "removed"   => "Guest removed from the stay.",
    "headcount" => "Headcount updated.",
];

if (isset($_GET["msg"]) && isset($flash[$_GET["msg"]])) {
    $message = $flash[$_GET["msg"]];
    $messageType = "success";
}

// ---------------------------------------------------------------
// Handle form submissions
// ---------------------------------------------------------------
if ($tableReady && $_SERVER["REQUEST_METHOD"] === "POST") {

    $reservationId = (int) ($_POST["reservation_id"] ?? 0);
    $action = $_POST["action"] ?? "";
    $redirectMsg = "";

    try {
        $conn->begin_transaction();

        $stay = $reservationId > 0
            ? lockEligibleStay($conn, $reservationId, $ELIGIBLE)
            : null;

        if (!$stay) {
            throw new RuntimeException("This stay was not found or can no longer be changed.");
        }

        if ($action === "add_guest" || $action === "update_guest") {

            [$form, $inputError] = readGuestInput($ID_TYPES, $AGE_GROUPS);

            if ($inputError !== "") {
                throw new RuntimeException($inputError);
            }

            if ($action === "add_guest") {

                $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM reservation_guests WHERE reservation_id = ?");
                $stmt->bind_param("i", $reservationId);
                $stmt->execute();
                $total = (int) $stmt->get_result()->fetch_assoc()["total"];
                $stmt->close();

                if ($total >= $MAX_COMPANIONS) {
                    throw new RuntimeException("A stay can have at most {$MAX_COMPANIONS} additional guests.");
                }

                $stmt = $conn->prepare("
                    INSERT INTO reservation_guests
                        (reservation_id, first_name, last_name, age_group,
                         relationship, phone, id_type, id_number)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "isssssss",
                    $reservationId, $form["first_name"], $form["last_name"],
                    $form["age_group"], $form["relationship"], $form["phone"],
                    $form["id_type"], $form["id_number"]
                );
                $stmt->execute();
                $stmt->close();

                syncHeadcount($conn, $reservationId, 0, 0);
                $redirectMsg = "added";

            } else {

                $stayGuestId = (int) ($_POST["stay_guest_id"] ?? 0);
                $editId = $stayGuestId;

                $stmt = $conn->prepare("SELECT age_group FROM reservation_guests WHERE stay_guest_id = ? AND reservation_id = ?");
                $stmt->bind_param("ii", $stayGuestId, $reservationId);
                $stmt->execute();
                $old = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$old) {
                    throw new RuntimeException("Guest not found on this stay.");
                }

                $stmt = $conn->prepare("
                    UPDATE reservation_guests
                    SET first_name = ?, last_name = ?, age_group = ?,
                        relationship = ?, phone = ?, id_type = ?, id_number = ?
                    WHERE stay_guest_id = ? AND reservation_id = ?
                ");
                $stmt->bind_param(
                    "sssssssii",
                    $form["first_name"], $form["last_name"], $form["age_group"],
                    $form["relationship"], $form["phone"], $form["id_type"],
                    $form["id_number"], $stayGuestId, $reservationId
                );
                $stmt->execute();
                $stmt->close();

                $changed = $old["age_group"] !== $form["age_group"];
                syncHeadcount(
                    $conn, $reservationId,
                    ($changed && $old["age_group"] === "Adult") ? 1 : 0,
                    ($changed && $old["age_group"] === "Child") ? 1 : 0
                );
                $redirectMsg = "updated";
            }

        } elseif ($action === "remove_guest") {

            $stayGuestId = (int) ($_POST["stay_guest_id"] ?? 0);

            $stmt = $conn->prepare("SELECT age_group FROM reservation_guests WHERE stay_guest_id = ? AND reservation_id = ?");
            $stmt->bind_param("ii", $stayGuestId, $reservationId);
            $stmt->execute();
            $old = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$old) {
                throw new RuntimeException("Guest not found on this stay.");
            }

            $stmt = $conn->prepare("DELETE FROM reservation_guests WHERE stay_guest_id = ? AND reservation_id = ?");
            $stmt->bind_param("ii", $stayGuestId, $reservationId);
            $stmt->execute();
            $stmt->close();

            syncHeadcount(
                $conn, $reservationId,
                $old["age_group"] === "Adult" ? 1 : 0,
                $old["age_group"] === "Child" ? 1 : 0
            );
            $redirectMsg = "removed";

        } elseif ($action === "update_headcount") {

            $adults = (int) ($_POST["adults"] ?? 0);
            $children = (int) ($_POST["children"] ?? -1);

            $stmt = $conn->prepare("
                SELECT
                    COALESCE(SUM(age_group = 'Adult'), 0) AS named_adults,
                    COALESCE(SUM(age_group = 'Child'), 0) AS named_children
                FROM reservation_guests
                WHERE reservation_id = ?
            ");
            $stmt->bind_param("i", $reservationId);
            $stmt->execute();
            $named = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($adults < 1 || $children < 0 || $adults > $MAX_HEADCOUNT || $children > $MAX_HEADCOUNT) {
                throw new RuntimeException("Enter at least 1 adult, and no more than {$MAX_HEADCOUNT} adults or children.");
            }

            if ($adults < 1 + (int) $named["named_adults"] || $children < (int) $named["named_children"]) {
                throw new RuntimeException("The headcount cannot be lower than the number of people already listed. Remove them from the list first.");
            }

            $stmt = $conn->prepare("UPDATE reservations SET adults = ?, children = ? WHERE reservation_id = ?");
            $stmt->bind_param("iii", $adults, $children, $reservationId);
            $stmt->execute();
            $stmt->close();

            $redirectMsg = "headcount";

        } else {
            throw new RuntimeException("Unknown action.");
        }

        $conn->commit();

        header("Location: index.php?reservation_id=" . $reservationId . "&msg=" . $redirectMsg);
        exit;

    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }

        $message = ($e instanceof RuntimeException)
            ? $e->getMessage()
            : "Something went wrong and nothing was saved. Please try again.";
        $messageType = "error";
    }
}

// ---------------------------------------------------------------
// Load data for the page
// ---------------------------------------------------------------
$stays = $conn->query("
    SELECT
        r.reservation_id, r.check_in, r.check_out, r.reservation_status,
        g.first_name, g.last_name, rm.room_number
    FROM reservations r
    INNER JOIN guests g ON r.guest_id = g.guest_id
    LEFT JOIN rooms rm ON r.room_id = rm.room_id
    WHERE r.reservation_status IN ('Confirmed', 'Checked-in')
    ORDER BY (r.reservation_status = 'Checked-in') DESC, r.check_out ASC
");

$stay = null;
$companions = [];
$editing = null;

if ($tableReady && $reservationId > 0) {

    $stmt = $conn->prepare("
        SELECT
            r.reservation_id, r.check_in, r.check_out, r.adults, r.children,
            r.reservation_status, r.guest_id,
            g.first_name, g.last_name, rm.room_number, rm.room_type
        FROM reservations r
        INNER JOIN guests g ON r.guest_id = g.guest_id
        LEFT JOIN rooms rm ON r.room_id = rm.room_id
        WHERE r.reservation_id = ?
    ");
    $stmt->bind_param("i", $reservationId);
    $stmt->execute();
    $stay = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($stay && !in_array($stay["reservation_status"], $ELIGIBLE, true)) {
        $message = "This stay is " . $stay["reservation_status"] . ", so guests can no longer be changed.";
        $messageType = "error";
        $stay = null;
    } elseif (!$stay && $message === "") {
        $message = "Stay not found.";
        $messageType = "error";
    }

    if ($stay) {
        $stmt = $conn->prepare("
            SELECT stay_guest_id, first_name, last_name, age_group,
                   relationship, phone, id_type, id_number
            FROM reservation_guests
            WHERE reservation_id = ?
            ORDER BY stay_guest_id ASC
        ");
        $stmt->bind_param("i", $reservationId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $companions[] = $row;
            if ($editId > 0 && (int) $row["stay_guest_id"] === $editId) {
                $editing = $row;
            }
        }
        $stmt->close();

        // Show the saved values when editing (unless a failed save is being redisplayed).
        if ($editing && $_SERVER["REQUEST_METHOD"] !== "POST") {
            $form = [
                "first_name"   => $editing["first_name"],
                "last_name"    => $editing["last_name"],
                "age_group"    => $editing["age_group"],
                "relationship" => $editing["relationship"],
                "phone"        => $editing["phone"],
                "id_type"      => $editing["id_type"],
                "id_number"    => $editing["id_number"],
            ];
        }
    }
}

$h = function ($v) {
    return htmlspecialchars((string) $v, ENT_QUOTES, "UTF-8");
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Stay Guests</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #FFFDF8;
            margin: 0;
            padding: 25px;
            color: #4B3F36;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        h1 {
            margin-bottom: 8px;
        }

        h2 {
            margin-top: 0;
        }

        .subtitle {
            color: #8B5258;
            margin-bottom: 25px;
        }

        .card {
            background: #FFFDF8;
            border: 1px solid #E6DCC3;
            padding: 22px;
            border-radius: 8px;
            margin-bottom: 22px;
            box-shadow: 0 2px 8px #8B525812;
        }

        .message {
            padding: 13px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .success {
            background: #E9E6C8;
            color: #5F6B3A;
        }

        .error {
            background: #F2D9D5;
            color: #A8423F;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        select,
        input {
            width: 100%;
            padding: 11px;
            border: 1px solid #D9CDAE;
            border-radius: 5px;
            margin-bottom: 16px;
        }

        button,
        .btn {
            display: inline-block;
            background: #A8666C;
            color: #FFFDF8;
            border: none;
            padding: 11px 17px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            text-decoration: none;
        }

        button:hover,
        .btn:hover {
            background: #8B5258;
        }

        .btn-light {
            background: #EBCFCB;
            color: #8B5258;
        }

        .btn-light:hover {
            background: #D8A9A9;
        }

        .btn-danger {
            background: #A8423F;
        }

        .btn-danger:hover {
            background: #8A2F2C;
        }

        .btn-small {
            padding: 7px 12px;
            font-size: 13px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 0 18px;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 14px;
        }

        .summary div {
            background: #F4ECD8;
            border-left: 4px solid #C99A9A;
            border-radius: 6px;
            padding: 10px 14px;
        }

        .summary small {
            display: block;
            color: #A8666C;
            margin-bottom: 4px;
        }

        .summary strong {
            font-size: 17px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #E6DCC3;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #C99A9A;
            color: #FFFDF8;
        }

        .tag {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            background: #EBCFCB;
            color: #8B5258;
        }

        .tag-primary {
            background: #F6EBC4;
            color: #7A5C10;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .actions form {
            margin: 0;
        }

        .inline-form {
            display: flex;
            gap: 14px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .inline-form > div {
            flex: 1 1 140px;
            max-width: 200px;
        }

        .inline-form input {
            margin-bottom: 0;
        }

        .empty {
            text-align: center;
            padding: 20px;
            color: #8B5258;
        }

        .back {
            display: inline-block;
            margin-top: 5px;
            color: #A8666C;
            text-decoration: none;
        }

        .note {
            color: #8B5258;
            font-size: 13px;
            margin-top: 12px;
        }

        /* ---- Dusty Pink / Khaki / Cream theme extras ---- */
        input, select, textarea { background-color: #FFFDF8; color: #4B3F36; }
        input:focus, select:focus, textarea:focus {
            outline: 3px solid #D8A9A9; outline-offset: 1px; border-color: #C99A9A;
        }
        button:focus-visible, a:focus-visible { outline: 3px solid #D8A9A9; outline-offset: 2px; }
        ::selection { background: #EBCFCB; }
    </style>
</head>

<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="container">

    <h1>Stay Guests</h1>

    <p class="subtitle">
        Add, edit or remove the people staying in a room after check-in,
        and keep the adult / children headcount correct.
    </p>

    <?php if ($message !== ""): ?>
        <div class="message <?= $h($messageType) ?>">
            <?= $h($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($tableReady): ?>

    <div class="card">
        <h2>Select a Stay</h2>

        <form method="GET">
            <label for="reservation_id">Confirmed or checked-in stay</label>

            <select name="reservation_id" id="reservation_id" required>
                <option value="">-- Select Stay --</option>
                <?php while ($s = $stays->fetch_assoc()): ?>
                    <option
                        value="<?= (int) $s["reservation_id"] ?>"
                        <?= (int) $s["reservation_id"] === $reservationId ? "selected" : "" ?>
                    >
                        #<?= (int) $s["reservation_id"] ?>
                        - <?= $h($s["first_name"] . " " . $s["last_name"]) ?>
                        - Room <?= $h($s["room_number"] ?? "not assigned") ?>
                        - <?= $h($s["reservation_status"]) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <button type="submit"><i class="ic i-concierge"></i> Open Stay</button>
        </form>
    </div>

    <?php if ($stay): ?>

        <div class="card">
            <h2>Stay #<?= (int) $stay["reservation_id"] ?></h2>

            <div class="summary">
                <div>
                    <small>Primary guest</small>
                    <strong><?= $h($stay["first_name"] . " " . $stay["last_name"]) ?></strong>
                </div>
                <div>
                    <small>Room</small>
                    <strong><?= $h($stay["room_number"] ?? "Not assigned") ?>
                        <?= $stay["room_type"] ? "(" . $h($stay["room_type"]) . ")" : "" ?></strong>
                </div>
                <div>
                    <small>Stay dates</small>
                    <strong><?= $h($stay["check_in"]) ?> to <?= $h($stay["check_out"]) ?></strong>
                </div>
                <div>
                    <small>Status</small>
                    <strong><?= $h($stay["reservation_status"]) ?></strong>
                </div>
                <div>
                    <small>Headcount</small>
                    <strong><?= (int) $stay["adults"] ?> adult(s), <?= (int) $stay["children"] ?> child(ren)</strong>
                </div>
            </div>
        </div>

        <div class="card">
            <h2><?= $editing ? "Edit Guest" : "Add a Guest to This Stay" ?></h2>

            <form method="POST">
                <input type="hidden" name="reservation_id" value="<?= (int) $stay["reservation_id"] ?>">
                <input type="hidden" name="action" value="<?= $editing ? "update_guest" : "add_guest" ?>">
                <?php if ($editing): ?>
                    <input type="hidden" name="stay_guest_id" value="<?= (int) $editing["stay_guest_id"] ?>">
                <?php endif; ?>

                <div class="grid">
                    <div>
                        <label for="first_name">First name *</label>
                        <input type="text" name="first_name" id="first_name" maxlength="100"
                               value="<?= $h($form["first_name"]) ?>" required>
                    </div>

                    <div>
                        <label for="last_name">Last name *</label>
                        <input type="text" name="last_name" id="last_name" maxlength="100"
                               value="<?= $h($form["last_name"]) ?>" required>
                    </div>

                    <div>
                        <label for="age_group">Adult or child *</label>
                        <select name="age_group" id="age_group" required>
                            <?php foreach ($AGE_GROUPS as $ag): ?>
                                <option value="<?= $h($ag) ?>" <?= $form["age_group"] === $ag ? "selected" : "" ?>>
                                    <?= $h($ag) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="relationship">Relationship to primary guest</label>
                        <input type="text" name="relationship" id="relationship" maxlength="50"
                               list="relationship_list" value="<?= $h($form["relationship"]) ?>">
                        <datalist id="relationship_list">
                            <?php foreach ($RELATIONSHIPS as $rel): ?>
                                <option value="<?= $h($rel) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div>
                        <label for="phone">Phone</label>
                        <input type="text" name="phone" id="phone" maxlength="30"
                               value="<?= $h($form["phone"]) ?>">
                    </div>

                    <div>
                        <label for="id_type">ID type</label>
                        <select name="id_type" id="id_type">
                            <option value="">-- None --</option>
                            <?php foreach ($ID_TYPES as $it): ?>
                                <option value="<?= $h($it) ?>" <?= $form["id_type"] === $it ? "selected" : "" ?>>
                                    <?= $h($it) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="id_number">ID number</label>
                        <input type="text" name="id_number" id="id_number" maxlength="100"
                               value="<?= $h($form["id_number"]) ?>">
                    </div>
                </div>

                <button type="submit"><?= $editing ? "Save Changes" : "Add Guest" ?></button>

                <?php if ($editing): ?>
                    <a class="btn btn-light"
                       href="index.php?reservation_id=<?= (int) $stay["reservation_id"] ?>"><i class="ic i-x"></i> Cancel</a>
                <?php endif; ?>

                <p class="note">
                    Adding a guest raises the headcount only if it is not already
                    counted. Removing a guest lowers it by one.
                </p>
            </form>
        </div>

        <div class="card">
            <h2>People on This Stay</h2>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Relationship</th>
                            <th>Phone</th>
                            <th>ID</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td>
                                <?= $h($stay["first_name"] . " " . $stay["last_name"]) ?>
                                <span class="tag tag-primary">Primary guest</span>
                            </td>
                            <td>Adult</td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td>
                                <a class="btn btn-light btn-small"
                                   href="../guests/edit.php?id=<?= (int) $stay["guest_id"] ?>"><i class="ic i-pencil"></i> Edit profile</a>
                            </td>
                        </tr>

                        <?php foreach ($companions as $c): ?>
                            <tr>
                                <td><?= $h($c["first_name"] . " " . $c["last_name"]) ?></td>
                                <td><span class="tag"><?= $h($c["age_group"]) ?></span></td>
                                <td><?= $h($c["relationship"] !== "" ? $c["relationship"] : "-") ?></td>
                                <td><?= $h($c["phone"] !== "" ? $c["phone"] : "-") ?></td>
                                <td>
                                    <?php if ($c["id_type"] !== "" || $c["id_number"] !== ""): ?>
                                        <?= $h(trim($c["id_type"] . " " . $c["id_number"])) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a class="btn btn-light btn-small"
                                           href="index.php?reservation_id=<?= (int) $stay["reservation_id"] ?>&amp;edit=<?= (int) $c["stay_guest_id"] ?>"><i class="ic i-pencil"></i> Edit</a>

                                        <form method="POST"
                                              onsubmit="return confirm('Remove this guest from the stay?');">
                                            <input type="hidden" name="reservation_id" value="<?= (int) $stay["reservation_id"] ?>">
                                            <input type="hidden" name="stay_guest_id" value="<?= (int) $c["stay_guest_id"] ?>">
                                            <input type="hidden" name="action" value="remove_guest">
                                            <button type="submit" class="btn-danger btn-small"><i class="ic i-trash"></i> Remove</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (count($companions) === 0): ?>
                            <tr>
                                <td colspan="6" class="empty">
                                    No additional guests listed yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2>Update Headcount</h2>

            <form method="POST" class="inline-form">
                <input type="hidden" name="reservation_id" value="<?= (int) $stay["reservation_id"] ?>">
                <input type="hidden" name="action" value="update_headcount">

                <div>
                    <label for="adults">Adults</label>
                    <input type="number" name="adults" id="adults" min="1"
                           max="<?= (int) $MAX_HEADCOUNT ?>" value="<?= (int) $stay["adults"] ?>" required>
                </div>

                <div>
                    <label for="children">Children</label>
                    <input type="number" name="children" id="children" min="0"
                           max="<?= (int) $MAX_HEADCOUNT ?>" value="<?= (int) $stay["children"] ?>" required>
                </div>

                <button type="submit"><i class="ic i-save"></i> Save Headcount</button>
            </form>

            <p class="note">
                Use this when someone is staying but you do not have their
                details, or when someone leaves. It cannot be lower than the
                number of people already listed above.
            </p>
        </div>

    <?php endif; ?>

    <?php endif; ?>

    <a class="back" href="../index.php">
        <i class="ic i-arrow-left"></i> Back to Dashboard
    </a>

</div>

</body>
</html>
