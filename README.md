# Bobo Baker's — Setup & Handover Guide

Lahore custom cake bakery. Static frontend + PHP/MySQL backend + admin panel.

---

## Quick start (XAMPP / local)

1. Start **Apache + MySQL** in XAMPP.
2. Open phpMyAdmin (`http://localhost/phpmyadmin`) and import **`database.sql`**.
   This creates the database, all tables, 79 cakes, 7 categories and 6 add-ons.
3. Copy this whole folder into `htdocs/bobo-bakers/`.
4. Open **`config/db.php`** and set your database credentials.
   - Local XAMPP: user `root`, password blank (already the default).
   - Hostinger / cPanel: take the values from your hosting panel.
5. Make the rate-limit folder writable:
   ```
   chmod 755 storage storage/ratelimit
   ```
6. Test the API: `http://localhost/bobo-bakers/api/get_products.php`
   You should get JSON with `"count":77`.
7. Open the site: `http://localhost/bobo-bakers/`

### "Connection Issue" when placing an order

If the order popup says it cannot reach the server, the database is almost
never the problem — the browser could not reach the PHP file at all. Check, in
this order:

1. **Is the URL `http://localhost/...`?** If the address bar shows
   `file:///C:/...` the page was opened by double-clicking it. PHP does not run
   that way. Put the folder in `htdocs` and open it through localhost.
2. **Is Apache running** in the XAMPP control panel?
3. **Does `http://localhost/<your-folder>/api/get_products.php` return JSON?**
   If it downloads a file instead, PHP is not enabled. If it 404s, the `api`
   folder did not upload.
4. **Only then** check `config/db.php`.

`API_BASE` is now a relative path (`'api'`), so the folder can be named
anything and sit anywhere — it no longer has to be called `bobo-bakers`.

### Deploying to the domain root

Nothing to change. `API_BASE` is relative, so it works at the domain root and
in a subfolder without being edited. `.htaccess` already sets `index.html` as
the directory index, so `https://www.bobobakers.com/` loads the site directly.

---

## First-day checklist for the client

| # | Task | Where |
|---|------|-------|
| 1 | **Change the admin password** (see below) | `admins` table |
| 2 | Set the real notification email | `api/place_order.php`, `api/place_lead.php` |
| 3 | Turn on free SSL (Let's Encrypt) | Hosting panel |
| 4 | Add the GA4 measurement ID | `index.html` `<head>`, uncomment |
| 5 | Add the Meta Pixel ID | `index.html` `<head>`, uncomment |
| 6 | Submit `sitemap.xml` | Google Search Console |
| 7 | Claim the Google Business Profile | google.com/business |

### Changing the admin password

Default login is `admin` / `admin123` — **change it before going live.**

Generate a new hash:
```
php -r "echo password_hash('YourNewPassword', PASSWORD_DEFAULT);"
```
Then in phpMyAdmin, open the `admins` table and paste it into `password_hash`.

Admin panel: `https://your-domain/admin/login.php`

---

## Folder structure

```
/
├── index.html               Main site (BOBOBAKERS.html is an identical copy)
├── 404.html                 Custom not-found page
├── og-image.jpg             1200×630 social share banner
├── *.webp                   All cake photos (WebP — 83% smaller than the PNGs)
├── *.png                    Original PNGs, kept as a backup
├── sitemap.xml, robots.txt, site.webmanifest
│
├── config/
│   ├── db.php               DATABASE CREDENTIALS GO HERE
│   ├── security.php         Rate limiting, honeypot, spam filter
│   └── .htaccess            Blocks this folder from the web
│
├── api/
│   ├── get_products.php     All active cakes (optional ?category=wedding)
│   ├── get_toppings.php     All add-ons
│   ├── place_order.php      Catalogue checkout
│   ├── place_lead.php       Custom-cake enquiries + kids designer
│   ├── get_orders.php       Admin only
│   ├── update_order_status.php   Admin only
│   └── update_lead_status.php    Admin only
│
├── admin/
│   ├── login.php            Login (brute-force protected)
│   ├── dashboard.php        Orders + enquiries, two tabs
│   ├── auth.php             Session, CSRF, timeout helper
│   └── logout.php
│
├── storage/ratelimit/       Rate-limit counters (must be writable)
└── database.sql             Full schema + all data
```

---

## Security notes — please read

These were fixed during the final review. Do not undo them.

**1. Prices come from the database, never the browser.**
`place_order.php` used to trust a `base_price` value sent by the page. Anyone
could open dev tools and order a Rs. 3,500 cake for Rs. 1. The endpoint now
looks the price up itself and ignores whatever the browser sends.

**2. Rate limits are on.**
5 orders and 4 enquiries per 10 minutes per IP; 6 failed logins per 15 minutes.
Counters live in `storage/ratelimit/`. If a real customer ever gets blocked,
they see a message pointing them to WhatsApp, so no sale is lost.

**3. Admin sessions are hardened.**
HttpOnly + SameSite cookies, Secure once SSL is on, session ID regenerated at
login (blocks session fixation), 2-hour idle timeout, CSRF tokens on every
status change.

**4. Honeypot fields.**
Both forms carry a hidden field bots fill in and humans never see. Submissions
that trip it get a success response but are silently discarded.

**5. `database.sql` starts with `SET NAMES utf8mb4`.**
Without it MySQL falls back to latin1 on import and every emoji is corrupted.

---

## SEO notes

**Fake review markup was removed.** The page previously declared
`"aggregateRating": {"ratingValue":"5","reviewCount":"350"}` in its structured
data. Those 350 reviews did not exist anywhere on the site. Google's policy
requires review markup to reflect real, visible, verifiable reviews, and
self-serving ratings are a common cause of manual actions. Once the Google
Business Profile has genuine reviews, connect those instead — do not re-add
a hardcoded number.

**All 77 cake cards now carry complete `Product` + `Offer` markup**
(price, currency, availability). Previously 76 of them declared themselves as
products with no offer data, which Search Console reports as errors.

**Testimonial locations were corrected.** Three testimonials listed Karachi
neighbourhoods (Clifton, Gulshan, North Nazimabad) as Lahore addresses. They
now use real Lahore areas. If these testimonials are not from real customers,
replace them with real ones or remove the section — a Lahore customer spots a
wrong neighbourhood immediately.

**Social sharing** now uses a proper 1200×630 `og-image.jpg`, and `og:url`
points at the website (it used to point at a Facebook share link).

---

## About the urgency messaging

The site previously showed pop-up toasts reading things like *"Ayesha from
DHA Phase 5 just ordered a Birthday Cake — 3 minutes ago"*, plus a counter
claiming a number of cakes ordered today that simply counted upwards on a timer.
None of it was real.

Fabricated transaction notifications are deceptive advertising, and both Meta
and Google Ads treat them as a policy violation that can get an ad account
restricted — so this is a commercial risk, not only an ethical one.

The replacement keeps the urgency but every claim is true:

- The **cutoff countdown** is a real clock to the real 6 PM baking deadline
  (change `CUTOFF_HOUR` in the JS if the bakery's deadline differs).
- The **design counter** reads the actual number of cards in the gallery.
- The **rotating spotlight** shows real cakes from the real catalogue, and
  clicking it scrolls to that cake.

If genuine "someone just ordered" notifications are wanted later, add a small
endpoint that reads the most recent rows from the `orders` table. Then the
claims are real and there is no policy problem.

---

## Kids Zone

A new section (`#kids`) lets a child build a cake live: theme, colour, size,
candles and the name to pipe on top. The SVG redraws as they choose. "Send my
design to the bakers" opens WhatsApp with the finished spec already written out,
and also saves a record to the `leads` table so nothing is lost if WhatsApp is
missed.

The gallery gained a **Kids Favourites** filter (27 cakes tagged `data-kids="1"`)
and the missing **Baby Shower** filter.

To add or remove a cake from Kids Favourites, add or remove `data-kids="1"` on
its `<article class="cake-card">` tag.

---

## Scrolling performance

The page was dropping almost every frame while scrolling. Measured causes, in
order of how much they cost:

| Cause | Fix |
|---|---|
| **241 elements using `backdrop-filter`** — the badge, wishlist and quick-view button on all 77 cards | Removed; they sit on an opaque photo where a backdrop blur does nothing visible |
| **The fixed navbar's `blur(24px) saturate(200%)`** — re-blurred everything scrolling underneath it, every frame | Reduced to `blur(10px)`, and turned off entirely once the bar becomes opaque on scroll |
| 77 cards all laid out and painted at once | `content-visibility: auto` so off-screen cards are skipped |
| Sticky Kids Zone panel with a backdrop blur | Blur removed (sticky + backdrop-filter is the worst combination for this) |

Average frame time went from **78 ms to 57 ms** in testing, and the worst-case
stalls roughly halved. Note that a headless test machine has no GPU, so real
hardware will be considerably better than these numbers — but the relative
improvement holds.

**Loading** was also slower than it needed to be:

- Google Fonts was requested **twice**, pulling roughly 14 font files including
  weights the CSS never uses. Now one request, 8 files.
- SweetAlert2 was blocking page parsing. Now deferred — it is only needed when
  a dialog opens.
- The hero photo was being fetched at 1920px and q80. Now 1400px at q62.

The largest remaining load cost is Font Awesome and the two Unsplash photos,
both external. Replacing the stock photos with the client's own (see below)
removes two cross-origin requests as well as being more honest.

## Images

All photos were converted to WebP: **7.07 MB → 1.18 MB (83% smaller)**.
The original PNGs are still in the folder as a fallback and can be deleted
once you're happy.

⚠️ **The source photos are only 200×200 pixels** but display at 260px, so they
look soft on retina screens. Re-export the originals at **520×520** and convert
again for a real quality jump. This is the single biggest remaining visual win.

---

## Categories were rebuilt (important)

Every cake was checked against its own photograph. **33 of the 77 were filed
under a category the picture contradicted**, and 10 had names that described a
different cake entirely.

The worst cases:

| Old name / category | What the photo actually shows |
|---|---|
| "Pink Surprise Reveal" — *Gender Reveal* | A dholki with **"NIMRA MEHNDI"** piped on the board |
| "He or She Reveal Cake" — *Gender Reveal* | **"ASMA KI MAIYOON"** |
| "Blue Boy Reveal Cake" — *Gender Reveal* | **"SHAHZINA MAYOON"** |
| "Oh Baby! Reveal Cake" — *Gender Reveal* | A pink draped dholki |
| "White Pearl Wedding Tier" — *Wedding* | A **Minion** cake |
| "Black Forest Supreme" | An **iPhone / Apple** cake |
| "Lemon Zest Cupcakes" | A tray of **donuts** |
| "Party Confetti Cupcakes" | A platter of **gulab jamun** |

Not one photo was a gender reveal cake, so that category was removed. Two new
ones replaced it:

- **Mehndi & Mayoon** — the four dholki cakes above, plus Sangeet Dhol, Golden
  Bride Mayoon and the shadi drape cake. For a Lahore bakery this is a category
  customers actively search for, so it earns its own tab.
- **Celebration & Classic** — plain chocolate, vanilla, fudge and buttercream
  cakes were previously split between "Boys" and "Girls" at random, which is
  why a plain fudge cake was labelled a girls' cake. They now sit together.

Final counts: Wedding 9, Mehndi 7, Girls 15, Boys 8, Celebration 20, Eid 3,
Baby 6, Cupcakes & Desserts 9. The site and the database agree on all 77.

## Claims on the page that only the client can verify

These are visible on the site and I had no way to check them. Please confirm
each one is true, or change it:

| Claim | Where |
|-------|-------|
| **500+ Cakes Delivered** | stats bar under the hero |
| **3yr+ Experience** | stats bar |
| **5★ Customer Rating** | stats bar + floating badge on the About photo |
| **The six testimonials** | Reviews section |

The **5★ Customer Rating** is the one to look at first. It is the same claim
that was removed from the structured data, just written in visible text
instead. A rating with no reviews behind it anywhere is exactly what Google
looks for, and a customer who checks the Google Business Profile and finds
nothing will notice too. If there is no real review source yet, replace it with
something you can stand behind — "Fresh, made to order" or "Delivered citywide".

The **testimonials** have no verifiable source. Three of them originally listed
Karachi neighbourhoods as Lahore addresses, which is what made them look
invented. I corrected the areas so nothing on the page is obviously wrong, but
correcting an address does not make a testimonial real. Replace them with real
customer messages (WhatsApp screenshots work well and are more convincing), or
remove the section until you have some.

## Stock photos

Two images are stock photography from Unsplash, not Bobo Baker's own work:

- the **hero background** (the cake behind "Every Bite Tells a Sweet Story")
- the **About section photo** of a chef decorating a cake

The About photo's alt text used to describe it as "Bobo Baker's pastry chef",
which it is not; I changed the wording. Unsplash's licence allows commercial
use, so there is no legal problem — but a bakery with 90+ photos of its own
cakes should be showing those instead. Swapping in two real photos would make
the page noticeably more convincing.

## Known items still open

- **Golden Anniversary Cake** and **Happy Anniversary Cake** (ids 3 and 4) are
  set `is_active = 0` because their source images (`36.png`, `60.png`) were never
  in the package. Add the photos, run
  `UPDATE products SET is_active = 1 WHERE id IN (3,4);` and add the two cards
  back to the gallery.
- Testimonials need real, attributable customer quotes.
- Email notifications use PHP `mail()`. On shared hosting this usually works,
  but deliverability is much better with SMTP. Set up SPF and DKIM records for
  the domain, and make sure `orders@bobobakers.com` actually exists.
- Font Awesome still loads its full icon set (~70 KB) for the 52 icon classes
  the page uses. It no longer blocks the first paint, but replacing it with
  inline SVGs would remove the request entirely and save around 60 KB.
- The two Anniversary cakes and the two stock photos above.
