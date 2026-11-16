
<?php
require_once "../config/database.php";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    die("Invalid room ID. <a href='index.php'><i class='ic i-arrow-left'></i> Back to Room List</a>");
}

$stmt = $conn->prepare("SELECT * FROM rooms WHERE room_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$room = $result->fetch_assoc();

$stmt->close();

if (!$room) {
    die("Room not found. <a href='index.php'><i class='ic i-arrow-left'></i> Back to Room List</a>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Room</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #FFFDF8;
            margin: 0;
            padding: 25px;
            color: #4B3F36;
        }

        .container {
            max-width: 700px;
            margin: 30px auto;
            background: #FFFDF8;
            border: 1px solid #E6DCC3;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px #8B525812;
        }

        .detail {
            padding: 13px 0;
            border-bottom: 1px solid #D9CDAE;
        }

        .label {
            display: inline-block;
            width: 180px;
            font-weight: bold;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            margin: 15px 5px 0 0;
            background: #A8666C;
            color: #FFFDF8;
            text-decoration: none;
            border-radius: 5px;
        }

        .edit {
            background: #667A35;
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
    <h1>Room Details</h1>

<?php if (isset($_GET["updated"]) && $_GET["updated"] === "1"): ?>
    <p style="color: #5F6B3A; background: #E9E6C8; padding: 10px 14px; border-radius: 8px;">
        Room information updated successfully!
    </p>
<?php endif; ?>

    <div class="detail">
        <span class="label">Room ID:</span>
        <?= e($room["room_id"]) ?>
    </div>

    <div class="detail">
        <span class="label">Room Number:</span>
        <?= e($room["room_number"]) ?>
    </div>

    <div class="detail">
        <span class="label">Room Type:</span>
        <?= e($room["room_type"]) ?>
    </div>

    <div class="detail">
        <span class="label">Rate per Night:</span>
        ₱<?= number_format((float)$room["rate"], 2) ?>
    </div>

    <div class="detail">
        <span class="label">Room Status:</span>
        <?= e($room["room_status"]) ?>
    </div>

    <p>
        <a class="btn" href="index.php"><i class="ic i-arrow-left"></i> Back to Room List</a>
        <a class="btn edit" href="edit.php?id=<?= (int)$room["room_id"] ?>">
            <i class="ic i-pencil"></i> Edit Room
        </a>
    </p>
</div>
</body>
</html>

<?php $conn->close(); ?>
