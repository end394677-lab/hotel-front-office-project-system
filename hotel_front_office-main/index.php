
<?php
require_once "config/database.php";

$guestQuery = $conn->query(
    "SELECT COUNT(*) AS total FROM guests"
);
$guestCount = $guestQuery->fetch_assoc()["total"];

$roomQuery = $conn->query(
    "SELECT COUNT(*) AS total FROM rooms"
);
$roomCount = $roomQuery->fetch_assoc()["total"];

$reservationQuery = $conn->query(
    "SELECT COUNT(*) AS total FROM reservations"
);
$reservationCount = $reservationQuery->fetch_assoc()["total"];

$availableQuery = $conn->query(
    "SELECT COUNT(*) AS total
     FROM rooms
     WHERE room_status = 'Available'"
);
$availableCount = $availableQuery->fetch_assoc()["total"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Hotel Front Office System</title>

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
            margin: 0 0 8px;
        }

        header p {
            margin: 0;
            color: #FBE6E7;
        }

        main {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .cards {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
        }

        .card {
            background: #FFFFFF;
            border: 1px solid #EBD0D3;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 3px 12px #7e46580f;
        }

        .card h2 {
            font-size: 16px;
            color: #94566A;
            margin-top: 0;
        }

        .number {
            font-size: 32px;
            font-weight: bold;
            color: #4A2F37;
        }

        .status {
            margin-top: 25px;
            background: #FFFFFF;
            border: 1px solid #EBD0D3;
            padding: 22px;
            border-radius: 12px;
        }

        @media (max-width: 600px) {
            header {
                padding: 20px;
            }

            main {
                margin-top: 20px;
            }
        }
    
        /* ---- Dusty Pink / Khaki / Cream theme extras ---- */
        body { color: #4A2F37; }
        input, select, textarea { background-color: #FFFFFF; color: #4A2F37; }
        input:focus, select:focus, textarea:focus {
            outline: 3px solid #D9B1B6; outline-offset: 1px; border-color: #B5707F;
        }
        button:focus-visible, a:focus-visible { outline: 3px solid #D9B1B6; outline-offset: 2px; }
        ::selection { background: #E8CACC; }

        header { border-bottom: 4px solid #D9B1B6; }
        .number { color: #94566A; }

        .modules { margin-top: 25px; }
        .modules h3 { margin: 18px 0 10px; font-size: 15px; color: #94566A; }
        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
            gap: 12px;
        }
        .module-link {
            display: block; padding: 14px 16px; background: #FFFFFF;
            border: 1px solid #EBD0D3; border-left: 4px solid #B5707F;
            border-radius: 8px; color: #4A2F37; text-decoration: none; font-weight: bold;
        }
        .module-link:hover { background: #F5EEDC; }
</style>
</head>

<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<header>
    <h1>Hotel Front Office System</h1>
    <p>Dashboard | Hotel Management System</p>
</header>

<main>
    <h2>Dashboard Overview</h2>

    <section class="cards">
        <div class="card">
            <h2>Total Guests</h2>
            <div class="number">
                <?= (int) $guestCount ?>
            </div>
        </div>

        <div class="card">
            <h2>Total Rooms</h2>
            <div class="number">
                <?= (int) $roomCount ?>
            </div>
        </div>

        <div class="card">
            <h2>Walk-in Stays</h2>
            <div class="number">
                <?= (int) $reservationCount ?>
            </div>
        </div>

        <div class="card">
            <h2>Available Rooms</h2>
            <div class="number">
                <?= (int) $availableCount ?>
            </div>
        </div>
    </section>

    <section class="modules">
        <h2>Modules</h2>
        <?php foreach ($hfoGroups as $groupName => $items): ?>
            <h3><?= htmlspecialchars($groupName) ?></h3>
            <div class="module-grid">
                <?php foreach ($items as $dir => $label): ?>
                    <a class="module-link" href="<?= htmlspecialchars($dir) ?>/">
                        <?= htmlspecialchars($label) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </section>
</main>

</body>
</html>
