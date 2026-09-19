<?php
/**
 * setup.php — the setup wizard.
 *
 * FIRST RUN (no settings yet): anyone can open this page to configure the
 *   site — set an admin password, enter the wedding details, upload a photo
 *   and choose how many invitation links to generate.
 *
 * AFTER SETUP: this same page becomes the settings editor and is locked
 *   behind the admin password, so only the owner can change the details.
 *
 * Reach it at /setup (friendly URL via .htaccess) or /setup.php directly.
 */

require_once __DIR__ . '/config.php';
admin_session_start();

$configured = is_configured();
$errors     = [];
$saved      = false;

// ---------------------------------------------------------------------------
// If the site is already configured, require the admin to sign in before any
// changes can be made.
// ---------------------------------------------------------------------------
if ($configured && !admin_is_authed()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
        if (admin_login((string)($_POST['password'] ?? ''))) {
            header('Location: setup.php');
            exit;
        }
        usleep(500000); // slow brute-force attempts
        $errors[] = 'Incorrect password.';
    }
    render_login($errors);
    exit;
}

// ---------------------------------------------------------------------------
// Handle the settings form submission.
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {

    $existing = load_settings();
    $s        = $existing; // start from what we already have, then overwrite

    // --- Text fields (trimmed) ------------------------------------------------
    $s['name1']       = trim((string)($_POST['name1'] ?? ''));
    $s['name2']       = trim((string)($_POST['name2'] ?? ''));
    $s['eyebrow']     = trim((string)($_POST['eyebrow'] ?? ''));
    $s['subtitle']    = trim((string)($_POST['subtitle'] ?? ''));
    $s['date_text']   = trim((string)($_POST['date_text'] ?? ''));
    $s['time_text']   = trim((string)($_POST['time_text'] ?? ''));
    $s['time_note']   = trim((string)($_POST['time_note'] ?? ''));
    $s['length_text'] = trim((string)($_POST['length_text'] ?? ''));
    $s['venue_name']  = trim((string)($_POST['venue_name'] ?? ''));
    $s['venue_note']  = trim((string)($_POST['venue_note'] ?? ''));
    $s['footer_line'] = trim((string)($_POST['footer_line'] ?? ''));

    // --- Required fields ------------------------------------------------------
    if ($s['name1'] === '' || $s['name2'] === '') {
        $errors[] = 'Please enter both names.';
    }
    if ($s['date_text'] === '' || $s['venue_name'] === '') {
        $errors[] = 'Please enter at least the date and the venue.';
    }

    // --- Admin password -------------------------------------------------------
    // Required on first setup; optional (leave blank to keep) when editing.
    $pw  = (string)($_POST['password'] ?? '');
    $pw2 = (string)($_POST['password2'] ?? '');
    if (!$configured || $pw !== '' || $pw2 !== '') {
        if (strlen($pw) < 6) {
            $errors[] = 'The admin password must be at least 6 characters.';
        } elseif ($pw !== $pw2) {
            $errors[] = 'The two password fields do not match.';
        } else {
            $s['admin_hash'] = password_hash($pw, PASSWORD_DEFAULT);
        }
    }

    // --- Number of invite links (only generated on first setup) --------------
    if (!$configured) {
        $count = (int)($_POST['num_links'] ?? 20);
        $count = max(1, min(200, $count));         // keep it sane: 1–200
        $pad   = max(2, strlen((string)$count));   // zero-pad the labels neatly
        $invites = [];
        for ($i = 1; $i <= $count; $i++) {
            $invites[make_token()] = 'Guest ' . str_pad((string)$i, $pad, '0', STR_PAD_LEFT);
        }
        $s['invites'] = $invites;
    } elseif (empty($s['invites'])) {
        $s['invites'] = []; // safety: never leave this unset
    }

    // --- Photo upload (optional) ---------------------------------------------
    if (!empty($_FILES['photo']['name']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $photoResult = handle_photo_upload($_FILES['photo']);
        if ($photoResult['error']) {
            $errors[] = $photoResult['error'];
        } else {
            // Remove an old photo with a different extension so we don't leave stragglers.
            $old = $existing['photo'] ?? '';
            if ($old !== '' && $old !== $photoResult['path'] && is_file(__DIR__ . '/' . $old)) {
                @unlink(__DIR__ . '/' . $old);
            }
            $s['photo'] = $photoResult['path'];
        }
    }

    // --- Save -----------------------------------------------------------------
    if (empty($errors)) {
        $s['setup_complete'] = true;
        if (save_settings($s)) {
            // Sign the owner in and send them to the dashboard.
            admin_session_start();
            $_SESSION['admin_ok'] = true;
            header('Location: admin.php?welcome=1');
            exit;
        }
        $errors[] = 'Could not save settings — the data folder may not be writable. '
                  . 'Set the "data" folder permissions to 755 and try again.';
    }
}

/**
 * Validate and store an uploaded photo.
 * Returns ['path' => 'uploads/photo.jpg', 'error' => null] or an error string.
 */
function handle_photo_upload(array $file): array {
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
        return ['path' => '', 'error' => 'The photo failed to upload. Please try again.'];
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['path' => '', 'error' => 'The photo is larger than 10 MB. Please use a smaller image.'];
    }
    // Confirm the file really is an image, and pick a safe extension ourselves
    // (never trust the uploaded filename).
    $info = @getimagesize($file['tmp_name']);
    $map  = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF  => 'gif',
    ];
    if ($info === false || !isset($map[$info[2]])) {
        return ['path' => '', 'error' => 'That file is not a supported image (use JPG, PNG, WEBP or GIF).'];
    }
    ensure_dir(UPLOAD_DIR);
    $ext  = $map[$info[2]];
    $dest = UPLOAD_DIR . '/photo.' . $ext;
    if (!@move_uploaded_file($file['tmp_name'], $dest)) {
        return ['path' => '', 'error' => 'Could not save the photo — check the "uploads" folder is writable (755).'];
    }
    return ['path' => 'uploads/photo.' . $ext, 'error' => null];
}

// Values to pre-fill the form with (existing settings, or sensible defaults).
$cur = load_settings();
$val = static function (string $key, string $default = '') use ($cur): string {
    return e($cur[$key] ?? $default);
};
$currentPhoto = $cur['photo'] ?? '';

// ===========================================================================
// From here down is HTML. Two small render helpers keep the login screen and
// the page shell tidy.
// ===========================================================================
function render_login(array $errors): void {
    page_head('Sign in · Setup');
    echo '<form class="card narrow" method="post" autocomplete="off">';
    echo '<h1>Site settings</h1><p class="lede">This site is already set up. Sign in to change the details.</p>';
    foreach ($errors as $err) echo '<div class="err">' . e($err) . '</div>';
    echo '<input type="hidden" name="action" value="login">';
    echo '<label class="fl">Admin password</label>';
    echo '<input class="in" type="password" name="password" autofocus required>';
    echo '<button class="btn" type="submit">Sign in</button>';
    echo '<p class="foot"><a href="admin.php">Go to the dashboard instead</a></p>';
    echo '</form>';
    page_foot();
}

function page_head(string $title): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=EB+Garamond:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root{--ink:#241a12;--cream:#f7f1e4;--panel:#fffaf0;--red:#b21f27;--gold:#c69a3f;--green:#166b45;--muted:#7a6a58;--line:#e3d6ba}
  *{box-sizing:border-box}
  body{margin:0;background:var(--cream);color:var(--ink);font-family:'EB Garamond',Georgia,serif;font-size:17px;line-height:1.55}
  .band{height:16px;background-color:var(--ink);
    background-image:repeating-linear-gradient(45deg,var(--red) 0 3px,transparent 3px 16px),repeating-linear-gradient(-45deg,var(--red) 0 3px,transparent 3px 16px);
    background-size:16px 16px;box-shadow:inset 0 2px 0 var(--gold),inset 0 -2px 0 var(--gold)}
  .wrap{max-width:680px;margin:0 auto;padding:30px 20px 60px}
  h1{font-family:'Cormorant Garamond',serif;font-weight:600;font-size:2rem;color:var(--red);margin:0 0 4px}
  .lede{color:var(--muted);font-style:italic;margin:0 0 22px}
  .card{background:var(--panel);border:1px solid var(--line);padding:26px 24px;margin:0 0 22px}
  .card.narrow{max-width:400px;margin:8vh auto}
  h2.sec{font-family:'Cormorant Garamond',serif;font-weight:600;color:var(--ink);font-size:1.3rem;margin:0 0 4px}
  .sec-hint{color:var(--muted);font-style:italic;font-size:.9rem;margin:0 0 16px}
  .fl{display:block;font-size:.74rem;letter-spacing:.12em;text-transform:uppercase;color:var(--ink);margin:14px 0 6px;font-weight:500}
  .fl .opt{color:var(--muted);text-transform:none;letter-spacing:0;font-style:italic;font-weight:400}
  .in{width:100%;padding:11px 13px;font:inherit;background:#fff;border:1px solid rgba(122,106,88,.5)}
  .in:focus{outline:none;border-color:var(--red);box-shadow:0 0 0 3px rgba(178,31,39,.12)}
  .row{display:flex;gap:14px;flex-wrap:wrap}
  .row > div{flex:1 1 220px}
  .hint{font-size:.85rem;color:var(--muted);font-style:italic;margin:5px 0 0}
  .btn{display:block;width:100%;margin-top:22px;padding:14px;border:none;background:var(--red);color:var(--cream);
    font-family:'Cormorant Garamond',serif;font-size:1.25rem;cursor:pointer}
  .btn:hover{background:#8f1920}
  .err{background:#fdeceb;color:#7a1a1f;border-left:4px solid var(--red);padding:10px 12px;margin:0 0 14px;font-size:.95rem}
  .ok{background:#eaf3ec;color:#155e3b;border-left:4px solid var(--green);padding:10px 12px;margin:0 0 14px;font-size:.95rem}
  .photo-prev{margin-top:10px}
  .photo-prev img{max-height:150px;border:2px solid var(--gold)}
  .foot{margin-top:16px;text-align:center;font-size:.9rem}
  a{color:var(--red)}
</style>
</head>
<body>
<div class="band"></div>
<div class="wrap">
<?php }

function page_foot(): void {
    echo '</div><div class="band"></div></body></html>';
}

// ---------------------------------------------------------------------------
// Render the main settings form.
// ---------------------------------------------------------------------------
page_head($configured ? 'Edit details · Setup' : 'Set up your invitation');
?>

  <h1><?= $configured ? 'Edit your details' : 'Set up your invitation' ?></h1>
  <p class="lede"><?= $configured
      ? 'Change any of the details below, then save.'
      : 'Fill this in once. You can always come back to /setup to make changes.' ?></p>

  <?php foreach ($errors as $err): ?><div class="err"><?= e($err) ?></div><?php endforeach; ?>

  <form method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="action" value="save">

    <!-- ---- The couple & wording -------------------------------------- -->
    <div class="card">
      <h2 class="sec">The couple</h2>
      <p class="sec-hint">The two names shown large at the top of the invitation.</p>
      <div class="row">
        <div>
          <label class="fl">First name(s)</label>
          <input class="in" type="text" name="name1" value="<?= $val('name1') ?>" placeholder="e.g. May Lalmalsawmi" required>
        </div>
        <div>
          <label class="fl">Second name(s)</label>
          <input class="in" type="text" name="name2" value="<?= $val('name2') ?>" placeholder="e.g. David Jon Mumford" required>
        </div>
      </div>
      <label class="fl">Line above the names <span class="opt">(optional)</span></label>
      <input class="in" type="text" name="eyebrow" value="<?= $val('eyebrow', 'Together with their families') ?>">
      <label class="fl">Line below the names <span class="opt">(optional)</span></label>
      <input class="in" type="text" name="subtitle" value="<?= $val('subtitle', 'joyfully invite you to celebrate their wedding') ?>">
    </div>

    <!-- ---- Date, time & venue --------------------------------------- -->
    <div class="card">
      <h2 class="sec">Date, time &amp; venue</h2>
      <p class="sec-hint">Written exactly as you'd like it to appear.</p>
      <label class="fl">Date</label>
      <input class="in" type="text" name="date_text" value="<?= $val('date_text') ?>" placeholder="e.g. Saturday, 7th November 2026" required>
      <div class="row">
        <div>
          <label class="fl">Time</label>
          <input class="in" type="text" name="time_text" value="<?= $val('time_text') ?>" placeholder="e.g. 11:00 in the morning">
        </div>
        <div>
          <label class="fl">Note under the time <span class="opt">(optional)</span></label>
          <input class="in" type="text" name="time_note" value="<?= $val('time_note', 'Please arrive 15 minutes early') ?>">
        </div>
      </div>
      <label class="fl">Expected length <span class="opt">(optional)</span></label>
      <input class="in" type="text" name="length_text" value="<?= $val('length_text') ?>" placeholder="e.g. Around 30 minutes">
      <div class="row">
        <div>
          <label class="fl">Venue</label>
          <input class="in" type="text" name="venue_name" value="<?= $val('venue_name') ?>" placeholder="e.g. Chadderton Town Hall" required>
        </div>
        <div>
          <label class="fl">Room / note <span class="opt">(optional)</span></label>
          <input class="in" type="text" name="venue_note" value="<?= $val('venue_note') ?>" placeholder="e.g. The Green Room">
        </div>
      </div>
      <label class="fl">Footer line <span class="opt">(optional)</span></label>
      <input class="in" type="text" name="footer_line" value="<?= $val('footer_line', 'With love and thanks') ?>">
    </div>

    <!-- ---- Photo ---------------------------------------------------- -->
    <div class="card">
      <h2 class="sec">Photo <span class="opt" style="font-size:1rem;color:var(--muted)">(optional)</span></h2>
      <p class="sec-hint">A landscape or square photo works best; it is cropped to fit automatically.</p>
      <input class="in" type="file" name="photo" accept="image/*">
      <?php if ($currentPhoto && is_file(__DIR__ . '/' . $currentPhoto)): ?>
        <div class="photo-prev">
          <p class="hint">Current photo (upload a new one to replace it):</p>
          <img src="<?= e($currentPhoto) ?>?v=<?= (int)@filemtime(__DIR__ . '/' . $currentPhoto) ?>" alt="Current photo">
        </div>
      <?php endif; ?>
    </div>

    <!-- ---- Invite links (first run only) ---------------------------- -->
    <?php if (!$configured): ?>
    <div class="card">
      <h2 class="sec">Invitation links</h2>
      <p class="sec-hint">Each guest gets their own private link. You can rename them and add more later in the dashboard.</p>
      <label class="fl">How many links to create?</label>
      <input class="in" type="number" name="num_links" value="20" min="1" max="200" style="max-width:140px">
    </div>
    <?php endif; ?>

    <!-- ---- Admin password ------------------------------------------- -->
    <div class="card">
      <h2 class="sec">Admin password</h2>
      <p class="sec-hint">
        <?= $configured
            ? 'Leave blank to keep your current password, or type a new one to change it.'
            : 'You will use this to open your private dashboard (/admin) and view responses.' ?>
      </p>
      <div class="row">
        <div>
          <label class="fl">Password<?= $configured ? ' <span class="opt">(leave blank to keep)</span>' : '' ?></label>
          <input class="in" type="password" name="password" <?= $configured ? '' : 'required' ?>>
        </div>
        <div>
          <label class="fl">Confirm password</label>
          <input class="in" type="password" name="password2" <?= $configured ? '' : 'required' ?>>
        </div>
      </div>
    </div>

    <button class="btn" type="submit"><?= $configured ? 'Save changes' : 'Create my invitation' ?></button>
    <?php if ($configured): ?><p class="foot"><a href="admin.php">Back to the dashboard</a></p><?php endif; ?>
  </form>

<?php page_foot(); ?>
