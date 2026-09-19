<?php
/**
 * admin.php — the private dashboard.
 *
 * Sign in with the admin password (set in the setup wizard) to see every RSVP,
 * summary totals, and the full list of invitation links. From here you can
 * rename each guest's label, copy their personal link to send it, add more
 * links, and export everything to CSV.
 */

require_once __DIR__ . '/config.php';

// Not set up yet? Send the owner to the wizard first.
if (!is_configured()) {
    header('Location: setup.php');
    exit;
}

admin_session_start();

// ---- Log out ---------------------------------------------------------------
if (isset($_GET['logout'])) {
    admin_logout();
    header('Location: admin.php');
    exit;
}

// ---- Log in ----------------------------------------------------------------
$loginError = '';
if (!admin_is_authed() && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (admin_login((string)($_POST['password'] ?? ''))) {
        header('Location: admin.php');
        exit;
    }
    usleep(500000); // slow brute-force attempts
    $loginError = 'Incorrect password.';
}

$authed = admin_is_authed();

// ===========================================================================
// Everything below only runs for a signed-in admin.
// ===========================================================================
if ($authed) {

    $S       = load_settings();
    $invites = $S['invites'] ?? [];

    // ---- Guest-link management: rename labels -----------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_labels') {
        $newLabels = $_POST['label'] ?? [];
        if (is_array($newLabels)) {
            foreach ($invites as $tok => $old) {
                if (isset($newLabels[$tok])) {
                    $label = trim((string)$newLabels[$tok]);
                    $invites[$tok] = ($label !== '') ? $label : $old;
                }
            }
            $S['invites'] = $invites;
            save_settings($S);
        }
        header('Location: admin.php#links');
        exit;
    }

    // ---- Guest-link management: add more links ----------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_links') {
        $add   = max(1, min(100, (int)($_POST['add_count'] ?? 5)));
        $start = count($invites);
        $pad   = max(2, strlen((string)($start + $add)));
        for ($i = 1; $i <= $add; $i++) {
            $invites[make_token()] = 'Guest ' . str_pad((string)($start + $i), $pad, '0', STR_PAD_LEFT);
        }
        $S['invites'] = $invites;
        save_settings($S);
        header('Location: admin.php#links');
        exit;
    }

    // ---- Load responses (oldest first) ------------------------------------
    $rows = load_rsvps();
    usort($rows, static function ($a, $b) {
        return strcmp((string)($a['time'] ?? ''), (string)($b['time'] ?? ''));
    });

    // Keep only the latest response per invitation (for the totals).
    $latest = [];
    foreach ($rows as $r) {
        $latest[$r['token'] ?? ''] = $r;
    }

    // ---- Totals -----------------------------------------------------------
    $householdsInvited = count($invites);
    $responded = $accepts = $declines = $headcount = 0;
    foreach ($latest as $r) {
        $responded++;
        if (($r['attending'] ?? '') === 'Accept') {
            $accepts++;
            $headcount += 1 + (trim((string)($r['plusone'] ?? '')) !== '' ? 1 : 0);
        } else {
            $declines++;
        }
    }
    $awaiting = max(0, $householdsInvited - $responded);

    // ---- CSV export -------------------------------------------------------
    if (isset($_GET['export'])) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="wedding-rsvps.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date', 'Invitation', 'Attending', 'Invitee', 'Plus one', 'Message']);
        foreach (array_reverse($rows) as $r) { // newest first
            fputcsv($out, [
                $r['time'] ?? '',
                $r['label'] ?? '',
                ($r['attending'] ?? '') === 'Accept' ? 'Accepts' : 'Declines',
                $r['invitee'] ?? '',
                $r['plusone'] ?? '',
                $r['message'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }
}

/** Format an ISO timestamp for display. */
function fmtDate(string $iso): string {
    $t = strtotime($iso);
    return $t ? date('j M Y · H:i', $t) : $iso;
}
$coupleName = trim((string)setting('name1') . ' & ' . (string)setting('name2'), ' &');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>RSVP Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=EB+Garamond:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root{
    --ink:#241a12; --cream:#f7f1e4; --cream-2:#efe6d3; --panel:#fffaf0;
    --red:#b21f27; --gold:#c69a3f; --green:#166b45; --muted:#7a6a58; --line:#e3d6ba;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--cream);color:var(--ink);font-family:'EB Garamond',Georgia,serif;font-size:17px;line-height:1.55}
  .puan-band{height:14px;background-color:var(--ink);
    background-image:
      repeating-linear-gradient(45deg,var(--red) 0 3px,transparent 3px 16px),
      repeating-linear-gradient(-45deg,var(--red) 0 3px,transparent 3px 16px);
    background-size:16px 16px;box-shadow:inset 0 2px 0 var(--gold),inset 0 -2px 0 var(--gold)}
  .wrap{max-width:960px;margin:0 auto;padding:26px 20px 60px}

  /* Login */
  .login{max-width:380px;margin:8vh auto;background:var(--panel);border:1px solid var(--line);padding:34px 30px;text-align:center}
  .login h1{font-family:'Cormorant Garamond',serif;font-weight:600;font-size:1.7rem;margin:0 0 4px;color:var(--red)}
  .login p{color:var(--muted);margin:0 0 22px;font-style:italic}
  .login input{width:100%;padding:12px 14px;font:inherit;background:#fff;border:1px solid rgba(122,106,88,.5);margin-bottom:14px}
  .login button{width:100%;padding:13px;border:none;background:var(--red);color:var(--cream);
    font-family:'Cormorant Garamond',serif;font-size:1.2rem;cursor:pointer}
  .login button:hover{background:#8f1920}
  .err{background:#fdeceb;color:#7a1a1f;border-left:4px solid var(--red);padding:10px 12px;margin:0 0 16px;text-align:left;font-size:.95rem}

  /* Header */
  .top{display:flex;align-items:baseline;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:6px}
  .top h1{font-family:'Cormorant Garamond',serif;font-weight:600;font-size:2rem;margin:0;color:var(--ink)}
  .top .sub{color:var(--muted);font-style:italic}
  .actions a{display:inline-block;margin-left:10px;padding:8px 14px;border:1px solid var(--line);
    color:var(--ink);text-decoration:none;background:var(--panel);font-size:.95rem}
  .actions a:hover{border-color:var(--gold)}
  .actions a.primary{background:var(--green);color:#fff;border-color:var(--green)}

  .welcome{background:#eaf3ec;border:1px solid #bfe0c9;color:#155e3b;padding:14px 16px;margin:16px 0;font-size:.98rem}

  /* Stat cards */
  .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin:22px 0 30px}
  .stat{background:var(--panel);border:1px solid var(--line);padding:18px;text-align:center}
  .stat .n{font-family:'Cormorant Garamond',serif;font-size:2.4rem;line-height:1;color:var(--red)}
  .stat .n.green{color:var(--green)}
  .stat .n.muted{color:var(--muted)}
  .stat .k{font-size:.72rem;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);margin-top:8px}

  /* Sections + tables */
  h2.section{font-family:'Cormorant Garamond',serif;font-weight:600;color:var(--red);font-size:1.5rem;margin:26px 0 10px}
  .hintline{color:var(--muted);font-style:italic;font-size:.9rem;margin:-4px 0 12px}
  .tablewrap{overflow-x:auto;border:1px solid var(--line);background:var(--panel)}
  table{border-collapse:collapse;width:100%;min-width:640px}
  th,td{text-align:left;padding:11px 14px;border-bottom:1px solid var(--line);vertical-align:top}
  th{font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:var(--gold);background:#fcf6ea}
  tr:last-child td{border-bottom:none}
  .badge{display:inline-block;padding:4px 10px;font-size:.85rem;border:1px solid;white-space:nowrap}
  .badge.yes{background:#eaf3ec;color:var(--green);border-color:#bfe0c9}
  .badge.no{background:#f6efe0;color:#8a6d3b;border-color:#e2d4b3}
  .badge.wait{background:#f3eee2;color:var(--muted);border-color:var(--line)}
  .who{font-weight:500}
  .plus{color:var(--muted);font-size:.92rem}
  .msg{color:#3f3327;max-width:320px}
  .empty{padding:26px;text-align:center;color:var(--muted);font-style:italic}

  /* Invite-link manager */
  .lblinput{width:100%;min-width:160px;padding:8px 10px;font:inherit;background:#fff;border:1px solid rgba(122,106,88,.4)}
  .lblinput:focus{outline:none;border-color:var(--red)}
  .rowbtns{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px}
  .savebtn{padding:10px 18px;border:none;background:var(--red);color:var(--cream);
    font-family:'Cormorant Garamond',serif;font-size:1.1rem;cursor:pointer}
  .savebtn:hover{background:#8f1920}
  .addform{display:flex;gap:8px;align-items:center;margin-top:14px;color:var(--muted);flex-wrap:wrap}
  .addform input{width:70px;padding:8px 10px;font:inherit;border:1px solid rgba(122,106,88,.4)}
  .addform button{padding:9px 14px;border:1px solid var(--line);background:var(--panel);cursor:pointer;font:inherit}
  .addform button:hover{border-color:var(--gold)}

  /* copy-to-clipboard buttons */
  .copybtn{font:inherit;color:inherit;background:none;border:none;padding:0;cursor:pointer}
  .copybtn:active{transform:translateY(1px)}
  .copybtn.small{border:1px solid var(--line);background:var(--panel);padding:7px 12px;font-size:.9rem;white-space:nowrap}
  .copybtn.small::before{content:"🔗";opacity:.55;margin-right:6px;font-size:.85em}
  .copybtn.small:hover{border-color:var(--gold);background:#fff}
  .who.copybtn{font-weight:500;border-bottom:1px dashed rgba(198,154,63,.55)}
  .who.copybtn:hover{border-bottom-color:var(--gold);color:var(--red)}
  .copybtn.copied{color:var(--green)!important;border-color:var(--green)!important}

  .toast{position:fixed;left:50%;bottom:26px;transform:translateX(-50%) translateY(20px);
    background:var(--ink);color:var(--cream);padding:10px 18px;font-size:.95rem;border:1px solid var(--gold);
    opacity:0;pointer-events:none;transition:opacity .2s,transform .2s;z-index:50}
  .toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
</style>
</head>
<body>
<div class="puan-band"></div>
<div class="wrap">

<?php if (!$authed): ?>

  <form class="login" method="post" autocomplete="off">
    <h1>RSVP Dashboard</h1>
    <p><?= e($coupleName) ?></p>
    <?php if ($loginError): ?><div class="err"><?= e($loginError) ?></div><?php endif; ?>
    <input type="hidden" name="action" value="login">
    <input type="password" name="password" placeholder="Password" autofocus required>
    <button type="submit">Sign in</button>
  </form>

<?php else: ?>

  <div class="top">
    <div>
      <h1>Wedding RSVPs</h1>
      <div class="sub"><?= e($coupleName) ?></div>
    </div>
    <div class="actions">
      <a href="setup.php">Edit details</a>
      <a class="primary" href="?export=1">Export CSV</a>
      <a href="?logout=1">Log out</a>
    </div>
  </div>

  <?php if (isset($_GET['welcome'])): ?>
    <div class="welcome"><strong>Your invitation is live.</strong> Scroll down to
      <a href="#links">Invitation links</a>, copy each guest's link and send it to them.</div>
  <?php endif; ?>

  <div class="stats">
    <div class="stat"><div class="n"><?= (int)$responded ?><span style="font-size:1.2rem;color:var(--muted)">/<?= (int)$householdsInvited ?></span></div><div class="k">Responded</div></div>
    <div class="stat"><div class="n green"><?= (int)$headcount ?></div><div class="k">Guests coming</div></div>
    <div class="stat"><div class="n green"><?= (int)$accepts ?></div><div class="k">Accepts</div></div>
    <div class="stat"><div class="n muted"><?= (int)$declines ?></div><div class="k">Declines</div></div>
    <div class="stat"><div class="n"><?= (int)$awaiting ?></div><div class="k">Awaiting reply</div></div>
  </div>

  <!-- ---- Responses ---------------------------------------------------- -->
  <h2 class="section">Responses</h2>
  <p class="hintline">Tip: click a guest's name to copy their personal invite link.</p>
  <div class="tablewrap">
    <?php if (empty($rows)): ?>
      <div class="empty">No responses yet.</div>
    <?php else: ?>
      <table>
        <thead><tr><th>Received</th><th>Invitation</th><th>Attending</th><th>Guest(s)</th><th>Message</th></tr></thead>
        <tbody>
        <?php foreach (array_reverse($rows) as $r):
            $acc  = (($r['attending'] ?? '') === 'Accept');
            $plus = trim((string)($r['plusone'] ?? '')); ?>
          <tr>
            <td><?= e(fmtDate((string)($r['time'] ?? ''))) ?></td>
            <td><?= e((string)($r['label'] ?? '')) ?></td>
            <td><span class="badge <?= $acc ? 'yes' : 'no' ?>"><?= $acc ? 'Accepts' : 'Declines' ?></span></td>
            <td>
              <button type="button" class="who copybtn" data-token="<?= e((string)($r['token'] ?? '')) ?>" title="Copy this invite link"><?= e((string)($r['invitee'] ?? '')) ?></button>
              <?php if ($plus !== ''): ?><div class="plus">+ <?= e($plus) ?></div><?php endif; ?>
            </td>
            <td class="msg"><?= nl2br(e((string)($r['message'] ?? ''))) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- ---- Invitation links --------------------------------------------- -->
  <h2 class="section" id="links">Invitation links (<?= count($invites) ?>)</h2>
  <p class="hintline">Rename a label so you know whose is whose, click “Copy link” to send it, then Save.</p>
  <form method="post">
    <input type="hidden" name="action" value="save_labels">
    <div class="tablewrap">
      <table>
        <thead><tr><th style="width:120px">Status</th><th>Guest label</th><th style="width:150px">Link</th></tr></thead>
        <tbody>
        <?php foreach ($invites as $tok => $label):
            $resp = $latest[$tok] ?? null;
            if ($resp === null)                       { $cls = 'wait'; $txt = 'Awaiting'; }
            elseif (($resp['attending'] ?? '') === 'Accept') { $cls = 'yes'; $txt = 'Accepts'; }
            else                                      { $cls = 'no';  $txt = 'Declines'; } ?>
          <tr>
            <td><span class="badge <?= $cls ?>"><?= $txt ?></span></td>
            <td><input class="lblinput" type="text" name="label[<?= e($tok) ?>]" value="<?= e($label) ?>"></td>
            <td><button type="button" class="copybtn small" data-token="<?= e($tok) ?>" title="Copy invite link">Copy link</button></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="rowbtns">
      <button class="savebtn" type="submit">Save labels</button>
    </div>
  </form>

  <form method="post" class="addform">
    <input type="hidden" name="action" value="add_links">
    <span>Need more?</span>
    <label>Add <input type="number" name="add_count" value="5" min="1" max="100"> link(s)</label>
    <button type="submit">Add</button>
  </form>

<?php endif; ?>

</div>
<div class="puan-band"></div>

<?php if ($authed): ?>
<script>
/* Copy a guest's invite link to the clipboard when their name or "Copy link"
   button is clicked. The link is built from this page's own address, so it
   works no matter which domain or folder the site is hosted in. */
(function () {
  var toast;
  function showToast(msg) {
    if (!toast) { toast = document.createElement('div'); toast.className = 'toast'; document.body.appendChild(toast); }
    toast.textContent = msg;
    toast.classList.add('show');
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { toast.classList.remove('show'); }, 1700);
  }
  function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
      var ta = document.createElement('textarea');
      ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
      document.body.appendChild(ta); ta.focus(); ta.select();
      try { document.execCommand('copy'); resolve(); }
      catch (err) { reject(err); }
      finally { document.body.removeChild(ta); }
    });
  }
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('.copybtn');
    if (!btn) return;
    var token = btn.getAttribute('data-token');
    if (!token) return;
    // Base = this page's folder (strip "admin" or "admin.php" from the path).
    var base = location.origin + location.pathname.replace(/[^/]*$/, '');
    var url  = base + '?k=' + encodeURIComponent(token);
    copyText(url).then(function () {
      showToast('Invite link copied ✓');
      btn.classList.add('copied');
      setTimeout(function () { btn.classList.remove('copied'); }, 1200);
    }).catch(function () {
      window.prompt('Copy this invite link:', url);
    });
  });
})();
</script>
<?php endif; ?>

</body>
</html>
