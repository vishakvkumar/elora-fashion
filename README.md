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

## Running it
It's a static site with no build step. Open `index.html` through any web server, for example:

```bash
python3 -m http.server 8000
# then visit http://localhost:8000
```

It can also be hosted on GitHub Pages (Settings → Pages → deploy from the `main` branch).

### About the admin panel
The live version runs as a Claude artifact. There, the admin panel saves products, categories, photos and settings in the artifact's database. Hosted anywhere else (GitHub Pages, Netlify, etc.), the site shows the catalogue in `catalog.js` and the admin panel is not available. To change products or settings on a static host, edit `catalog.js`, including setting `whatsapp` to the store's number with country code, e.g. `447700900123`.
