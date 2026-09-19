<?php
/**
 * index.php — the public, invitation-only wedding page.
 *
 * A guest reaches this page through their personal link (…/?k=THEIR-TOKEN).
 * The token is checked against the guest list in the settings; an unknown or
 * missing token shows a polite "not found" message. When a guest submits the
 * RSVP form, their answer is saved to the private data file (data/rsvps.php)
 * and viewed later in the dashboard (admin.php). No email is sent.
 */

require_once __DIR__ . '/config.php';

// If the site has not been set up yet, send the owner to the setup wizard.
if (!is_configured()) {
    header('Location: setup.php');
    exit;
}

$S       = load_settings();
$invites = $S['invites'] ?? [];

// ---------------------------------------------------------------------------
// Work out which invitation this is from the token in the URL (or the form).
// ---------------------------------------------------------------------------
$token      = $_POST['k'] ?? $_GET['k'] ?? '';
$token      = is_string($token) ? trim($token) : '';
$validToken = ($token !== '' && array_key_exists($token, $invites));
$guestLabel = $validToken ? $invites[$token] : '';

// ---------------------------------------------------------------------------
// Gate: without a valid token, show a friendly "not found" page and stop.
// ---------------------------------------------------------------------------
if (!$validToken) {
    http_response_code(404);
    ?><!DOCTYPE html>
    <html lang="en"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Invitation not found</title>
    <style>
      body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
           background:#f7f3ea;color:#2b211a;font-family:Georgia,'Times New Roman',serif;text-align:center;padding:2rem}
      .box{max-width:30rem}
      h1{font-size:1.6rem;font-weight:normal;margin:0 0 .75rem}
      p{line-height:1.6;color:#6b5d50}
      .rule{width:60px;height:3px;margin:1.25rem auto;
            background:repeating-linear-gradient(90deg,#c1272d 0 6px,#1a1410 6px 12px)}
    </style></head>
    <body><div class="box">
      <h1>This invitation link isn't valid</h1>
      <div class="rule"></div>
      <p>Please use the personal link that was sent to you. If you think this is a
         mistake, do get in touch and we'll sort it out.</p>
    </div></body></html><?php
    exit;
}

// ---------------------------------------------------------------------------
// Handle the RSVP form: validate, then save the response to the data file.
// ---------------------------------------------------------------------------
$submitted = false;
$sendError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['website'])) {          // honeypot field: bots fill it, people don't
        $submitted = true;                    // silently accept and drop spam
    } else {
        $invitee   = trim((string)($_POST['invitee']   ?? ''));
        $plusOne   = trim((string)($_POST['plusone']   ?? ''));
        $attending = trim((string)($_POST['attending'] ?? ''));
        $message   = trim((string)($_POST['message']   ?? ''));

        if ($invitee === '' || ($attending !== 'Accept' && $attending !== 'Decline')) {
            $sendError = 'Please give your name and let us know whether you can attend.';
        } else {
            $ok = save_rsvp([
                'time'      => date('c'),
                'token'     => $token,
                'label'     => $guestLabel,
                'invitee'   => $invitee,
                'plusone'   => $plusOne,
                'attending' => $attending,           // 'Accept' | 'Decline'
                'message'   => $message,
                'ip'        => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
            if ($ok) {
                $submitted = true;
            } else {
                $sendError = 'Sorry — your response could not be saved right now. '
                           . 'Please try again shortly.';
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Pull the display values out of the settings (all already saved as plain text;
// they are escaped with e() at the point of output below).
// ---------------------------------------------------------------------------
$name1     = (string)($S['name1'] ?? '');
$name2     = (string)($S['name2'] ?? '');
$eyebrow   = (string)($S['eyebrow'] ?? '');
$subtitle  = (string)($S['subtitle'] ?? '');
$dateText  = (string)($S['date_text'] ?? '');
$timeText  = (string)($S['time_text'] ?? '');
$timeNote  = (string)($S['time_note'] ?? '');
$lengthTxt = (string)($S['length_text'] ?? '');
$venueName = (string)($S['venue_name'] ?? '');
$venueNote = (string)($S['venue_note'] ?? '');
$footer    = (string)($S['footer_line'] ?? '');
$photoRel  = (string)($S['photo'] ?? '');
$hasPhoto  = ($photoRel !== '' && is_file(__DIR__ . '/' . $photoRel));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($name1 . ' & ' . $name2) ?><?= $dateText ? ' · ' . e($dateText) : '' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=EB+Garamond:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root{
    --ink:#241a12;          /* near-black brown */
    --cream:#f7f1e4;        /* warm paper */
    --cream-2:#efe6d3;
    --red:#b21f27;          /* woven red */
    --gold:#c69a3f;         /* woven gold */
    --green:#166b45;        /* deep green */
    --muted:#7a6a58;
  }
  *{box-sizing:border-box}
  html{scroll-behavior:smooth}
  body{
    margin:0;color:var(--ink);
    background:var(--cream);
    font-family:'EB Garamond',Georgia,serif;
    font-size:18px;line-height:1.65;
  }

  /* Woven decorative band (black base, red diamond lattice, gold edges) */
  .puan-band{
    height:26px;
    background-color:var(--ink);
    background-image:
      repeating-linear-gradient(45deg,  var(--red) 0 3px, transparent 3px 16px),
      repeating-linear-gradient(-45deg, var(--red) 0 3px, transparent 3px 16px),
      repeating-linear-gradient(45deg,  rgba(247,241,228,.7) 0 1px, transparent 1px 16px),
      repeating-linear-gradient(-45deg, rgba(247,241,228,.7) 0 1px, transparent 1px 16px);
    background-size:16px 16px;
    box-shadow:inset 0 4px 0 var(--gold), inset 0 -4px 0 var(--gold);
  }
  .puan-band.slim{height:16px;box-shadow:inset 0 2px 0 var(--gold),inset 0 -2px 0 var(--gold)}

  .wrap{max-width:640px;margin:0 auto;padding:0 22px}

  /* Hero */
  .hero{text-align:center;padding:54px 0 30px}
  .eyebrow{
    font-family:'EB Garamond',serif;letter-spacing:.42em;text-transform:uppercase;
    font-size:.72rem;color:var(--red);margin:0 0 18px;font-weight:500;
  }
  .names{
    font-family:'Cormorant Garamond',serif;font-weight:500;
    font-size:clamp(2.6rem,9vw,4.4rem);line-height:1.02;margin:0;color:var(--ink);
  }
  .amp{display:block;color:var(--gold);font-style:italic;font-size:.62em;margin:.12em 0;font-weight:400}
  .subtitle{margin:18px 0 0;color:var(--muted);font-style:italic;font-size:1.12rem}

  .divider{display:flex;align-items:center;gap:14px;justify-content:center;margin:34px auto;max-width:340px}
  .divider::before,.divider::after{content:"";flex:1;height:1px;background:linear-gradient(90deg,transparent,var(--gold),transparent)}
  .diamond{width:12px;height:12px;background:var(--red);transform:rotate(45deg);box-shadow:0 0 0 3px var(--cream),0 0 0 4px var(--gold)}

  /* Photo */
  .photo-frame{
    margin:6px auto 0;max-width:560px;padding:10px;background:var(--ink);
    box-shadow:0 12px 34px rgba(36,26,18,.28);
  }
  .photo-frame .inner{border:2px solid var(--gold);overflow:hidden}
  .photo-frame img{display:block;width:100%;height:clamp(300px,58vw,460px);object-fit:cover;object-position:center 28%}
  .photo-cap{text-align:center;color:var(--muted);font-style:italic;font-size:.95rem;margin:14px 0 0}

  /* Details card */
  .card{
    background:var(--cream-2);border:1px solid rgba(198,154,63,.5);
    padding:34px 26px;margin:38px 0;text-align:center;position:relative;
  }
  .card h2{
    font-family:'Cormorant Garamond',serif;font-weight:500;font-size:1.9rem;margin:0 0 22px;color:var(--red);
  }
  .detail{margin:0 0 20px}
  .detail:last-child{margin-bottom:0}
  .detail .label{font-size:.72rem;letter-spacing:.28em;text-transform:uppercase;color:var(--gold);display:block;margin-bottom:4px}
  .detail .value{font-size:1.32rem;font-family:'Cormorant Garamond',serif}
  .detail .value small{display:block;font-family:'EB Garamond',serif;font-size:1rem;color:var(--muted);font-style:italic;margin-top:2px}

  /* RSVP */
  .rsvp{padding:6px 0 20px}
  .rsvp h2{font-family:'Cormorant Garamond',serif;font-weight:500;font-size:2rem;text-align:center;margin:0 0 6px;color:var(--ink)}
  .rsvp .lede{text-align:center;color:var(--muted);margin:0 0 26px;font-style:italic}
  .field{margin:0 0 20px}
  label.flabel{display:block;font-size:.78rem;letter-spacing:.14em;text-transform:uppercase;color:var(--ink);margin-bottom:7px;font-weight:500}
  .req{color:var(--red)}
  input[type=text],textarea{
    width:100%;padding:12px 14px;font:inherit;color:var(--ink);
    background:#fffaf0;border:1px solid rgba(122,106,88,.45);border-radius:0;
  }
  input[type=text]:focus,textarea:focus{outline:none;border-color:var(--red);box-shadow:0 0 0 3px rgba(178,31,39,.12)}
  textarea{min-height:96px;resize:vertical}
  .hint{font-size:.85rem;color:var(--muted);font-style:italic;margin:5px 0 0}

  .choice{display:flex;gap:12px;flex-wrap:wrap}
  .choice label{
    flex:1 1 160px;display:flex;align-items:center;gap:10px;cursor:pointer;
    padding:13px 16px;background:#fffaf0;border:1px solid rgba(122,106,88,.45);
    transition:border-color .15s,background .15s;
  }
  .choice input{accent-color:var(--red);width:17px;height:17px}
  .choice label:has(input:checked){border-color:var(--red);background:#fff;box-shadow:0 0 0 2px rgba(178,31,39,.15)}
  .choice .yes{font-weight:500}

  .honey{position:absolute;left:-9999px;opacity:0;height:0;overflow:hidden}

  .btn{
    display:block;width:100%;margin-top:6px;padding:15px;cursor:pointer;
    font-family:'Cormorant Garamond',serif;font-size:1.25rem;letter-spacing:.04em;
    color:var(--cream);background:var(--red);border:none;
    transition:background .15s,transform .05s;
  }
  .btn:hover{background:#8f1920}
  .btn:active{transform:translateY(1px)}

  .alert{padding:14px 16px;margin:0 0 22px;border-left:4px solid var(--red);background:#fdeceb;color:#7a1a1f;font-size:.98rem}

  /* Thank-you */
  .thanks{text-align:center;padding:20px 0}
  .thanks .mark{width:58px;height:58px;margin:0 auto 18px;border:2px solid var(--gold);
    display:flex;align-items:center;justify-content:center;color:var(--red);font-size:1.7rem;transform:rotate(45deg)}
  .thanks .mark span{transform:rotate(-45deg)}
  .thanks h2{font-family:'Cormorant Garamond',serif;font-weight:500;font-size:2.1rem;margin:0 0 10px;color:var(--red)}
  .thanks p{color:var(--muted);margin:0 auto;max-width:26rem}

  footer{text-align:center;color:var(--muted);font-size:.9rem;padding:6px 0 40px}
  footer .kaltha{font-family:'Cormorant Garamond',serif;font-style:italic;font-size:1.15rem;color:var(--ink);margin:0 0 6px}

  @media (max-width:420px){ body{font-size:17px} .card{padding:28px 18px} }
</style>
</head>
<body>

<div class="puan-band"></div>

<div class="wrap">

  <header class="hero">
    <?php if ($eyebrow): ?><p class="eyebrow"><?= e($eyebrow) ?></p><?php endif; ?>
    <h1 class="names"><?= e($name1) ?><span class="amp">&amp;</span><?= e($name2) ?></h1>
    <?php if ($subtitle): ?><p class="subtitle"><?= e($subtitle) ?></p><?php endif; ?>
  </header>

  <div class="divider"><span class="diamond"></span></div>

  <?php if ($hasPhoto): ?>
  <!-- Photo -->
  <figure class="photo-frame" style="margin-bottom:0">
    <div class="inner">
      <img src="<?= e($photoRel) ?>?v=<?= (int)@filemtime(__DIR__ . '/' . $photoRel) ?>" alt="<?= e($name1 . ' and ' . $name2) ?>">
    </div>
  </figure>
  <p class="photo-cap"><?= e($name1) ?> &amp; <?= e($name2) ?></p>
  <?php endif; ?>

  <!-- Details -->
  <section class="card">
    <h2>The Celebration</h2>
    <div class="detail">
      <span class="label">On the day of</span>
      <span class="value"><?= e($dateText) ?></span>
    </div>
    <?php if ($timeText || $timeNote): ?>
    <div class="detail">
      <span class="label">At</span>
      <span class="value"><?= e($timeText) ?>
        <?php if ($timeNote): ?><small><?= e($timeNote) ?></small><?php endif; ?>
      </span>
    </div>
    <?php endif; ?>
    <?php if ($lengthTxt): ?>
    <div class="detail">
      <span class="label">Expected length</span>
      <span class="value"><?= e($lengthTxt) ?></span>
    </div>
    <?php endif; ?>
    <div class="detail">
      <span class="label">Venue</span>
      <span class="value"><?= e($venueName) ?>
        <?php if ($venueNote): ?><small><?= e($venueNote) ?></small><?php endif; ?>
      </span>
    </div>
  </section>

  <div class="puan-band slim" style="margin:0 0 34px"></div>

  <!-- RSVP -->
  <section class="rsvp" id="rsvp">
  <?php if ($submitted): ?>
    <div class="thanks">
      <div class="mark"><span>&#10003;</span></div>
      <h2>Thank you</h2>
      <p>Your response has been received. We're so grateful you took the time to reply &mdash; and we can't wait to celebrate together.</p>
    </div>
  <?php else: ?>
    <h2>Kindly Respond</h2>
    <p class="lede">We would be honoured to have you with us. Please reply below.</p>

    <?php if ($sendError): ?>
      <div class="alert"><?= e($sendError) ?></div>
    <?php endif; ?>

    <form method="post" action="?k=<?= e($token) ?>#rsvp" novalidate>
      <input type="hidden" name="k" value="<?= e($token) ?>">

      <!-- honeypot: real people leave this empty -->
      <div class="honey" aria-hidden="true">
        <label>Leave this field empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </div>

      <div class="field">
        <label class="flabel" for="invitee">Full name of invitee <span class="req">*</span></label>
        <input type="text" id="invitee" name="invitee" required
               value="<?= e($_POST['invitee'] ?? '') ?>" autocomplete="name">
      </div>

      <div class="field">
        <label class="flabel" for="plusone">Full name of second invitee / plus one</label>
        <input type="text" id="plusone" name="plusone" value="<?= e($_POST['plusone'] ?? '') ?>">
        <p class="hint">Leave blank if you're coming on your own.</p>
      </div>

      <div class="field">
        <label class="flabel">Will you be attending? <span class="req">*</span></label>
        <div class="choice">
          <label class="yes">
            <input type="radio" name="attending" value="Accept" required
              <?= (($_POST['attending'] ?? '') === 'Accept') ? 'checked' : '' ?>>
            Joyfully accepts
          </label>
          <label>
            <input type="radio" name="attending" value="Decline"
              <?= (($_POST['attending'] ?? '') === 'Decline') ? 'checked' : '' ?>>
            Regretfully declines
          </label>
        </div>
      </div>

      <div class="field">
        <label class="flabel" for="message">A message for the couple</label>
        <textarea id="message" name="message" placeholder="Anything you'd like to say — and if you can't make it, we'd love to know why."><?= e($_POST['message'] ?? '') ?></textarea>
      </div>

      <button type="submit" class="btn">Send our response</button>
    </form>
  <?php endif; ?>
  </section>

  <footer>
    <?php if ($footer): ?><p class="kaltha"><?= e($footer) ?></p><?php endif; ?>
    <p><?= e($name1) ?> &amp; <?= e($name2) ?><?= $dateText ? ' &middot; ' . e($dateText) : '' ?></p>
  </footer>

</div>

<div class="puan-band"></div>

</body>
</html>
