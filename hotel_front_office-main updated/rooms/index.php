
<?php
require_once "../config/database.php";

$message = "";
$error = "";
$room_number = "";
$room_type = "Standard";
$rate = "";
$room_status = "Available";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Add a new room
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $room_number = trim($_POST["room_number"] ?? "");
    $room_type = $_POST["room_type"] ?? "Standard";
    $rate = $_POST["rate"] ?? "";
    $room_status = $_POST["room_status"] ?? "Available";

    $valid_types = ["Standard", "Deluxe", "Suite"];
    $valid_statuses = [
        "Available", "Occupied", "Maintenance",
        "Reserved", "Cleaning"
    ];

    if (
        $room_number === "" ||
        !is_numeric($rate) ||
        (float)$rate < 0 ||
        !in_array($room_type, $valid_types, true) ||
        !in_array($room_status, $valid_statuses, true)
    ) {
        $error = "Please enter valid room information.";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO rooms (room_number, room_type, rate, room_status)
             VALUES (?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssds",
            $room_number,
            $room_type,
            $rate,
            $room_status
        );

        if ($stmt->execute()) {
            $stmt->close();
            header("Location: index.php?added=1");
            exit;
        } else {
            if ($stmt->errno === 1062) {
                $error = "That room number already exists.";
            } else {
                $error = "Unable to add the room. Please try again.";
            }
            $stmt->close();
        }
    }
}

if (isset($_GET["added"]) && $_GET["added"] === "1") {
    $message = "Room added successfully!";
}

// Search rooms
$search = trim($_GET["q"] ?? "");

if ($search !== "") {
    $stmt = $conn->prepare(
        "SELECT * FROM rooms
         WHERE room_number LIKE ?
            OR room_type LIKE ?
            OR room_status LIKE ?
         ORDER BY room_number"
    );

    $term = "%" . $search . "%";
    $stmt->bind_param("sss", $term, $term, $term);
} else {
    $stmt = $conn->prepare(
        "SELECT * FROM rooms ORDER BY room_number"
    );
}

$stmt->execute();
$rooms = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Management</title>

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
            max-width: 1100px;
            margin: auto;
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .panel {
            background: #FFFDF8;
            border: 1px solid #E6DCC3;
            padding: 22px;
            margin: 20px 0;
            border-radius: 10px;
            box-shadow: 0 2px 8px #8B52580f;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        input, select {
            width: 100%;
            padding: 11px;
            border: 1px solid #D9CDAE;
            border-radius: 5px;
            font-size: 15px;
        }

        button, .btn {
            display: inline-block;
            padding: 10px 14px;
            border: none;
            border-radius: 5px;
            background: #A8666C;
            color: #FFFDF8;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-secondary {
            background: #7D714A;
        }

        .btn-edit {
            background: #667A35;
        }

        .message {
            color: #5F6B3A;
        }

        .error {
            color: #A8423F;
        }

        .search-form {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .search-form input {
            flex: 1;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #D9CDAE;
            text-align: left;
            white-space: nowrap;
        }

        th {
            background: #C99A9A;
            color: #FFFDF8;
        }

        @media (max-width: 600px) {
            body {
                padding: 12px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .search-form {
                flex-wrap: wrap;
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

    <div class="top">
        <h1>Room Management</h1>
        <a class="btn btn-secondary" href="../index.php">
            <i class="ic i-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <?php if ($message !== ""): ?>
        <p class="message"><?= e($message) ?></p>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <div class="panel">
        <h2>Add New Room</h2>

        <form method="POST" action="index.php">
            <div class="form-grid">
                <div>
                    <label for="room_number">Room Number</label>
                    <input
                        type="text"
                        id="room_number"
                        name="room_number"
                        value="<?= e($room_number) ?>"
                        required
                    >
                </div>

                <div>
                    <label for="room_type">Room Type</label>
                    <select id="room_type" name="room_type" required>
                        <?php foreach (["Standard", "Deluxe", "Suite"] as $type): ?>
                            <option
                                value="<?= e($type) ?>"
                                <?= $room_type === $type ? "selected" : "" ?>
                            >
                                <?= e($type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="rate">Rate per Night (₱)</label>
                    <input
                        type="number"
                        id="rate"
                        name="rate"
                        min="0"
                        step="0.01"
                        value="<?= e($rate) ?>"
                        required
                    >
                </div>

                <div>
                    <label for="room_status">Room Status</label>
                    <select id="room_status" name="room_status" required>
                        <?php foreach (
                            ["Available", "Occupied", "Maintenance", "Reserved", "Cleaning"]
                            as $status
                        ): ?>
                            <option
                                value="<?= e($status) ?>"
                                <?= $room_status === $status ? "selected" : "" ?>
                            >
                                <?= e($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <p>
                <button type="submit"><i class="ic i-plus"></i> Add Room</button>
            </p>
        </form>
    </div>

    <div class="panel">
        <h2>Room List</h2>

        <form method="GET" action="index.php" class="search-form">
            <input
                type="text"
                name="q"
                placeholder="Search room number, type, or status"
                value="<?= e($search) ?>"
            >
            <button type="submit"><i class="ic i-search"></i> Search</button>
            <a class="btn btn-secondary" href="index.php"><i class="ic i-undo"></i> Reset</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Room ID</th>
                    <th>Room Number</th>
                    <th>Room Type</th>
                    <th>Rate per Night</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody>
                <?php if ($rooms->num_rows > 0): ?>
                    <?php while ($room = $rooms->fetch_assoc()): ?>
                        <tr>
                            <td><?= e($room["room_id"]) ?></td>
                            <td><?= e($room["room_number"]) ?></td>
                            <td><?= e($room["room_type"]) ?></td>
                            <td>₱<?= number_format((float)$room["rate"], 2) ?></td>
                            <td><?= e($room["room_status"]) ?></td>
                            <td>
                                <a class="btn"
                                   href="view.php?id=<?= (int)$room["room_id"] ?>">
                                    <i class="ic i-eye"></i> View
                                </a>
                                <a class="btn btn-edit"
                                   href="edit.php?id=<?= (int)$room["room_id"] ?>">
                                    <i class="ic i-pencil"></i> Edit
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No rooms found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
