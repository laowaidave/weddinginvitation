# Wedding Invitation & RSVP

A small, self-contained wedding invitation website with private guest links, an
RSVP form, and a password-protected dashboard to view responses. It runs on any
basic PHP web host — no database, no third-party services, no sign-ups.

Guests only ever see their own personal link. Their replies are saved on your
own hosting and shown to you in a simple dashboard.

## Features

- **Elegant single-page invitation** with your names, date, time, venue and photo.
- **Private, per-guest links** — each guest gets a unique token; unknown links
  show a polite "not found" page. The page is marked `noindex` for search engines.
- **RSVP form** — name, second guest / plus one, accept or decline, and a message.
- **Web-based setup wizard** at `/setup` — no code editing required.
- **Dashboard** at `/admin` — totals, every response, CSV export, and an invite
  link manager (rename labels, copy links to send, add more).
- **Responses stored on your host** in a folder that is blocked from the web.

## Requirements

- A web host that runs **PHP 7.4 or newer** (most shared hosts do, including
  Hostinger, and it is enabled by default).
- No database needed.

## Install

1. Upload **all** of these files and folders to your web space (for most hosts
   that means the `public_html` folder), keeping the structure intact:
   - `index.php`, `setup.php`, `admin.php`, `config.php`
   - the `data/` folder (contains an `.htaccess` that protects your responses)
   - the `uploads/` folder (your photo will be saved here)
   - `.htaccess`
2. In your browser, go to **`https://your-domain/setup`**
   (or `https://your-domain/setup.php` if friendly URLs aren't available).
3. Fill in the wizard: choose an admin password, enter the wedding details,
   upload a photo, and pick how many invite links to create. Save.
4. You'll land on the dashboard. Open **Invitation links**, copy each guest's
   link, and send it to them.

> **Tip:** run `/setup` as soon as you've uploaded the files, so you're the one
> who sets the password.

## Day-to-day use

- **View responses:** go to `/admin` and sign in.
- **Send links:** in the dashboard's *Invitation links* section, rename a label
  to the guest's name, click **Copy link**, and paste it into your message.
- **Add more links:** use *Add link(s)* at the bottom of that section.
- **Change any detail** (names, date, venue, photo, password): go to `/setup`
  (you'll be asked for your admin password).
- **Back up / export:** click **Export CSV** on the dashboard.

## Where your data lives

- Settings (details, hashed password, guest list): `data/settings.php`
- Responses: `data/rsvps.php`
- Photo: `uploads/`

The `data/` folder is blocked from the web two ways: an `.htaccess` deny rule
and a PHP guard on the files themselves (opening them directly shows nothing).
The admin password is stored only as a secure hash, never in plain text.

`data/settings.php`, `data/rsvps.php` and the uploaded photo are listed in
`.gitignore`, so your private details are never committed to version control.

## Customising

- **Wording, date format, venue** — all editable in `/setup`; write them exactly
  as you'd like them shown.
- **Colours & fonts** — edit the `:root` colour variables and the `<style>`
  block near the top of `index.php` (and `admin.php` for the dashboard).

## Troubleshooting

- **"Could not be saved" / "Could not save settings":** the `data` (or
  `uploads`) folder isn't writable. In your host's File Manager, set the folder
  permissions to `755`.
- **Friendly URLs `/setup` and `/admin` don't work:** your host may not have URL
  rewriting on. Use `/setup.php` and `/admin.php` instead.
- **Photo doesn't update after replacing it:** the link includes a version
  stamp, so a normal reload should show the new image; if not, clear your host's
  page cache.

## License

Noncommercial use only. You may use, copy, modify and share this for
noncommercial purposes; you may not sell it, host it for payment, or use it
for any revenue-generating purpose. Redistributed or modified copies must keep
the license, copyright notice, attribution, and a note of any changes. See the
[LICENSE](LICENSE) file for the full terms.
