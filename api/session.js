// GET /api/session?id=cs_... — tells the confirmation page whether the payment went through.
const { stripe } = require("./_lib");

module.exports = async (req, res) => {
  try {
    const id = String((req.query && req.query.id) || "");
    if (!/^cs_(test|live)_[A-Za-z0-9]+$/.test(id)) return res.status(400).json({ error: "Invalid session." });
    const s = await stripe("GET", `checkout/sessions/${id}`, { "expand[0]": "line_items" });
    res.setHeader("Cache-Control", "no-store");
    res.status(200).json({
      paid: s.payment_status === "paid",
      status: s.payment_status,
      ref: s.client_reference_id,
      email: s.customer_details && s.customer_details.email,
      name: s.metadata && s.metadata.customer_name,
      total: s.amount_total / 100,
      shipping: (s.shipping_cost && s.shipping_cost.amount_total / 100) || 0,
      currency: s.currency,
      testMode: !s.livemode,
      items: ((s.line_items && s.line_items.data) || []).map((l) => ({ name: l.description, qty: l.quantity, total: l.amount_total / 100 })),
    });
  } catch (e) {
    res.status(e.status || 500).json({ error: e.message });
  }
};
