# Elora — Timeless Fashion for All Generations

A shopping website for **Elora**, a UK ethnic-wear boutique (sarees, anarkali suits, dresses & kurtis, ready-made blouses).

## Features
- Homepage with banner slider, shop-by-category, New Arrivals and Customer Favourites
- Category pages with sorting, search and a wishlist
- Product pages with photo gallery, colour switching, size selection, size guide, quantity and "Buy now"
- Bag drawer with a free-delivery progress bar
- Checkout with name, phone, email and full UK address, with no customer login
- **Card payments with Stripe Checkout**: cards, Apple Pay and Google Pay on Stripe's secure hosted page, then a confirmation page and an email receipt
- **Admin panel** (`#admin`) to add, edit or delete products, categories and photos, and to change the WhatsApp number, delivery fees, announcement bar and homepage slides

## Files
| File | Purpose |
|---|---|
| `index.html` | The whole site: markup, styles and logic |
| `catalog.js` | Starter catalogue and default settings |
| `img/` | Logo and product photos |
| `api/checkout.js` | Vercel function that creates the Stripe Checkout session, using prices from `catalog.js` |
| `api/session.js` | Vercel function the confirmation page uses to check the payment |
| `vercel.json` | Bundles `catalog.js` with the functions |

## Setting up Stripe payments (Vercel)
1. Create a Stripe account and stay in **Test mode**.
2. Copy the **secret key** (`sk_test_…`) from Stripe → Developers → API keys.
3. In Vercel → Project → Settings → **Environment Variables**, add `STRIPE_SECRET_KEY` with that value, then **redeploy**.
4. Test with card `4242 4242 4242 4242`, any future expiry date and any CVC. Payments appear in the Stripe Dashboard with the order reference, items, sizes, colours and delivery address.
5. To go live, finish Stripe's business verification and swap in the live key (`sk_live_…`).

Prices and delivery charges are always read on the server from `catalog.js`, so customers can't change them in the browser. Products added through the demo admin panel exist only in that browser, so they can't be paid for until they're added to `catalog.js`.

## Hosting on the client's own domain (PHP hosting) — recommended
This is the version to go live with. It needs an ordinary web hosting plan with:
- **PHP 8.0 or newer** with the **curl** extension (standard on almost every UK host)
- **Apache or LiteSpeed** (so the included `.htaccess` files protect private folders)
- **Free SSL/HTTPS** (Let's Encrypt), a file manager or FTP

### Install
1. Upload everything in this repo to the website folder (usually `public_html`).
2. In `api/`, copy `config.sample.php` to `config.php` and fill in:
   - `stripe_secret_key`: from Stripe → Developers → API keys (`sk_test_…` while testing)
   - `admin_password`: a long password for the admin panel
3. Make `data/` and `uploads/` writable by the website (permissions 755, or 775 if saving fails).
4. Turn on HTTPS for the domain.
5. Open `https://yourdomain/#admin` and log in. Products, categories, photos and settings now save on the server for every visitor.

### Test a payment
Use card `4242 4242 4242 4242`, any future date and any CVC. The order appears in Admin → Orders and in the Stripe Dashboard.

### Refunds
Admin → **Orders** lists the latest 50 paid orders from Stripe, with a **Refund** button for a full or part refund. Refunds can also be made in the Stripe Dashboard. The money returns to the customer's card in 5–10 working days; Stripe keeps its original fee.

### Go live
Finish Stripe's business verification, then replace the test key in `api/config.php` with the live key (`sk_live_…`).

### Keep the client's data safe
`data/store.json` (products, categories, settings) and `uploads/` (photos) are the live shop data. **Back them up, and never overwrite them when uploading a new version of the site.** `api/config.php` holds secrets: never share it or commit it.

| Server file | Purpose |
|---|---|
| `api/store.php` | Public catalogue |
| `api/admin.php` | Admin login, saving, photo uploads, orders and refunds |
| `api/checkout.php` | Creates the Stripe Checkout session, using server-side prices |
| `api/session.php` | Confirms a payment for the thank-you page |
| `api/config.sample.php` | Template for the private `config.php` |
| `data/store.json` | The shop's data |
| `uploads/` | Photos added through the admin panel |

## Preview on GitHub Pages / Vercel
Without PHP, the site runs as a demo: the admin panel (password `elora123`) saves only in the visitor's browser. On Vercel, `api/checkout.js` and `api/session.js` provide Stripe checkout if `STRIPE_SECRET_KEY` is set in the project's environment variables.
