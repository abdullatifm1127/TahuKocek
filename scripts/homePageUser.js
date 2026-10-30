// ── State ──────────────────────────────────────────────
const cart = {};          // { id: { name, price, qty } }
const SERVICE_FEE = 1000;
let pickupMode = 'ambil'; // 'ambil' | 'antar'

/* Ekspor ke window agar modal pembayaran bisa akses */
window.cart         = cart;
window.pickupMode   = pickupMode;
window.SERVICE_FEE  = SERVICE_FEE;

// ── Helpers ────────────────────────────────────────────
function formatRupiah(num) {
  return 'Rp' + num.toLocaleString('id-ID');
}

function showToast(msg) {
  let toast = document.getElementById('toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'toast';
    toast.className = 'toast';
    document.body.appendChild(toast);
  }
  toast.textContent = msg;
  toast.classList.add('show');
  clearTimeout(toast._timer);
  toast._timer = setTimeout(() => toast.classList.remove('show'), 2200);
}

// ── Tambah item ke keranjang ───────────────────────────
function addToCart(id, name, price) {
  const key = String(id); // normalise ke string agar object key konsisten
  if (cart[key]) {
    cart[key].qty += 1;
  } else {
    cart[key] = { name, price, qty: 1 };
  }
  renderCart();
  showToast(`${name} ditambahkan ✓`);
}

// ── Ubah quantity ──────────────────────────────────────
function changeQty(id, delta) {
  const key = String(id);
  if (!cart[key]) return;
  cart[key].qty += delta;
  if (cart[key].qty <= 0) {
    delete cart[key];
  }
  renderCart();
}

// ── Render keranjang ───────────────────────────────────
function renderCart() {
  const list     = document.getElementById('orderList');
  const badge    = document.getElementById('cartCount');
  const subtotalEl = document.getElementById('subtotal');
  const totalEl    = document.getElementById('totalPrice');
  const btnBayar   = document.getElementById('btnBayar');

  const items = Object.entries(cart);

  // Badge jumlah item
  const totalQty = items.reduce((s, [, v]) => s + v.qty, 0);
  badge.textContent = totalQty;

  if (items.length === 0) {
    list.innerHTML = '<p class="empty-cart">Belum ada pesanan. Tambahkan menu!</p>';
    subtotalEl.textContent = formatRupiah(0);
    totalEl.textContent    = formatRupiah(SERVICE_FEE);
    btnBayar.disabled = true;
    return;
  }

  btnBayar.disabled = false;

  // Render baris item
  list.innerHTML = items.map(([id, item]) => `
    <div class="order-item">
      <div style="flex:1">
        <div class="order-item-name">${item.name}</div>
      </div>
      <div class="qty-controls">
        <button class="qty-btn" onclick="changeQty('${id}', -1)">−</button>
        <span class="qty-num">${item.qty}</span>
        <button class="qty-btn" onclick="changeQty('${id}', 1)">+</button>
      </div>
      <div class="order-item-price">${formatRupiah(item.price * item.qty)}</div>
    </div>
  `).join('');

  // Hitung total
  const subtotal = items.reduce((s, [, v]) => s + v.price * v.qty, 0);
  subtotalEl.textContent = formatRupiah(subtotal);
  totalEl.textContent    = formatRupiah(subtotal + SERVICE_FEE);
}

// ── Pilih cara ambil ───────────────────────────────────
function setPickup(mode) {
  pickupMode = mode;
  window.pickupMode = mode;   // sync ke window untuk modal
  document.getElementById('btnAmbil').classList.toggle('active', mode === 'ambil');
  document.getElementById('btnAntar').classList.toggle('active', mode === 'antar');
}

// ── Filter level pedas (listener ditangani oleh renderMenuGrid di HTML) ─────

// ── Checkout ───────────────────────────────────────────
function checkout() {
  const items = Object.entries(cart);
  if (items.length === 0) return;
  // Selalu gunakan modal pembayaran
  if (typeof window.checkoutOverride === 'function') {
    window.checkoutOverride();
  }
}

// ── Init ───────────────────────────────────────────────
renderCart();
