// POST /api/checkout — creates a Stripe Checkout Session for the customer's bag.
// Prices and delivery are taken from catalog.js on the server, never from the browser.
const { loadCatalog, stripe, origin, clip } = require("./_lib");

module.exports = async (req, res) => {
  if (req.method !== "POST") return res.status(405).json({ error: "Method not allowed" });
  try {
    const { items, customer = {}, ref } = typeof req.body === "string" ? JSON.parse(req.body) : req.body || {};
    if (!Array.isArray(items) || !items.length || items.length > 30) return res.status(400).json({ error: "Your bag is empty." });

    const cat = loadCatalog();
    const byId = Object.fromEntries(cat.products.map((p) => [p.id, p]));
    const site = origin(req);
    const line_items = [];
    let subtotal = 0;
    const missing = [];

    for (const it of items) {
      const p = byId[it.id];
      const qty = Math.max(1, Math.min(20, parseInt(it.qty, 10) || 1));
      if (!p || p.hidden || p.inStock === false) { missing.push(it.name || it.id); continue; }
      const sizes = p.sizes || [];
      if (sizes.length && !sizes.includes(it.size)) return res.status(400).json({ error: `Please choose a valid size for ${p.name}.` });
      const colours = (p.colors || []).map((c) => c.name);
      const colour = colours.includes(it.color) ? it.color : colours[0] || "";
      const pence = Math.round(Number(p.price) * 100);
      subtotal += pence * qty;
      const img = (p.colors || []).find((c) => c.name === colour)?.image || (p.images || [])[0];
      line_items.push({
        quantity: qty,
        price_data: {
          currency: "gbp",
          unit_amount: pence,
          product_data: {
            name: p.name,
            description: [colour && `Colour: ${colour}`, sizes.length && `${p.sizeLabel || "Size"}: ${it.size}`].filter(Boolean).join(" · ") || undefined,
            images: img && /^img\//.test(img) ? [`${site}/${img}`] : undefined,
            metadata: { product_id: p.id, size: it.size || "", colour },
          },
        },
      });
    }
    if (missing.length) return res.status(409).json({ error: `Sorry, these items can't be bought online right now: ${missing.join(", ")}. Please remove them from your bag.` });

    const s = cat.settings || {};
    const freeOver = Math.round(Number(s.freeOver || 0) * 100);
    const fee = Math.round(Number(s.deliveryFee || 0) * 100);
    const shipping = freeOver && subtotal >= freeOver ? 0 : fee;

    const c = customer;
    const orderRef = /^ELR-[A-Z0-9]{4,10}$/.test(ref || "") ? ref : "ELR-" + Date.now().toString(36).toUpperCase().slice(-6);
    const address = {
      line1: clip(c.line1, 200), line2: clip(c.line2, 200), city: clip(c.city, 100),
      state: clip(c.county, 100), postal_code: clip(c.postcode, 20).toUpperCase(), country: "GB",
    };

    const session = await stripe("POST", "checkout/sessions", {
      mode: "payment",
      line_items,
      shipping_options: [{
        shipping_rate_data: {
          type: "fixed_amount",
          display_name: shipping ? "UK delivery" : "Free UK delivery",
          fixed_amount: { amount: shipping, currency: "gbp" },
          delivery_estimate: { minimum: { unit: "business_day", value: 3 }, maximum: { unit: "business_day", value: 7 } },
        },
      }],
      customer_email: /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(c.email || "") ? c.email.trim() : undefined,
      client_reference_id: orderRef,
      payment_intent_data: {
        description: `Elora order ${orderRef}`,
        shipping: { name: clip(c.name, 100), phone: clip(c.phone, 30), address },
        metadata: { order_ref: orderRef, phone: clip(c.phone, 30), notes: clip(c.notes, 450) },
      },
      metadata: {
        order_ref: orderRef,
        customer_name: clip(c.name, 100),
        phone: clip(c.phone, 30),
        address: [address.line1, address.line2, address.city, address.state, address.postal_code].filter(Boolean).join(", ").slice(0, 450),
        notes: clip(c.notes, 450),
      },
      success_url: `${site}/?session_id={CHECKOUT_SESSION_ID}#order-success`,
      cancel_url: `${site}/#checkout`,
    });

    res.status(200).json({ url: session.url, ref: orderRef });
  } catch (e) {
    console.error(e);
    res.status(e.status || 500).json({ error: e.message || "Something went wrong starting the payment." });
  }
};
