
<?php
require_once "../config/database.php";

function escapeHTML($value) {
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// Get guest ID from URL
$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit("Invalid guest ID.");
}

// Find the guest in the database
$stmt = $conn->prepare(
    "SELECT * FROM guests WHERE guest_id = ?"
);

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$guest = $result->fetch_assoc();

$stmt->close();

if (!$guest) {
    http_response_code(404);
    exit("Guest not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Guest Profile | Hotel Front Office</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #FFFDF8;
            color: #4B3F36;
        }

        header {
            background: #C99A9A;
            color: #FFFDF8;
            padding: 24px;
        }

        header h1 {
            margin-top: 0;
        }

        main {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .panel {
            background: #FFFDF8;
            border: 1px solid #E6DCC3;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px #8B52580f;
        }

        .detail {
            padding: 14px 0;
            border-bottom: 1px solid #D9CDAE;
            overflow-wrap: anywhere;
        }

        .label {
            display: block;
            color: #A8666C;
            margin-bottom: 6px;
            font-size: 14px;
        }

        .actions {
            margin-top: 25px;
        }

        a {
            display: inline-block;
            margin: 0 12px 10px 0;
            color: #A8666C;
            text-decoration: none;
            font-weight: bold;
        }

        a:hover {
            text-decoration: underline;
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

<header>
    <h1>Guest Profile</h1>
    <p>Guest ID: <?= (int) $guest["guest_id"] ?></p>
</header>

<main>
    <section class="panel">

<?php if (isset($_GET["updated"]) && $_GET["updated"] === "1"): ?>
    <p style="color: #5F6B3A; background: #E9E6C8; padding: 10px 14px; border-radius: 8px;">
        Guest information updated successfully!
    </p>
<?php endif; ?>

        <h2>
            <?= escapeHTML(
                $guest["first_name"] . " " .
                $guest["last_name"]
            ) ?>
        </h2>

        <div class="detail">
            <span class="label">Email Address</span>
            <?= escapeHTML($guest["email"]) ?: "Not provided" ?>
        </div>

        <div class="detail">
            <span class="label">Phone Number</span>
            <?= escapeHTML($guest["phone"]) ?: "Not provided" ?>
        </div>

        <div class="detail">
            <span class="label">Address</span>
            <?= nl2br(escapeHTML($guest["address"])) ?: "Not provided" ?>
        </div>

        <div class="detail">
            <span class="label">Identification Type</span>
            <?= escapeHTML($guest["id_type"]) ?: "Not provided" ?>
        </div>

        <div class="detail">
            <span class="label">Identification Number</span>
            <?= escapeHTML($guest["id_number"]) ?: "Not provided" ?>
        </div>

        <div class="detail">
            <span class="label">Date Registered</span>
            <?= escapeHTML($guest["created_at"]) ?>
        </div>

        <div class="actions">
            <a href="index.php"><i class="ic i-arrow-left"></i> Back to Guest List</a>

            <a href="edit.php?id=<?= (int) $guest["guest_id"] ?>">
                <i class="ic i-pencil"></i> Edit Guest
            </a>
        </div>

    </section>
</main>

</body>
</html>
