<?php
// ONE-TIME CLEANUP: permanently deletes the 3 sample guests
// (Maria Santos, Juan Dela Cruz, Anna Reyes) and everything linked to them.
// Open http://127.0.0.1:8000/remove_sample_guests.php once, then DELETE this file.
require_once __DIR__ . "/config/database.php";

$emails = [
    "maria.santos@example.com",
    "juan.delacruz@example.com",
    "anna.reyes@example.com",
];
$in = "'" . implode("','", array_map([$conn, "real_escape_string"], $emails)) . "'";

$conn->begin_transaction();
try {
    // Stays first (guests can't be deleted while reservations exist).
    // Folio, early/late, transfers and extra stay guests cascade automatically.
    $conn->query("DELETE FROM reservations WHERE guest_id IN (SELECT guest_id FROM (SELECT guest_id FROM guests WHERE email IN ($in)) t)");
    $res = $conn->query("DELETE FROM guests WHERE email IN ($in)");
    $deleted = $conn->affected_rows;
    $conn->commit();
    echo "Done. Guests deleted: " . (int)$deleted . ". Now delete remove_sample_guests.php from the project.";
} catch (Throwable $e) {
    $conn->rollback();
    echo "Failed: " . htmlspecialchars($e->getMessage());
}
