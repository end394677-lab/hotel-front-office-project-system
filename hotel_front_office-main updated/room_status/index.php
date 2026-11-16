
<?php
include "../config/database.php";

$message = "";
$messageType = "";

// UPDATE ROOM STATUS
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {

    $room_id = intval($_POST["room_id"]);
    $new_status = $_POST["room_status"];

    $allowed_statuses = [
        "Available",
        "Occupied",
        "Reserved",
        "Cleaning",
        "Maintenance"
    ];

    if ($room_id <= 0 || !in_array($new_status, $allowed_statuses, true)) {

        $message = "Invalid room information or status.";
        $messageType = "error";

    } else {

        $conn->begin_transaction();

        try {

            // Lock selected room
            $stmt = $conn->prepare(
                "SELECT room_status
                 FROM rooms
                 WHERE room_id = ?
                 FOR UPDATE"
            );

            $stmt->bind_param("i", $room_id);
            $stmt->execute();

            $room = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$room) {
                throw new Exception("Room not found.");
            }

            // Prevent changing an occupied room while a guest is checked in
            if (
                $new_status !== "Occupied" &&
                $room["room_status"] === "Occupied"
            ) {

                $stmt = $conn->prepare(
                    "SELECT reservation_id
                     FROM reservations
                     WHERE room_id = ?
                     AND reservation_status = 'Checked-in'
                     LIMIT 1"
                );

                $stmt->bind_param("i", $room_id);
                $stmt->execute();

                $activeGuest = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($activeGuest) {
                    throw new Exception(
                        "This room has a checked-in guest. Complete check-out or room transfer first."
                    );
                }
            }

            // Prevent setting a room to Available if it has an active reservation
            if ($new_status === "Available") {

                $stmt = $conn->prepare(
                    "SELECT reservation_id
                     FROM reservations
                     WHERE room_id = ?
                     AND reservation_status IN ('Pending', 'Confirmed', 'Checked-in')
                     AND check_out > CURDATE()
                     LIMIT 1"
                );

                $stmt->bind_param("i", $room_id);
                $stmt->execute();

                $activeReservation = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($activeReservation) {
                    throw new Exception(
                        "This room has an active reservation and cannot be marked Available."
                    );
                }
            }

            // Update room status
            $stmt = $conn->prepare(
                "UPDATE rooms
                 SET room_status = ?
                 WHERE room_id = ?"
            );

            $stmt->bind_param("si", $new_status, $room_id);

            if (!$stmt->execute()) {
                throw new Exception("Unable to update room status.");
            }

            $stmt->close();

            $conn->commit();

            header("Location: index.php?success=1");
            exit;

        } catch (Exception $e) {

            $conn->rollback();

            $message = $e->getMessage();
            $messageType = "error";
        }
    }
}

// SUCCESS MESSAGE
if (isset($_GET["success"])) {
    $message = "Room status updated successfully!";
    $messageType = "success";
}

// SEARCH AND FILTER
$search = trim($_GET["search"] ?? "");
$statusFilter = $_GET["status"] ?? "";

$allowed_filters = [
    "Available",
    "Occupied",
    "Reserved",
    "Cleaning",
    "Maintenance"
];

if (!in_array($statusFilter, $allowed_filters, true)) {
    $statusFilter = "";
}

// SUMMARY COUNTS
$summary = $conn->query(
    "SELECT
        COUNT(*) AS total_rooms,
        SUM(room_status = 'Available') AS available_rooms,
        SUM(room_status = 'Occupied') AS occupied_rooms,
        SUM(room_status = 'Reserved') AS reserved_rooms,
        SUM(room_status = 'Cleaning') AS cleaning_rooms,
        SUM(room_status = 'Maintenance') AS maintenance_rooms
     FROM rooms"
);

$counts = $summary->fetch_assoc();

// GET ROOMS
$sql = "
    SELECT room_id, room_number, room_type, rate, room_status
    FROM rooms
    WHERE (
        room_number LIKE ?
        OR room_type LIKE ?
    )
";

$searchTerm = "%" . $search . "%";

if ($statusFilter !== "") {
    $sql .= " AND room_status = ?";
}

$sql .= " ORDER BY room_number ASC";

$stmt = $conn->prepare($sql);

if ($statusFilter !== "") {
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $statusFilter);
} else {
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
}

$stmt->execute();
$rooms = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Room Status Monitoring</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #FFFDF8;
            margin: 0;
            padding: 25px;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        h1 {
            color: #4B3F36;
        }

        .card {
            background: #FFFDF8;
            border: 1px solid #E6DCC3;
            padding: 22px;
            border-radius: 8px;
            margin-bottom: 22px;
            box-shadow: 0 2px 8px rgba(107,95,69,0.10);
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .summary-box {
            background: #F4ECD8;
            padding: 18px;
            border-radius: 7px;
        }

        .summary-box h3 {
            font-size: 14px;
            color: #8B5258;
            margin: 0 0 8px;
        }

        .summary-box p {
            font-size: 25px;
            font-weight: bold;
            margin: 0;
        }

        .filters {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 10px;
            align-items: end;
        }

        input, select, button {
            padding: 10px;
            border: 1px solid #D9CDAE;
            border-radius: 5px;
            box-sizing: border-box;
        }

        input, select {
            width: 100%;
        }

        button {
            background: #A8666C;
            color: #FFFDF8;
            border: none;
            cursor: pointer;
            padding: 11px 18px;
        }

        button:hover {
            background: #8B5258;
        }

        .success {
            background: #E9E6C8;
            color: #5F6B3A;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .error {
            background: #F2D9D5;
            color: #8A2F2C;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #E6DCC3;
            text-align: left;
        }

        th {
            background: #C99A9A;
            color: #FFFDF8;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .available {
            background: #E9E6C8;
            color: #5F6B3A;
        }

        .occupied {
            background: #F2D9D5;
            color: #8A2F2C;
        }

        .reserved {
            background: #F6EBC4;
            color: #7A5C10;
        }

        .cleaning {
            background: #E9E6C8;
            color: #5F6B3A;
        }

        .maintenance {
            background: #E6DCC3;
            color: #4B3F36;
        }

        .update-form {
            display: flex;
            gap: 7px;
            min-width: 230px;
        }

        .update-form select {
            min-width: 120px;
        }

        .update-form button {
            padding: 9px 12px;
        }

        @media (max-width: 700px) {
            .filters {
                grid-template-columns: 1fr;
            }

            .card {
                overflow-x: auto;
            }

            table {
                min-width: 800px;
            }
        }

        @media print {
            .no-print {
                display: none;
            }

            body {
                background: #FFFDF8;
                padding: 0;
            }

            .card {
                box-shadow: none;
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
</style>

</head>

<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="container">

    <h1>Room Status Monitoring</h1>

    <p>
        Monitor hotel room availability and manage room statuses.
    </p>

    <?php if ($message !== ""): ?>

        <div class="<?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>


    <!-- ROOM SUMMARY -->

    <div class="card">

        <h2>Room Status Summary</h2>

        <div class="summary">

            <div class="summary-box">
                <h3>Total Rooms</h3>
                <p><?php echo $counts["total_rooms"] ?? 0; ?></p>
            </div>

            <div class="summary-box">
                <h3>Available</h3>
                <p><?php echo $counts["available_rooms"] ?? 0; ?></p>
            </div>

            <div class="summary-box">
                <h3>Occupied</h3>
                <p><?php echo $counts["occupied_rooms"] ?? 0; ?></p>
            </div>

            <div class="summary-box">
                <h3>Reserved</h3>
                <p><?php echo $counts["reserved_rooms"] ?? 0; ?></p>
            </div>

            <div class="summary-box">
                <h3>Cleaning</h3>
                <p><?php echo $counts["cleaning_rooms"] ?? 0; ?></p>
            </div>

            <div class="summary-box">
                <h3>Maintenance</h3>
                <p><?php echo $counts["maintenance_rooms"] ?? 0; ?></p>
            </div>

        </div>

    </div>


    <!-- SEARCH AND FILTER -->

    <div class="card no-print">

        <h2>Search and Filter Rooms</h2>

        <form method="GET">

            <div class="filters">

                <div>
                    <label>Search Room</label>

                    <input
                        type="text"
                        name="search"
                        placeholder="Enter room number or room type"
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                </div>

                <div>
                    <label>Filter by Status</label>

                    <select name="status">

                        <option value="">All Statuses</option>

                        <?php foreach ($allowed_filters as $status): ?>

                            <option
                                value="<?php echo $status; ?>"
                                <?php echo ($statusFilter === $status) ? "selected" : ""; ?>
                            >
                                <?php echo $status; ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div>
                    <button type="submit"><i class="ic i-search"></i> Search Rooms</button>
                </div>

            </div>

        </form>

    </div>


    <!-- ROOM LIST -->

    <div class="card">

        <h2>Room Status List</h2>

        <table>

            <thead>

                <tr>
                    <th>Room Number</th>
                    <th>Room Type</th>
                    <th>Rate</th>
                    <th>Current Status</th>
                    <th class="no-print">Update Status</th>
                </tr>

            </thead>

            <tbody>

            <?php if ($rooms && $rooms->num_rows > 0): ?>

                <?php while ($room = $rooms->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?php echo htmlspecialchars($room["room_number"]); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($room["room_type"]); ?>
                        </td>

                        <td>
                            ₱<?php echo number_format($room["rate"], 2); ?>
                        </td>

                        <td>

                            <span class="status <?php
                                echo strtolower($room["room_status"]);
                            ?>">

                                <?php echo htmlspecialchars($room["room_status"]); ?>

                            </span>

                        </td>

                        <td class="no-print">

                            <form method="POST" class="update-form">

                                <input
                                    type="hidden"
                                    name="room_id"
                                    value="<?php echo $room["room_id"]; ?>"
                                >

                                <select name="room_status" required>

                                    <?php foreach ($allowed_filters as $status): ?>

                                        <option
                                            value="<?php echo $status; ?>"
                                            <?php echo ($room["room_status"] === $status) ? "selected" : ""; ?>
                                        >
                                            <?php echo $status; ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                                <button type="submit" name="update_status">
                                    <i class="ic i-refresh"></i> Update
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="5">No rooms found.</td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

        <br>

        <button class="no-print" onclick="window.print()">
            <i class="ic i-printer"></i> Print Room Status Report
        </button>

    </div>

</div>

</body>
</html>

<?php
$stmt->close();
?>