<?php
/**
 * Shared sidebar. Include right after <body> on every page:
 *   subpages: <?php include __DIR__ . '/../includes/sidebar.php'; ?>
 *   root:     <?php include __DIR__ . '/includes/sidebar.php'; ?>
 *
 * To add a module, add one line to $hfoGroups below.
 */

// Work out the web path back to the project root and the current module,
// so links work from any folder depth and any install directory name.
$hfoRoot   = realpath(__DIR__ . '/..');
$hfoScript = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
$hfoRel    = ltrim(str_replace('\\', '/', substr($hfoScript, strlen($hfoRoot))), '/');
$hfoDepth  = substr_count($hfoRel, '/');
$hfoBase   = str_repeat('../', $hfoDepth);
$hfoCurrent = $hfoDepth > 0 ? explode('/', $hfoRel)[0] : '';

$hfoGroups = [
    'Guests' => [
        'guests'         => 'Guest Management',
        'guest_history'  => 'Guest History',
        'guest_requests' => 'Guest Requests',
    ],
    'Front desk' => [
        'walk_in'               => 'Walk-in',
        'checkin_checkout'      => 'Check-in / Check-out',
        'stay_extension'        => 'Stay Extension',
        'stay_guests'           => 'Stay Guests (add people)',
        'early_late_management' => 'Early / Late Records',
        'room_assignment'       => 'Room Assignment',
        'room_transfer'         => 'Room Transfer',
        'guest_folio'           => 'Guest Folio',
    ],
    'Rooms' => [
        'rooms'             => 'Rooms',
        'room_status'       => 'Room Status',
        'room_availability' => 'Room Availability',
    ],
    'Reports' => [
        'reports' => 'Reports',
    ],
];

$hfoEsc = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
?>
<style>
    .hfo-toggle { position: absolute; opacity: 0; pointer-events: none; }
    .hfo-sidebar {
        position: fixed; top: 0; left: 0; bottom: 0; width: 264px; z-index: 1000;
        background: #9C5B6E; color: #FBE6E7;
        overflow-y: auto; padding: 0 0 24px; box-sizing: border-box;
        font-family: Arial, sans-serif; font-size: 14px; line-height: 1.3;
    }
    .hfo-brand {
        display: block; padding: 22px 20px 18px; color: #FFFCF5; text-decoration: none;
        font-size: 17px; font-weight: bold; border-bottom: 3px solid #F6DAD6; margin-bottom: 8px;
    }
    .hfo-brand small { display: block; margin-top: 4px; font-size: 12px; font-weight: normal; color: #EBD0D3; }
    .hfo-group { padding: 14px 20px 6px; font-size: 12px; font-weight: bold; color: #F6DAD6; }
    .hfo-sidebar a.hfo-link {
        display: block; margin: 1px 10px; padding: 9px 10px; border-radius: 6px;
        color: #FBE6E7; text-decoration: none;
    }
    .hfo-sidebar a.hfo-link:hover { background: rgba(255, 252, 245, 0.12); color: #FFFCF5; }
    .hfo-sidebar a.hfo-link.is-active { background: #FFFCF5; color: #7E4658; font-weight: bold; }
    .hfo-sidebar a:focus-visible { outline: 3px solid #D9B1B6; outline-offset: 1px; }
    .hfo-topbar, .hfo-overlay { display: none; }

    @media (min-width: 901px) {
        body { margin-left: 264px !important; }
    }
    @media (max-width: 900px) {
        body { margin-top: 54px !important; }
        .hfo-topbar {
            display: flex; align-items: center; gap: 12px; position: fixed; top: 0; left: 0; right: 0;
            height: 54px; padding: 0 14px; z-index: 999; box-sizing: border-box;
            background: #9C5B6E; color: #FFFCF5; font: bold 16px Arial, sans-serif;
            border-bottom: 3px solid #D9B1B6;
        }
        .hfo-burger {
            cursor: pointer; padding: 8px 12px; border-radius: 6px; background: #7E4658;
            color: #FFFCF5; font: bold 14px Arial, sans-serif;
        }
        .hfo-sidebar { transform: translateX(-100%); transition: transform .2s ease; box-shadow: 4px 0 18px #7e465844; }
        .hfo-toggle:checked ~ .hfo-sidebar { transform: translateX(0); }
        .hfo-toggle:checked ~ .hfo-overlay {
            display: block; position: fixed; inset: 0; z-index: 999; background: #4A2F3766;
        }
        .hfo-toggle:focus-visible ~ .hfo-topbar .hfo-burger { outline: 3px solid #D9B1B6; }
    }
    @media (prefers-reduced-motion: reduce) { .hfo-sidebar { transition: none; } }
    @media print {
        .hfo-sidebar, .hfo-topbar, .hfo-overlay { display: none !important; }
        body { margin: 0 !important; }
    }
</style>

<input type="checkbox" id="hfo-toggle" class="hfo-toggle" aria-label="Toggle menu">
<div class="hfo-topbar">
    <label for="hfo-toggle" class="hfo-burger">Menu</label>
    <span>Hotel Front Office</span>
</div>
<label for="hfo-toggle" class="hfo-overlay"></label>

<aside class="hfo-sidebar" aria-label="Main menu">
    <a class="hfo-brand" href="<?= $hfoEsc($hfoBase) ?>index.php">
        Hotel Front Office
        <small>Front desk system</small>
    </a>

    <a class="hfo-link <?= $hfoCurrent === '' ? 'is-active' : '' ?>"
       href="<?= $hfoEsc($hfoBase) ?>index.php">Dashboard</a>

    <?php foreach ($hfoGroups as $hfoGroupName => $hfoItems): ?>
        <div class="hfo-group"><?= $hfoEsc($hfoGroupName) ?></div>
        <?php foreach ($hfoItems as $hfoDir => $hfoLabel): ?>
            <a class="hfo-link <?= $hfoCurrent === $hfoDir ? 'is-active' : '' ?>"
               <?= $hfoCurrent === $hfoDir ? 'aria-current="page"' : '' ?>
               href="<?= $hfoEsc($hfoBase . $hfoDir) ?>/"><?= $hfoEsc($hfoLabel) ?></a>
        <?php endforeach; ?>
    <?php endforeach; ?>
</aside>
