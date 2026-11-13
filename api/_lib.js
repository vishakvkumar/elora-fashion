// Shared helpers for the Stripe API functions (files starting with _ are not exposed as routes).
const fs = require("fs");
const path = require("path");

let cached = null;
function loadCatalog() {
  if (cached) return cached;
  const src = fs.readFileSync(path.join(process.cwd(), "catalog.js"), "utf8");
  const win = {};
  new Function("window", src)(win);
  cached = win.ELORA_SEED;
  return cached;
}

async function stripe(method, endpoint, params) {
  const key = process.env.STRIPE_SECRET_KEY;
  if (!key) {
    const e = new Error("Payments aren't set up yet. Add STRIPE_SECRET_KEY in Vercel → Settings → Environment Variables, then redeploy.");
    e.status = 503;
    throw e;
  }
  const body = params ? encode(params) : undefined;
  const url = "https://api.stripe.com/v1/" + endpoint + (method === "GET" && body ? "?" + body : "");
  const res = await fetch(url, {
    method,
    headers: { Authorization: "Bearer " + key, "Content-Type": "application/x-www-form-urlencoded" },
    body: method === "GET" ? undefined : body,
  });
  const json = await res.json();
  if (!res.ok) {
    const e = new Error((json.error && json.error.message) || "Stripe request failed");
    e.status = 502;
    throw e;
  }
  return json;
}

// Stripe expects form encoding with bracket notation: a[b][0][c]=d
function encode(obj, prefix, out = []) {
  for (const [k, v] of Object.entries(obj)) {
    if (v === undefined || v === null || v === "") continue;
    const key = prefix ? `${prefix}[${k}]` : k;
    if (typeof v === "object") encode(v, key, out);
    else out.push(encodeURIComponent(key) + "=" + encodeURIComponent(String(v)));
  }
  return out.join("&");
}

function origin(req) {
  const proto = req.headers["x-forwarded-proto"] || "https";
  const host = req.headers["x-forwarded-host"] || req.headers.host;
  return `${proto}://${host}`;
}

const clip = (s, n) => String(s || "").trim().slice(0, n);

module.exports = { loadCatalog, stripe, encode, origin, clip };
