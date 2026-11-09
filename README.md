# Elora — Timeless Fashion for All Generations

A shopping website for **Elora**, a UK ethnic-wear boutique (sarees, anarkali suits, dresses & kurtis, ready-made blouses).

## Features
- Homepage with banner slider, shop-by-category, New Arrivals and Customer Favourites
- Category pages with sorting, search and a wishlist
- Product pages with photo gallery, colour switching, size selection, size guide, quantity and "Buy now"
- Bag drawer with a free-delivery progress bar
- Checkout with name, phone, email and full UK address, with no customer login
- **WhatsApp ordering**: the order (items, sizes, colours, totals, address and an order reference) opens as a ready-to-send WhatsApp message to the store's number. The store then replies with payment details.
- **Admin panel** (`#admin`) to add, edit or delete products, categories and photos, and to change the WhatsApp number, delivery fees, announcement bar and homepage slides

## Files
| File | Purpose |
|---|---|
| `index.html` | The whole site: markup, styles and logic |
| `catalog.js` | Starter catalogue and default settings |
| `img/` | Logo and product photos |

## Running it
It's a static site with no build step. Open `index.html` through any web server, for example:

```bash
python3 -m http.server 8000
# then visit http://localhost:8000
```

It can also be hosted on GitHub Pages (Settings → Pages → deploy from the `main` branch).

### About the admin panel
The live version runs as a Claude artifact. There, the admin panel saves products, categories, photos and settings in the artifact's database. Hosted anywhere else (GitHub Pages, Netlify, etc.), the site shows the catalogue in `catalog.js` and the admin panel is not available. To change products or settings on a static host, edit `catalog.js`, including setting `whatsapp` to the store's number with country code, e.g. `447700900123`.
