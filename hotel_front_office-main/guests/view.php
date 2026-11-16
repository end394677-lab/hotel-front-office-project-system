
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
            background: #FFFFFF;
            color: #4A2F37;
        }

        header {
            background: #B5707F;
            color: #FFFCF5;
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
            background: #FFFFFF;
            border: 1px solid #EBD0D3;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px #7e46580f;
        }

        .detail {
            padding: 14px 0;
            border-bottom: 1px solid #E3BFC4;
            overflow-wrap: anywhere;
        }

        .label {
            display: block;
            color: #94566A;
            margin-bottom: 6px;
            font-size: 14px;
        }

        .actions {
            margin-top: 25px;
        }

        a {
            display: inline-block;
            margin: 0 12px 10px 0;
            color: #94566A;
            text-decoration: none;
            font-weight: bold;
        }

        a:hover {
            text-decoration: underline;
        }
    
        /* ---- Dusty Pink / Khaki / Cream theme extras ---- */
        body { color: #4A2F37; }
        input, select, textarea { background-color: #FFFFFF; color: #4A2F37; }
        input:focus, select:focus, textarea:focus {
            outline: 3px solid #D9B1B6; outline-offset: 1px; border-color: #B5707F;
        }
        button:focus-visible, a:focus-visible { outline: 3px solid #D9B1B6; outline-offset: 2px; }
        ::selection { background: #E8CACC; }
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
    <p style="color: #55601F; background: #E9EACB; padding: 10px 14px; border-radius: 8px;">
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
            <a href="index.php">Back to Guest List</a>

            <a href="edit.php?id=<?= (int) $guest["guest_id"] ?>">
                Edit Guest
            </a>
        </div>

    </section>
</main>

</body>
</html>
