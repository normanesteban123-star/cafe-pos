// ============================================
// CAFE POS - MAIN JAVASCRIPT
// ============================================

const API = 'api.php';
const CURRENCY = '₱';
const ENABLE_VARIANTS = false;

// ── STATE ────────────────────────────────────
let cart = [];
let products = [];
let popularProducts = [];
let categories = [];
let currentCategory = 0;
let currentUser = null;
let pendingOrderPayload = null;
const STAFF_ALLOWED_PAGES = new Set(['pos']);
const ADMIN_ONLY_PAGES = new Set(['accounts', 'categories', 'ingredients', 'inventory', 'orders', 'dashboard', 'reports', 'audit']);
const PAYMENT_METHOD_LABELS = {
  cash: 'Cash',
  card: 'Credit/Debit Card',
  gcash: 'GCash',
  maya: 'Maya',
  bank_transfer: 'Bank Transfer',
  qrph: 'QRPh'
};
const ORDER_TYPE_LABELS = {
  dine_in: 'Dine In',
  take_out: 'Take Out'
};

// ── INIT ─────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
  console.log('Page loaded, initializing...');
  // Ensure database schema has required columns
  if (typeof currentUser !== 'undefined' && currentUser && currentUser.role === 'admin') {
    api('ensure_schema', null, 'GET', true).catch(() => {});
  }
  initSidebar();
  initClock();
  initProductImagePreview();
  initCheckoutPaymentMethod();
  initBaseSizeHint();
  initCheckoutEnter();
  console.log('Calling initAuth...');
  await initAuth();
  console.log('Auth done, currentUser:', currentUser);
  if (currentUser) {
    console.log('Navigating to POS...');
    navigate('pos');
  } else {
    console.log('No user, showing login');
  }
});

function initCheckoutEnter() {
  const modal = document.getElementById('checkout-modal');
  if (!modal) return;
  modal.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' || event.shiftKey) return;
    const target = event.target;
    if (target && target.tagName === 'TEXTAREA') return;
    if (!modal.classList.contains('open')) return;
    event.preventDefault();
    placeOrder();
  });
}

function initBaseSizeHint() {
  const sizeSelect = document.getElementById('product-size');
  if (!sizeSelect) return;
  sizeSelect.addEventListener('change', () => {
    updateBaseSizeHint(sizeSelect.value);
    if (_ingredientCatalog.length) renderProductIngredientInputs();
  });
  updateBaseSizeHint(sizeSelect.value);
}

// ── NAVIGATION ───────────────────────────────
function navigate(page) {
  if (!canAccessPage(page)) {
    toast('Staff access is limited to POS only', 'error');
    page = 'pos';
  }
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  const el = document.getElementById('page-' + page);
  if (el) el.classList.add('active');
  const nav = document.querySelector(`[data-page="${page}"]`);
  if (nav) nav.classList.add('active');
  const loaders = { pos: loadPOS, inventory: loadInventory, accounts: loadAccountsPage, categories: loadCategoryManager, ingredients: loadIngredients, orders: loadOrders, dashboard: loadDashboard, reports: initReportsPage, audit: loadAuditLogs };
  if (loaders[page]) loaders[page]();
}

function initSidebar() {
  document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', () => {
      const page = item.dataset.page;
      if (!canAccessPage(page)) {
        toast('Staff access is limited to POS only', 'error');
        return;
      }
      navigate(page);
    });
  });
}

function initClock() {
  function tick() {
    const now = new Date();
    const t = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    const d = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
    const el = document.getElementById('sidebar-time');
    if (el) el.innerHTML = `<span class="time">${t}</span>${d}`;
  }
  tick();
  setInterval(tick, 1000);
}

function getPaymentMethodLabel(method) {
  const key = String(method || '').toLowerCase();
  return PAYMENT_METHOD_LABELS[key] || method || 'Cash';
}

function getOrderTypeLabel(type) {
  const key = String(type || '').toLowerCase();
  return ORDER_TYPE_LABELS[key] || 'Dine In';
}

function initCheckoutPaymentMethod() {
  const paymentEl = document.getElementById('checkout-payment');
  if (!paymentEl) return;
  paymentEl.addEventListener('change', handlePaymentMethodChange);
}

function handlePaymentMethodChange() {
  const paymentEl = document.getElementById('checkout-payment');
  const totalFieldEl = document.getElementById('checkout-total-field');
  const totalEl = document.getElementById('checkout-total-display');
  if (!paymentEl || !totalEl) return;
  const total = parseFloat(totalEl.textContent.replace(CURRENCY, '')) || 0;
  if (totalFieldEl) totalFieldEl.value = `${CURRENCY}${total.toFixed(2)}`;
}

// ── API HELPER ────────────────────────────────
async function api(action, data = null, method = 'GET', suppressAuthRedirect = false) {
  const url = `${API}?action=${action}`;
  const options = { headers: { 'Content-Type': 'application/json' }, cache: 'no-store' };
  if (data && method === 'GET') {
    const params = new URLSearchParams({ action, ...data, _ts: Date.now() });
    const res = await fetch(`${API}?${params}`, { cache: 'no-store' });
    const json = await res.json();
    // Debug: log API errors to console
    if (!json.success && json.message) {
      console.error(`API Error [${action}]:`, json.message);
    }
    if (res.status === 401 && !suppressAuthRedirect) showLoginModal();
    return json;
  }
  options.method = method || (data ? 'POST' : 'GET');
  if (data) options.body = JSON.stringify(data);
  const requestUrl = (options.method === 'GET') ? `${url}&_ts=${Date.now()}` : url;
  const res = await fetch(requestUrl, options);
  const json = await res.json();
  // Debug: log API errors to console
  if (!json.success && json.message) {
    console.error(`API Error [${action}]:`, json.message);
  }
  if (res.status === 401 && !suppressAuthRedirect) showLoginModal();
  return json;
}

async function initAuth() {
  try {
    const res = await api('me', null, 'GET', true);
    if (res && res.authenticated && res.user) {
      currentUser = res.user;
      closeLoginModal();
      applyAccessControl();
      updateUserInfo();
    } else {
      showLoginModal();
    }
  } catch (err) {
    console.error('Auth initialization error:', err);
    showLoginModal();
  }
}

function canAccessPage(page) {
  if (!currentUser) return false;
  if (currentUser.role === 'staff') return STAFF_ALLOWED_PAGES.has(page);
  if (currentUser.role === 'admin') return true;
  return true;
}

function applyAccessControl() {
  const staff = currentUser && currentUser.role === 'staff';
  document.querySelectorAll('.nav-item').forEach(item => {
    const page = item.dataset.page;
    if (staff) {
      item.style.display = STAFF_ALLOWED_PAGES.has(page) ? '' : 'none';
      return;
    }
    if (currentUser && currentUser.role === 'admin') {
      item.style.display = '';
      return;
    }
    item.style.display = ADMIN_ONLY_PAGES.has(page) ? 'none' : '';
  });
}

function updateUserInfo() {
  const el = document.getElementById('sidebar-user');
  const welcomeEl = document.getElementById('sidebar-welcome');
  const btn = document.getElementById('logout-btn');
  if (el && currentUser) el.textContent = `${currentUser.full_name} (${currentUser.role})`;
  if (welcomeEl) {
    const displayName = currentUser ? (currentUser.full_name || currentUser.username || 'there') : 'there';
    welcomeEl.textContent = `Welcome, ${displayName}`;
  }
  if (btn) btn.style.display = currentUser ? 'inline-flex' : 'none';
}

function showLoginModal() {
  console.log('showLoginModal called');
  const modal = document.getElementById('login-modal');
  if (modal) {
    modal.classList.add('open');
    console.log('Login modal should be visible now');
  } else {
    console.error('Login modal not found!');
    // Fallback: redirect to login page
    window.location.href = 'login.php';
  }
}

function closeLoginModal() {
  const modal = document.getElementById('login-modal');
  if (modal) modal.classList.remove('open');
}

async function login() {
  const username = document.getElementById('login-username').value.trim();
  const password = document.getElementById('login-password').value;
  if (!username || !password) { toast('Enter username and password', 'error'); return; }
  const btn = document.getElementById('login-btn');
  if (btn) btn.disabled = true;
  const res = await api('login', { username, password }, 'POST', true);
  if (btn) btn.disabled = false;
  if (res.success && res.user) {
    currentUser = res.user;
    closeLoginModal();
    applyAccessControl();
    updateUserInfo();
    navigate('pos');
    toast(`Welcome, ${currentUser.full_name}`, 'success');
  } else {
    toast(res.message || 'Login failed', 'error');
  }
}

async function logout() {
  await api('logout', {}, 'POST', true);
  currentUser = null;
  window.location.href = 'login.php';
}

async function loadAccountsPage() {
  const fn = document.getElementById('staff-fullname');
  const un = document.getElementById('staff-username');
  const pw = document.getElementById('staff-password');
  if (fn) fn.value = '';
  if (un) un.value = '';
  if (pw) pw.value = '';
  const tbody = document.getElementById('accounts-tbody');
  if (!tbody) return;
  const res = await api('get_users');
  if (!res.success) {
    tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted" style="padding:30px">Unable to load accounts</td></tr>`;
    return;
  }
  const users = res.data || [];
  if (!users.length) {
    tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted" style="padding:30px">No accounts found</td></tr>`;
    return;
  }
  tbody.innerHTML = users.map(u => {
    const roleBadge = u.role === 'admin' ? 'badge-blue' : 'badge-green';
    const statusBadge = String(u.is_active) === '1' ? 'badge-green' : 'badge-red';
    return `<tr>
      <td><strong>${u.full_name}</strong></td>
      <td>${u.username}</td>
      <td><span class="badge ${roleBadge}">${u.role}</span></td>
      <td><span class="badge ${statusBadge}">${String(u.is_active) === '1' ? 'Active' : 'Inactive'}</span></td>
      <td><button class="btn btn-sm btn-ghost" onclick="openAccountEditModal(${u.id}, '${String(u.full_name).replace(/'/g, "\\'")}', '${String(u.username).replace(/'/g, "\\'")}', '${u.role}', ${String(u.is_active) === '1' ? 1 : 0})">Edit</button></td>
    </tr>`;
  }).join('');
}

async function createStaffAccount() {
  if (!currentUser || currentUser.role !== 'admin') {
    toast('Only administrator can create accounts', 'error');
    return;
  }
  const full_name = document.getElementById('staff-fullname').value.trim();
  const username = document.getElementById('staff-username').value.trim();
  const password = document.getElementById('staff-password').value;
  if (!username || !password) {
    toast('Username and password are required', 'error');
    return;
  }
  const res = await api('create_staff', { full_name, username, password }, 'POST');
  if (res.success) {
    toast(res.message || 'Staff account created', 'success');
    await loadAccountsPage();
  } else {
    toast(res.message || 'Failed to create staff account', 'error');
  }
}

function openAccountEditModal(id, fullName, username, role, isActive) {
  document.getElementById('edit-user-id').value = id;
  document.getElementById('edit-user-fullname').value = fullName;
  document.getElementById('edit-user-username').value = username;
  document.getElementById('edit-user-role').value = role;
  document.getElementById('edit-user-active').value = String(isActive);
  document.getElementById('edit-user-password').value = '';
  openModal('account-edit-modal');
}

async function saveAccountEdit() {
  const data = {
    id: document.getElementById('edit-user-id').value,
    full_name: document.getElementById('edit-user-fullname').value.trim(),
    username: document.getElementById('edit-user-username').value.trim(),
    role: document.getElementById('edit-user-role').value,
    is_active: document.getElementById('edit-user-active').value,
    password: document.getElementById('edit-user-password').value
  };
  if (!data.full_name || !data.username) {
    toast('Name and username are required', 'error');
    return;
  }
  const res = await api('update_user', data, 'POST');
  if (res.success) {
    toast(res.message || 'Account updated', 'success');
    closeModal('account-edit-modal');
    await loadAccountsPage();
  } else {
    toast(res.message || 'Failed to update account', 'error');
  }
}

// ── TOAST ─────────────────────────────────────
function toast(msg, type = 'info', duration = 3000) {
  const container = document.getElementById('toast-container');
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="16" height="16" stroke-width="2">${
    type === 'success' ? '<path d="M20 6L9 17l-5-5"/>' :
    type === 'error'   ? '<circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/>' :
    '<circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>'
  }</svg><span>${msg}</span>`;
  container.appendChild(el);
  setTimeout(() => el.style.opacity = '0', duration - 300);
  setTimeout(() => el.remove(), duration);
}

// ════════════════════════════════════════════
// POS PAGE
// ════════════════════════════════════════════
async function loadPOS() {
  await loadCategories();
  await loadMenuProducts();
  await loadPopularProducts();
}

async function loadPopularProducts() {
  const section = document.getElementById('quick-add-section');
  const container = document.getElementById('quick-add-buttons');
  if (!section || !container) return;
  const res = await api('get_popular_products', { limit: 8 });
  if (!res.success || !res.data || !res.data.length) {
    section.style.display = 'none';
    return;
  }
  popularProducts = res.data;
  section.style.display = 'flex';
  const emojis = { 'Coffee': '☕', 'Tea': '🍵', 'Pastries': '🥐', 'Sandwiches': '🥪', 'Cold Drinks': '🧊', 'Merchandise': '🛍️' };
  container.innerHTML = popularProducts.map(p => {
    const full = products.find(prod => prod.id == p.id) || p;
    const hasVariants = ENABLE_VARIANTS && full.has_variants && full.variants && full.variants.length > 0;
    const outOfStock = hasVariants
      ? full.variants.every(v => v.stock <= 0)
      : full.stock <= 0;
    const imgContent = full.image_url
      ? `<img src="${full.image_url}" alt="${full.name}" class="quick-add-img">`
      : `<span class="quick-add-emoji">${emojis[full.category_name] || '🍽️'}</span>`;
    const price = hasVariants
      ? Math.min(...full.variants.map(v => parseFloat(v.price)))
      : parseFloat(full.price);
    return `<button class="quick-add-btn${outOfStock ? ' disabled' : ''}" ${outOfStock ? 'disabled' : `onclick="addToCartById(${full.id})"`} title="${full.name} — ${CURRENCY}${price.toFixed(2)}${outOfStock ? ' (Out of stock)' : ''}">
      <div class="quick-add-thumb">${imgContent}</div>
      <span class="quick-add-name">${full.name}</span>
      <span class="quick-add-price">${CURRENCY}${price.toFixed(2)}</span>
    </button>`;
  }).join('');
}

function addToCartById(productId) {
  // Look in current products list first, then fall back to popularProducts
  let product = products.find(p => p.id == productId);
  if (!product) {
    product = popularProducts.find(p => p.id == productId);
    if (product && !products.find(p => p.id == productId)) products.push(product);
  }
  if (!product) return;
  // If the product has variants, show the picker
  if (ENABLE_VARIANTS && product.has_variants && product.variants && product.variants.length > 0) {
    openVariantPicker(productId);
    return;
  }
  if (product.stock <= 0) { toast('Not enough stock', 'error'); return; }
  addToCart(productId);
}

async function loadCategories() {
  const res = await api('get_categories');
  if (!res.success) {
    console.error('Failed to load categories:', res.message);
    toast('Failed to load categories: ' + res.message, 'error');
    return;
  }
  categories = res.data;
  renderCategoryTabs();
  const filter = document.getElementById('inv-cat-filter');
  if (filter) {
    filter.innerHTML = '<option value="">All Categories</option>' +
      categories.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
  }
}

function renderCategoryTabs() {
  const tabs = document.getElementById('category-tabs');
  tabs.innerHTML = `<button class="cat-tab ${currentCategory === 0 ? 'active' : ''}" onclick="filterCategory(0)">All</button>`;
  categories.forEach(c => {
    tabs.innerHTML += `<button class="cat-tab ${currentCategory === c.id ? 'active' : ''}" onclick="filterCategory(${c.id})">${c.name}</button>`;
  });
}

function filterCategory(id) {
  currentCategory = id;
  renderCategoryTabs();
  loadMenuProducts();
}

async function loadMenuProducts(search = '') {
  const params = { category_id: currentCategory };
  if (search) params.search = search;
  const res = await api('get_products', params);
  if (!res.success) {
    console.error('Failed to load products:', res.message);
    toast('Failed to load products: ' + res.message, 'error');
    return;
  }
  products = res.data;
  renderMenuGrid();
}

function renderMenuGrid() {
  const grid = document.getElementById('menu-grid');
  if (!products.length) {
    grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--roast);opacity:.5">No products found</div>`;
    return;
  }
  const emojis = { 'Coffee': '☕', 'Tea': '🍵', 'Pastries': '🥐', 'Sandwiches': '🥪', 'Cold Drinks': '🧊', 'Merchandise': '🛍️' };
  grid.innerHTML = products.map(p => {
    const hasVariants = ENABLE_VARIANTS && p.has_variants && p.variants && p.variants.length > 0;
    const outOfStock = !hasVariants && p.stock <= 0;
    const lowStock = !hasVariants && p.stock > 0 && p.stock <= p.low_stock_threshold;
    const image = p.image_url
      ? `<img src="${p.image_url}" alt="${p.name}">`
      : `${emojis[p.category_name] || '🍽️'}`;
    const priceDisplay = hasVariants
      ? `From ${CURRENCY}${Math.min(...p.variants.map(v => parseFloat(v.price))).toFixed(2)}`
      : `${CURRENCY}${parseFloat(p.price).toFixed(2)}`;
    const stockDisplay = hasVariants
      ? `<span class="menu-card-stock variant-badge">Sizes</span>`
      : `<span class="menu-card-stock ${lowStock ? 'low' : ''}">${outOfStock ? 'Out' : p.stock}</span>`;
    const clickAction = outOfStock ? '' : (hasVariants ? `openVariantPicker(${p.id})` : `addToCart(${p.id})`);
    return `
    <div class="menu-card ${outOfStock ? 'out-of-stock' : ''}" onclick="${clickAction}">
      <div class="menu-card-name">${p.name}</div>
      <div class="menu-card-icon">${image}</div>
      <div class="menu-card-size">${hasVariants ? p.variants.map(v => v.size_label).join(' / ') : (p.size || 'Regular')}</div>
      <div class="menu-card-price">${priceDisplay}</div>
      ${stockDisplay}
    </div>`;
  }).join('');
}

// ── VARIANT PICKER ────────────────────────────
function openVariantPicker(productId) {
  const product = products.find(p => p.id == productId);
  if (!product) return;
  const emojis = { 'Coffee': '☕', 'Tea': '🍵', 'Pastries': '🥐', 'Sandwiches': '🥪', 'Cold Drinks': '🧊', 'Merchandise': '🛍️' };
  const thumbEl = document.getElementById('variant-picker-thumb');
  const nameEl = document.getElementById('variant-picker-name');
  const descEl = document.getElementById('variant-picker-desc');
  const listEl = document.getElementById('variant-option-list');
  const titleEl = document.getElementById('variant-picker-title');
  if (titleEl) titleEl.textContent = product.name;
  if (nameEl) nameEl.textContent = product.name;
  if (descEl) descEl.textContent = product.description || product.category_name || '';
  if (thumbEl) {
    thumbEl.innerHTML = product.image_url
      ? `<img src="${product.image_url}" alt="${product.name}" class="quick-add-img">`
      : `<span class="quick-add-emoji">${emojis[product.category_name] || '🍽️'}</span>`;
  }
  if (listEl) {
    listEl.innerHTML = product.variants.map(v => {
      const outOfStock = v.stock <= 0;
      return `<button class="variant-option${outOfStock ? ' disabled' : ''}" ${outOfStock ? 'disabled' : ''} onclick="addToCartWithVariant(${product.id}, ${v.id}, '${v.size_label.replace(/'/g,"\\'")}', ${parseFloat(v.price)})">
        <span class="variant-option-label">${v.size_label}</span>
        <span class="variant-option-price">${CURRENCY}${parseFloat(v.price).toFixed(2)}</span>
        ${outOfStock ? '<span class="variant-option-stock out">Out of stock</span>' : (v.stock <= (v.low_stock_threshold || 5) ? `<span class="variant-option-stock low">Only ${v.stock} left</span>` : '')}
      </button>`;
    }).join('');
  }
  openModal('variant-picker-modal');
}

function addToCartWithVariant(productId, variantId, variantName, price) {
  const product = products.find(p => p.id == productId);
  if (!product) return;
  closeModal('variant-picker-modal');
  const cartKey = `${productId}_${variantId}`;
  const existing = cart.find(i => i._key == cartKey);
  if (existing) {
    existing.quantity++;
  } else {
    cart.push({
      _key: cartKey,
      product_id: product.id,
      product_name: product.name,
      variant_id: variantId,
      variant_name: variantName,
      unit_price: price,
      quantity: 1
    });
  }
  renderCart();
}

// ── CART ──────────────────────────────────────
function addToCart(productId) {
  const product = products.find(p => p.id == productId);
  if (!product) return;
  if (ENABLE_VARIANTS && product.has_variants && product.variants && product.variants.length > 0) {
    openVariantPicker(productId);
    return;
  }
  if (product.stock <= 0) return;
  const existing = cart.find(i => i.product_id == productId && !i.variant_id);
  const inCartQty = existing ? existing.quantity : 0;
  if (inCartQty >= product.stock) { toast('Not enough stock', 'error'); return; }
  if (existing) {
    existing.quantity++;
  } else {
    cart.push({ _key: String(productId), product_id: product.id, product_name: product.name, unit_price: parseFloat(product.price), quantity: 1 });
  }
  renderCart();
}

function updateCartQty(key, delta) {
  const idx = cart.findIndex(i => i._key == key);
  if (idx === -1) return;
  cart[idx].quantity += delta;
  if (cart[idx].quantity <= 0) cart.splice(idx, 1);
  renderCart();
}

function removeFromCart(key) {
  cart = cart.filter(i => i._key != key);
  renderCart();
}

function clearCart() {
  cart = [];
  renderCart();
}

function renderCart() {
  const itemsEl = document.getElementById('cart-items');
  const countEl = document.getElementById('cart-count');
  const totalItems = cart.reduce((s, i) => s + i.quantity, 0);
  countEl.textContent = totalItems ? `${totalItems} item${totalItems > 1 ? 's' : ''}` : 'Empty';

  if (!cart.length) {
    itemsEl.innerHTML = `<div class="cart-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg><p>Cart is empty</p></div>`;
  } else {
    itemsEl.innerHTML = cart.map(item => {
      const displayName = item.variant_name ? `${item.product_name} <span class="cart-variant-badge">${item.variant_name}</span>` : item.product_name;
      const key = item._key;
      return `
      <div class="cart-item">
        <div style="flex:1">
          <div class="cart-item-name">${displayName}</div>
          <div class="cart-item-price">${CURRENCY}${item.unit_price.toFixed(2)} each</div>
        </div>
        <div class="cart-qty">
          <button class="qty-btn" onclick="updateCartQty('${key}',-1)">−</button>
          <span class="qty-num">${item.quantity}</span>
          <button class="qty-btn" onclick="updateCartQty('${key}',1)">+</button>
        </div>
        <div class="cart-item-total">${CURRENCY}${(item.unit_price * item.quantity).toFixed(2)}</div>
        <button class="cart-remove" onclick="removeFromCart('${key}')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
      </div>`;
    }).join('');
  }
  updateCartTotals();
}

function updateCartTotals() {
  const discount = parseFloat(document.getElementById('cart-discount')?.value || 0) || 0;
  const subtotal = cart.reduce((s, i) => s + i.unit_price * i.quantity, 0);
  const afterDiscount = Math.max(0, subtotal - discount);
  const total = afterDiscount;
  document.getElementById('cart-subtotal').textContent = `${CURRENCY}${subtotal.toFixed(2)}`;
  document.getElementById('cart-discount-display').textContent = `−${CURRENCY}${discount.toFixed(2)}`;
  document.getElementById('cart-total').textContent = `${CURRENCY}${total.toFixed(2)}`;
  const checkoutBtn = document.getElementById('checkout-btn');
  if (checkoutBtn) checkoutBtn.disabled = cart.length === 0;
}

// ── CHECKOUT MODAL ────────────────────────────
function openCheckout() {
  if (!cart.length) return;
  const discount = parseFloat(document.getElementById('cart-discount')?.value || 0) || 0;
  const subtotal = cart.reduce((s, i) => s + i.unit_price * i.quantity, 0);
  const afterDiscount = Math.max(0, subtotal - discount);
  const total = afterDiscount;
  document.getElementById('checkout-total-display').textContent = `${CURRENCY}${total.toFixed(2)}`;
  handlePaymentMethodChange();
  openModal('checkout-modal');
}

function buildCheckoutPayload() {
  const discount = parseFloat(document.getElementById('cart-discount')?.value || 0) || 0;
  const total = parseFloat((document.getElementById('checkout-total-display')?.textContent || '').replace(CURRENCY, '')) || 0;
  return {
    items: cart,
    customer_name: document.getElementById('checkout-customer').value || 'Walk-in',
    order_type: document.getElementById('checkout-order-type')?.value || 'dine_in',
    payment_method: document.getElementById('checkout-payment').value,
    discount: discount,
    amount_paid: total,
    notes: document.getElementById('checkout-notes').value
  };
}

function openCashCalculator(payload) {
  const total = parseFloat(document.getElementById('checkout-total-display').textContent.replace(CURRENCY, '')) || 0;
  pendingOrderPayload = { ...payload };
  const totalEl = document.getElementById('cash-total-display');
  const paidEl = document.getElementById('cash-amount-paid');
  if (totalEl) totalEl.textContent = `${CURRENCY}${total.toFixed(2)}`;
  if (paidEl) paidEl.value = '0';
  handleCashInput();
  closeModal('checkout-modal');
  openModal('cash-calculator-modal');
  if (paidEl) paidEl.focus();
}

function closeCashCalculator() {
  closeModal('cash-calculator-modal');
  if (pendingOrderPayload) openModal('checkout-modal');
}

function setCashTender(amount) {
  const paidEl = document.getElementById('cash-amount-paid');
  const totalEl = document.getElementById('cash-total-display');
  if (!paidEl || !totalEl) return;
  const total = parseFloat(totalEl.textContent.replace(CURRENCY, '')) || 0;
  if (amount === 'exact') {
    paidEl.value = total.toFixed(2);
  } else {
    paidEl.value = (total + (parseFloat(amount) || 0)).toFixed(2);
  }
  handleCashInput();
}

function handleCashInput() {
  const paidEl = document.getElementById('cash-amount-paid');
  if (!paidEl) return;
  let value = String(paidEl.value || '').replace(/[^\d.]/g, '');
  const firstDot = value.indexOf('.');
  if (firstDot !== -1) {
    value = value.slice(0, firstDot + 1) + value.slice(firstDot + 1).replace(/\./g, '');
  }
  if (value.startsWith('.')) value = `0${value}`;
  if (value.includes('.')) {
    let [whole, decimal] = value.split('.');
    whole = whole.replace(/^0+(?=\d)/, '');
    if (whole === '') whole = '0';
    value = `${whole}.${(decimal || '').slice(0, 2)}`;
  } else {
    value = value.replace(/^0+(?=\d)/, '');
  }
  paidEl.value = value;
  calcCashChange();
}

function cashCalculatorKey(key) {
  const paidEl = document.getElementById('cash-amount-paid');
  if (!paidEl) return;
  let value = String(paidEl.value || '');
  if (key === '.') {
    if (!value.includes('.')) value = value ? `${value}.` : '0.';
  } else if (key === '00') {
    value = value ? `${value}00` : '0';
  } else {
    value = value === '0' ? key : `${value}${key}`;
  }
  paidEl.value = value;
  handleCashInput();
}

function cashCalculatorBackspace() {
  const paidEl = document.getElementById('cash-amount-paid');
  if (!paidEl) return;
  paidEl.value = String(paidEl.value || '').slice(0, -1);
  handleCashInput();
}

function cashCalculatorClear() {
  const paidEl = document.getElementById('cash-amount-paid');
  if (!paidEl) return;
  paidEl.value = '';
  handleCashInput();
}

function calcCashChange() {
  const shortfallEl = document.getElementById('cash-shortfall');
  const completeBtn = document.getElementById('cash-complete-btn');
  const total = parseFloat((document.getElementById('cash-total-display')?.textContent || '').replace(CURRENCY, '')) || 0;
  const paid = parseFloat(document.getElementById('cash-amount-paid')?.value) || 0;
  const change = Math.max(0, paid - total);
  document.getElementById('cash-change').textContent = `${CURRENCY}${change.toFixed(2)}`;
  if (shortfallEl) {
    if (paid < total) {
      shortfallEl.style.display = 'block';
      shortfallEl.textContent = `Short by ${CURRENCY}${(total - paid).toFixed(2)}`;
    } else {
      shortfallEl.style.display = 'none';
      shortfallEl.textContent = '';
    }
  }
  if (completeBtn) {
    completeBtn.disabled = paid < total;
  }
}

async function placeOrder() {
  const btn = document.getElementById('place-order-btn');
  btn.disabled = true;
  btn.textContent = 'Processing...';
  const payload = buildCheckoutPayload();
  if (payload.payment_method === 'cash') {
    btn.disabled = false;
    btn.textContent = 'Confirm & Pay';
    openCashCalculator(payload);
    return;
  }
  await submitOrder(payload, btn, 'Confirm & Pay');
}

async function completeCashOrder() {
  const btn = document.getElementById('cash-complete-btn');
  if (!pendingOrderPayload) {
    toast('No pending cash payment', 'error');
    closeModal('cash-calculator-modal');
    return;
  }
  const total = parseFloat((document.getElementById('cash-total-display')?.textContent || '').replace(CURRENCY, '')) || 0;
  const amountPaid = parseFloat(document.getElementById('cash-amount-paid')?.value) || 0;
  if (amountPaid < total) {
    calcCashChange();
    toast('Amount paid is less than total', 'error');
    return;
  }
  const payload = { ...pendingOrderPayload, amount_paid: amountPaid, payment_method: 'cash' };
  await submitOrder(payload, btn, 'Complete Payment');
}

async function submitOrder(payload, triggerBtn, defaultLabel) {
  if (triggerBtn) {
    triggerBtn.disabled = true;
    triggerBtn.textContent = 'Processing...';
  }
  const res = await api('create_order', payload, 'POST');
  if (res.success) {
    const receiptPayment = {
      amount_paid: payload.amount_paid,
      change: Math.max(0, payload.amount_paid - (res.total ?? 0))
    };
    pendingOrderPayload = null;
    closeModal('cash-calculator-modal');
    closeModal('checkout-modal');
    showReceipt(res, receiptPayment, payload);
    clearCart();
    loadMenuProducts();
    toast('Order placed successfully!', 'success');
  } else {
    toast(res.message || 'Order failed', 'error');
    if (triggerBtn) {
      triggerBtn.disabled = false;
      triggerBtn.textContent = defaultLabel;
    }
  }
}

function showReceipt(order, payment = null, payload = null) {
  const cartSnapshot = [...cart];
  const discount = parseFloat(document.getElementById('cart-discount')?.value || 0) || 0;
  const subtotal = cartSnapshot.reduce((s, i) => s + i.unit_price * i.quantity, 0);
  const afterDiscount = Math.max(0, subtotal - discount);
  const total = afterDiscount;
  const paid = payment ? (parseFloat(payment.amount_paid) || total) : total;
  const change = payment ? (parseFloat(payment.change) || 0) : 0;
  const orderType = getOrderTypeLabel(payload?.order_type || order?.order_type || 'dine_in');
  const paymentMethod = payment ? getPaymentMethodLabel(payment.payment_method || 'cash') : 'Cash';
  const cashierName = currentUser ? currentUser.full_name : 'Cashier';
  
  // Better item format: Product Name, then "1 x ₱100.00  ₱100.00"
  const itemsHtml = cartSnapshot.map(i => {
    const lineTotal = (i.unit_price * i.quantity).toFixed(2);
    const variant = i.variant_name ? `<div class="receipt-variant">${i.variant_name}</div>` : '';
    return `<div class="receipt-item">
      <div class="receipt-item-name">${i.product_name}${variant}</div>
      <div class="receipt-item-line">${i.quantity} x ${CURRENCY}${i.unit_price.toFixed(2)}<span class="receipt-item-total">${CURRENCY}${lineTotal}</span></div>
    </div>`;
  }).join('');
  
  document.getElementById('receipt-content').innerHTML = `
    <div class="receipt">
      <div class="receipt-header">
        <div class="receipt-logo">Ascot Hostel.</div>
        <div class="receipt-addr">Aurora State College of Technology Brgy. Zabali, Baler, Aurora, Philippines</div>
        <div style="margin-top:10px;font-size:12px">${new Date().toLocaleString()}</div>
        <div style="font-size:12px;font-weight:600">Order: ${order.order_number}</div>
      </div>
      <hr class="receipt-divider">
      <div class="receipt-meta"><span>Cashier:</span><span>${cashierName}</span></div>
      <div class="receipt-meta"><span>Payment:</span><span>${paymentMethod}</span></div>
      <div class="receipt-meta"><span>Type:</span><span>${orderType}</span></div>
      <hr class="receipt-divider">
      ${itemsHtml}
      <hr class="receipt-divider">
      <div class="receipt-row"><span>Subtotal</span><span>${CURRENCY}${subtotal.toFixed(2)}</span></div>
      <div class="receipt-row"><span>Discount</span><span>−${CURRENCY}${discount.toFixed(2)}</span></div>
      <div class="receipt-row receipt-total"><span>TOTAL</span><span>${CURRENCY}${total.toFixed(2)}</span></div>
      <hr class="receipt-divider">
      <div class="receipt-row"><span>Amount Paid</span><span>${CURRENCY}${paid.toFixed(2)}</span></div>
      <div class="receipt-row"><span>Change</span><span>${CURRENCY}${change.toFixed(2)}</span></div>
      <div class="receipt-footer">Thank you for visiting!<br>Please come again ☕</div>
    </div>`;
  openModal('receipt-modal');
}

// ════════════════════════════════════════════
// INVENTORY PAGE
// ════════════════════════════════════════════
async function loadInventory() {
  const res = await api('get_products', { category_id: 0 });
  if (!res.success) {
    console.error('Failed to load inventory:', res.message);
    toast('Failed to load inventory: ' + res.message, 'error');
    return;
  }
  renderInventoryTable(res.data);
  loadCategories();
}

function renderInventoryTable(data) {
  const tbody = document.getElementById('inv-tbody');
  if (!data.length) {
    tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted" style="padding:40px">No products found</td></tr>`;
    return;
  }
  tbody.innerHTML = data.map(p => {
    const pct = Math.min(100, Math.round((p.stock / Math.max(1, p.low_stock_threshold * 3)) * 100));
    const lvl = p.stock <= p.low_stock_threshold ? 'low' : p.stock <= p.low_stock_threshold * 2 ? 'mid' : 'high';
    const badge = p.stock <= 0 ? 'badge-red' : p.stock <= p.low_stock_threshold ? 'badge-caramel' : 'badge-green';
    const statusText = p.stock <= 0 ? 'Out of Stock' : p.stock <= p.low_stock_threshold ? 'Low Stock' : 'In Stock';
    return `<tr>
      <td><strong>${p.name}</strong></td>
      <td>${p.category_name || '—'}</td>
      <td>${CURRENCY}${parseFloat(p.price).toFixed(2)}</td>
      <td>${CURRENCY}${parseFloat(p.cost).toFixed(2)}</td>
      <td>
        <div class="stock-indicator">
          <div class="stock-bar"><div class="stock-fill ${lvl}" style="width:${pct}%"></div></div>
          <span>${p.stock} ${p.unit}</span>
        </div>
      </td>
      <td>${p.low_stock_threshold}</td>
      <td><span class="badge ${badge}">${statusText}</span></td>
      <td>
        <div class="d-flex gap-8">
          <button class="btn btn-sm btn-secondary" onclick="openAdjustStock(${p.id},'${p.name.replace(/'/g, "\\'")}',${p.stock})">Adjust</button>
          <button class="btn btn-sm btn-ghost" onclick="openEditProduct(${p.id})">Edit</button>
          <button class="btn btn-sm btn-danger" onclick="deleteProduct(${p.id},'${p.name.replace(/'/g, "\\'")}')">Delete</button>
        </div>
      </td>
    </tr>`;
  }).join('');
}

async function searchInventory() {
  const q = document.getElementById('inv-search').value;
  const cat = document.getElementById('inv-cat-filter').value;
  const res = await api('get_products', { search: q, category_id: cat || 0 });
  if (res.success) renderInventoryTable(res.data);
}

async function openAddProduct() {
  const form = document.getElementById('product-form');
  if (form) form.reset();
  document.getElementById('product-name').value = '';
  document.getElementById('product-desc').value = '';
  document.getElementById('product-price').value = '';
  document.getElementById('product-cost').value = '';
  document.getElementById('product-threshold').value = '';
  document.getElementById('product-unit').value = 'pcs';
  document.getElementById('product-size').value = 'Regular';
  document.getElementById('product-id').value = '';
  document.getElementById('product-image-file').value = '';
  document.getElementById('product-image-preview').src = '';
  document.getElementById('product-image-preview').style.display = 'none';
  document.getElementById('product-modal-title').textContent = 'Add Product';
  await loadCategoryOptions('product-category');
  await loadProductIngredientInputs();
  openModal('product-modal');
}

async function openEditProduct(id) {
  const res = await api('get_product', { id });
  if (!res.success) return;
  const p = res.data;
  document.getElementById('product-id').value = p.id;
  document.getElementById('product-name').value = p.name;
  document.getElementById('product-desc').value = p.description || '';
  document.getElementById('product-price').value = p.price;
  document.getElementById('product-cost').value = p.cost;
  document.getElementById('product-threshold').value = p.low_stock_threshold;
  document.getElementById('product-unit').value = p.unit;
  document.getElementById('product-size').value = p.size || 'Regular';
  document.getElementById('product-image-file').value = '';
  const preview = document.getElementById('product-image-preview');
  if (p.image_url) {
    preview.src = p.image_url;
    preview.style.display = 'block';
  } else {
    preview.src = '';
    preview.style.display = 'none';
  }
  document.getElementById('product-modal-title').textContent = 'Edit Product';
  await loadCategoryOptions('product-category');
  document.getElementById('product-category').value = p.category_id;
  const recipeRes = await api('get_product_ingredients', { product_id: id });
  const recipeMap = {};
  if (recipeRes.success) {
    recipeRes.data.forEach(r => {
      recipeMap[r.ingredient_id] = r.quantity_required;
    });
  }
  await loadProductIngredientInputs(recipeMap);
  openModal('product-modal');
}

// ── VARIANTS MANAGEMENT (Product Modal) ──────
let _variantEdits = []; // local edits before save
let _ingredientCatalog = [];
let _baseRecipeMap = {};
let _variantRecipeMap = {};

function renderVariantsList(variants) {
  _variantEdits = variants.map(v => ({ ...v, _dirty: false, _new: false }));
  _renderVariantRows();
}

function _renderVariantRows() {
  const list = document.getElementById('variants-list');
  if (!list) return;
  if (!_variantEdits.length) {
    list.innerHTML = `<div class="variants-empty">No size variants. Base price will be used.</div>`;
    if (_ingredientCatalog.length) {
      snapshotIngredientInputs();
      renderProductIngredientInputs();
    }
    return;
  }
  list.innerHTML = _variantEdits.map((v, idx) => `
    <div class="variant-row" data-idx="${idx}">
      <input class="form-control variant-size-input" placeholder="Size (e.g. Small)" value="${v.size_label || ''}" oninput="updateVariantField(${idx},'size_label',this.value)">
      <input class="form-control variant-price-input" type="number" min="0" step="0.01" placeholder="Price" value="${v.price || ''}" oninput="updateVariantField(${idx},'price',this.value)">
      <input class="form-control variant-stock-input" type="number" min="0" placeholder="Stock" value="${v.stock || 0}" oninput="updateVariantField(${idx},'stock',this.value)">
      <button type="button" class="btn btn-sm btn-danger" onclick="removeVariantRow(${idx})">✕</button>
    </div>`).join('');
  if (_ingredientCatalog.length) {
    snapshotIngredientInputs();
    renderProductIngredientInputs();
  }
}

function updateVariantField(idx, field, value) {
  if (_variantEdits[idx]) {
    _variantEdits[idx][field] = value;
    _variantEdits[idx]._dirty = true;
    if (field === 'size_label' && _ingredientCatalog.length) {
      snapshotIngredientInputs();
      renderProductIngredientInputs();
    }
  }
}

function addVariantRow() {
  _variantEdits.push({ id: null, size_label: '', price: '', stock: 0, is_active: 1, _new: true, _dirty: true });
  _renderVariantRows();
  // Focus last size input
  const rows = document.querySelectorAll('.variant-row');
  const last = rows[rows.length - 1];
  if (last) last.querySelector('.variant-size-input')?.focus();
}

function removeVariantRow(idx) {
  _variantEdits.splice(idx, 1);
  _renderVariantRows();
}

async function _saveVariants(productId) {
  for (let i = 0; i < _variantEdits.length; i++) {
    const v = _variantEdits[i];
    if (!v._dirty && !v._new) continue;
    if (!v.size_label || v.price === '') continue;
    const res = await api('save_variant', {
      id: v.id || 0,
      product_id: productId,
      size_label: v.size_label,
      price: parseFloat(v.price) || 0,
      stock: parseInt(v.stock) || 0,
      low_stock_threshold: parseInt(v.low_stock_threshold) || 5,
      sort_order: i
    }, 'POST');
    if (res && res.success && res.id && !v.id) {
      _variantEdits[i].id = res.id;
    }
  }
}

function collectVariantIngredientInputs() {
  const entries = [];
  document.querySelectorAll('.product-ingredient-variant-qty').forEach(input => {
    const ingredientId = parseInt(input.dataset.ingredientId);
    const variantIdx = parseInt(input.dataset.variantIdx);
    const qty = parseFloat(input.value);
    if (isNaN(ingredientId) || isNaN(variantIdx) || isNaN(qty) || qty <= 0) return;
    entries.push({ variantIdx, ingredientId, quantity: qty });
  });
  return entries;
}

async function _saveVariantIngredients(productId, entries) {
  if (!productId) return;
  const items = [];
  entries.forEach(entry => {
    const variant = _variantEdits[entry.variantIdx];
    const variantId = variant && variant.id ? parseInt(variant.id) : 0;
    if (!variantId) return;
    items.push({
      variant_id: variantId,
      ingredient_id: entry.ingredientId,
      quantity_required: entry.quantity
    });
  });
  await api('save_variant_ingredients', { product_id: productId, items }, 'POST');
}

async function loadCategoryOptions(selectId) {
  if (!categories.length) await loadCategories();
  const sel = document.getElementById(selectId);
  sel.innerHTML = '<option value="">Select category</option>' +
    categories.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
}

async function saveProduct() {
  const ingredients = [];
  document.querySelectorAll('.product-ingredient-qty').forEach(input => {
    const ingredientId = parseInt(input.dataset.ingredientId);
    const qty = parseFloat(input.value);
    if (!isNaN(ingredientId) && !isNaN(qty) && qty > 0) {
      ingredients.push({ ingredient_id: ingredientId, quantity_required: qty });
    }
  });
  const data = {
    id: document.getElementById('product-id').value,
    category_id: document.getElementById('product-category').value,
    name: document.getElementById('product-name').value,
    description: document.getElementById('product-desc').value,
    price: document.getElementById('product-price').value,
    cost: document.getElementById('product-cost').value,
    low_stock_threshold: document.getElementById('product-threshold').value,
    unit: document.getElementById('product-unit').value,
    size: document.getElementById('product-size').value,
    ingredients: ingredients
  };
  if (!data.name) { toast('Product name required', 'error'); return; }
  const res = await api('save_product', data, 'POST');
  if (res.success) {
    const file = document.getElementById('product-image-file')?.files?.[0];
    if (file && res.id) {
      const form = new FormData();
      form.append('product_id', res.id);
      form.append('image', file);
      const uploadRes = await fetch(`${API}?action=upload_product_image`, { method: 'POST', body: form, cache: 'no-store' });
      const uploadJson = await uploadRes.json();
      if (!uploadJson.success) {
        toast(uploadJson.message || 'Image upload failed', 'error');
      }
    }
    if (ENABLE_VARIANTS) {
      const variantIngredientEntries = collectVariantIngredientInputs();
      await _saveVariants(res.id);
      await _saveVariantIngredients(res.id, variantIngredientEntries);
    }
    toast(res.message, 'success');
    closeModal('product-modal');
    loadInventory();
    loadMenuProducts();
  } else {
    toast(res.message || 'Failed to save', 'error');
  }
}

function initProductImagePreview() {
  const input = document.getElementById('product-image-file');
  const preview = document.getElementById('product-image-preview');
  if (!input || !preview) return;
  input.addEventListener('change', () => {
    const file = input.files && input.files[0];
    if (!file) {
      preview.src = '';
      preview.style.display = 'none';
      return;
    }
    const reader = new FileReader();
    reader.onload = e => {
      preview.src = e.target.result;
      preview.style.display = 'block';
    };
    reader.readAsDataURL(file);
  });
}

async function loadProductIngredientInputs(selected = {}, variantMap = {}) {
  const wrap = document.getElementById('product-ingredients-grid');
  if (!wrap) return;
  const res = await api('get_ingredients');
  if (!res.success || !res.data.length) {
    _ingredientCatalog = [];
    wrap.innerHTML = '<div class="text-muted">No ingredients available. Add ingredients first.</div>';
    return;
  }
  _ingredientCatalog = res.data;
  _baseRecipeMap = selected || {};
  _variantRecipeMap = variantMap || {};
  renderProductIngredientInputs();
}

function renderProductIngredientInputs() {
  const wrap = document.getElementById('product-ingredients-grid');
  if (!wrap) return;
  if (!_ingredientCatalog.length) {
    wrap.innerHTML = '<div class="text-muted">No ingredients available. Add ingredients first.</div>';
    return;
  }
  updateBaseSizeHint(document.getElementById('product-size')?.value);
  const variants = getSizeVariantsForIngredientInputs();
  const hasVariants = variants.length > 0;
  wrap.innerHTML = _ingredientCatalog.map(i => {
    const val = _baseRecipeMap[i.id] !== undefined ? parseFloat(_baseRecipeMap[i.id]) : '';
    const safeName = String(i.name).replace(/'/g, "\\'");
    const safeUnit = String(i.unit).replace(/'/g, "\\'");
    const stock = parseFloat(i.stock || 0);
    const threshold = parseFloat(i.low_stock_threshold || 0);
    const variantInputs = hasVariants ? `
      <div class="ingredient-variant-grid">
        ${variants.map((v, idx) => {
          const variantId = v.id || '';
          const variantKey = variantId ? String(variantId) : `idx_${idx}`;
          const label = v.size_label || `Size ${idx + 1}`;
          const variantVal = (_variantRecipeMap[variantKey] && _variantRecipeMap[variantKey][i.id] !== undefined)
            ? parseFloat(_variantRecipeMap[variantKey][i.id])
            : '';
          return `<div class="ingredient-variant-item">
            <span class="ingredient-variant-label">${label}</span>
            <input type="number" class="form-control product-ingredient-variant-qty" data-variant-idx="${idx}" data-variant-id="${variantId}" data-ingredient-id="${i.id}" min="0" step="0.01" value="${variantVal}" placeholder="0">
          </div>`;
        }).join('')}
      </div>` : '';
    return `<div class="form-group mb-0">
      <div class="ingredient-line-head">
        <div class="ingredient-name-wrap">
          <label class="form-label mb-0">${i.name}</label>
          <span class="ingredient-unit-chip">${i.unit}</span>
        </div>
        <button type="button" class="btn btn-sm btn-ghost" onclick="openEditIngredient(${i.id}, '${safeName}', '${safeUnit}', ${stock}, ${threshold}, ${parseFloat(i.coffee_qty || 0)})">Edit</button>
      </div>
      <input type="number" class="form-control product-ingredient-qty" data-ingredient-id="${i.id}" min="0" step="0.01" value="${val}" placeholder="0">
      ${variantInputs}
    </div>`;
  }).join('');
}

function getSizeVariantsForIngredientInputs() {
  if (!ENABLE_VARIANTS) return [];
  const variants = _variantEdits.filter(v => v && (v.size_label || v.id || v._new));
  if (!variants.length) return [];
  const order = ['small', 'medium', 'large', 'regular'];
  return variants.slice().sort((a, b) => {
    const aLabel = String(a.size_label || '').toLowerCase();
    const bLabel = String(b.size_label || '').toLowerCase();
    const aIdx = order.indexOf(aLabel);
    const bIdx = order.indexOf(bLabel);
    if (aIdx === -1 && bIdx === -1) return aLabel.localeCompare(bLabel);
    if (aIdx === -1) return 1;
    if (bIdx === -1) return -1;
    return aIdx - bIdx;
  });
}

function updateBaseSizeHint(sizeLabel) {
  const hint = document.getElementById('ingredient-base-size-hint');
  if (!hint) return;
  const label = sizeLabel || 'Base';
  hint.textContent = `Base size (${label}) ingredients apply by default.`;
}

function snapshotIngredientInputs() {
  const baseInputs = document.querySelectorAll('.product-ingredient-qty');
  if (!baseInputs.length) return;
  const baseMap = {};
  baseInputs.forEach(input => {
    const ingredientId = parseInt(input.dataset.ingredientId);
    const qty = parseFloat(input.value);
    if (!isNaN(ingredientId) && !isNaN(qty) && qty > 0) {
      baseMap[ingredientId] = qty;
    }
  });
  _baseRecipeMap = baseMap;
  const variantMap = {};
  document.querySelectorAll('.product-ingredient-variant-qty').forEach(input => {
    const ingredientId = parseInt(input.dataset.ingredientId);
    const variantIdx = parseInt(input.dataset.variantIdx);
    const variantId = input.dataset.variantId;
    const qty = parseFloat(input.value);
    if (isNaN(ingredientId) || isNaN(variantIdx) || isNaN(qty) || qty <= 0) return;
    const key = variantId ? String(variantId) : `idx_${variantIdx}`;
    if (!variantMap[key]) variantMap[key] = {};
    variantMap[key][ingredientId] = qty;
  });
  _variantRecipeMap = variantMap;
}

async function loadIngredients() {
  const res = await api('get_ingredients');
  if (!res.success) return;
  renderIngredientsTable(res.data);
}

async function loadCategoryManager() {
  const res = await api('get_categories_manage');
  if (!res.success) return;
  renderCategoryManagerTable(res.data);
}

function renderCategoryManagerTable(data) {
  const tbody = document.getElementById('cat-tbody');
  if (!tbody) return;
  if (!data.length) {
    tbody.innerHTML = `<tr><td colspan="3" class="text-center text-muted" style="padding:40px">No categories found</td></tr>`;
    return;
  }
  tbody.innerHTML = data.map(c => `
    <tr>
      <td><strong>${c.name}</strong></td>
      <td><span class="badge badge-blue">${c.product_count} product${c.product_count == 1 ? '' : 's'}</span></td>
      <td>
        <div class="d-flex gap-8">
          <button class="btn btn-sm btn-ghost" onclick="openCategoryModal(${c.id},'${String(c.name).replace(/'/g, "\\'")}')">Edit</button>
          <button class="btn btn-sm btn-danger" onclick="deleteCategory(${c.id},'${String(c.name).replace(/'/g, "\\'")}')">Delete</button>
        </div>
      </td>
    </tr>
  `).join('');
}

function openCategoryModal(id = '', name = '') {
  document.getElementById('category-id').value = id || '';
  document.getElementById('category-name').value = name || '';
  document.getElementById('category-modal-title').textContent = id ? 'Edit Category' : 'Add Category';
  openModal('category-modal');
}

async function saveCategory() {
  const data = {
    id: document.getElementById('category-id').value,
    name: document.getElementById('category-name').value
  };
  if (!data.name) { toast('Category name required', 'error'); return; }
  const res = await api('save_category', data, 'POST');
  if (res.success) {
    toast(res.message || 'Category saved', 'success');
    closeModal('category-modal');
    await loadCategoryManager();
    await loadCategories();
    const filter = document.getElementById('inv-cat-filter');
    if (filter) {
      filter.innerHTML = '<option value="">All Categories</option>' + categories.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
    }
  } else {
    toast(res.message || 'Failed to save category', 'error');
  }
}

async function deleteCategory(id, name) {
  if (!confirm(`Delete category "${name}"? Products under it will become uncategorized.`)) return;
  const res = await api('delete_category', { id }, 'POST');
  if (res.success) {
    toast(res.message || 'Category deleted', 'success');
    await loadCategoryManager();
    await loadCategories();
    const filter = document.getElementById('inv-cat-filter');
    if (filter) {
      filter.innerHTML = '<option value="">All Categories</option>' + categories.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
    }
  } else {
    toast(res.message || 'Failed to delete category', 'error');
  }
}

function renderIngredientsTable(data) {
  const tbody = document.getElementById('ing-tbody');
  if (!tbody) return;
  if (!data.length) {
    tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted" style="padding:40px">No ingredients found</td></tr>`;
    return;
  }
  tbody.innerHTML = data.map(i => {
    const stock = parseFloat(i.stock || 0);
    const threshold = parseFloat(i.low_stock_threshold || 0);
    const low = threshold > 0 && stock <= threshold;
    return `<tr>
      <td><strong>${i.name}</strong></td>
      <td>${i.unit}</td>
      <td>${stock.toFixed(2)}</td>
      <td>${threshold.toFixed(2)}</td>
      <td><span class="badge ${low ? 'badge-caramel' : 'badge-green'}">${i.products_using} product${i.products_using == 1 ? '' : 's'}</span></td>
      <td>
        <div class="d-flex gap-8">
          <button class="btn btn-sm btn-ghost" onclick="openEditIngredient(${i.id}, '${String(i.name).replace(/'/g, "\\'")}', '${String(i.unit).replace(/'/g, "\\'")}', ${stock}, ${threshold}, ${parseFloat(i.coffee_qty || 0)})">Edit</button>
          <button class="btn btn-sm btn-danger" onclick="deleteIngredient(${i.id}, '${String(i.name).replace(/'/g, "\\'")}')">Delete</button>
        </div>
      </td>
    </tr>`;
  }).join('');
}

function openAddIngredient() {
  document.getElementById('ingredient-id').value = '';
  const nameInput = document.getElementById('ingredient-name');
  const stockInput = document.getElementById('ingredient-stock');
  const thresholdInput = document.getElementById('ingredient-threshold');
  const coffeeQtyInput = document.getElementById('ingredient-coffee-qty');
  nameInput.value = '';
  const unitSelect = document.getElementById('ingredient-unit');
  unitSelect.value = 'g';
  unitSelect.disabled = false;
  nameInput.disabled = false;
  stockInput.disabled = false;
  thresholdInput.disabled = false;
  coffeeQtyInput.disabled = false;
  stockInput.value = '';
  thresholdInput.value = '';
  coffeeQtyInput.value = '';
  document.getElementById('ingredient-modal-title').textContent = 'Add Ingredient';
  openModal('ingredient-modal');
}

function openEditIngredient(id, name, unit, stock, threshold, coffeeQty = 0) {
  document.getElementById('ingredient-id').value = id;
  const nameInput = document.getElementById('ingredient-name');
  const stockInput = document.getElementById('ingredient-stock');
  const thresholdInput = document.getElementById('ingredient-threshold');
  const coffeeQtyInput = document.getElementById('ingredient-coffee-qty');
  nameInput.value = name;
  const unitSelect = document.getElementById('ingredient-unit');
  if (![...unitSelect.options].some(o => o.value === unit)) {
    const extra = document.createElement('option');
    extra.value = unit;
    extra.textContent = unit;
    unitSelect.appendChild(extra);
  }
  unitSelect.value = unit;
  unitSelect.disabled = false;
  nameInput.disabled = false;
  stockInput.disabled = false;
  thresholdInput.disabled = false;
  coffeeQtyInput.disabled = false;
  stockInput.value = Number(stock).toFixed(2);
  thresholdInput.value = Number(threshold).toFixed(2);
  coffeeQtyInput.value = coffeeQty > 0 ? Number(coffeeQty).toFixed(2) : '';
  document.getElementById('ingredient-modal-title').textContent = 'Edit Ingredient';
  openModal('ingredient-modal');
}

async function saveIngredient() {
  const data = {
    id: document.getElementById('ingredient-id').value,
    name: document.getElementById('ingredient-name').value,
    unit: document.getElementById('ingredient-unit').value || 'g',
    stock: document.getElementById('ingredient-stock').value || 0,
    low_stock_threshold: document.getElementById('ingredient-threshold').value || 0,
    coffee_qty: document.getElementById('ingredient-coffee-qty').value || 0
  };
  if (!data.name) { toast('Ingredient name required', 'error'); return; }
  const res = await api('save_ingredient', data, 'POST');
  if (res.success) {
    const selectedRecipe = {};
    document.querySelectorAll('.product-ingredient-qty').forEach(input => {
      const ingredientId = parseInt(input.dataset.ingredientId);
      if (!isNaN(ingredientId)) {
        selectedRecipe[ingredientId] = input.value;
      }
    });
    toast(res.message || 'Ingredient saved', 'success');
    closeModal('ingredient-modal');
    await loadIngredients();
    await loadProductIngredientInputs(selectedRecipe, _variantRecipeMap);
  } else {
    toast(res.message || 'Failed to save ingredient', 'error');
  }
}

async function deleteIngredient(id, name) {
  if (!confirm(`Delete ingredient "${name}"?`)) return;
  const res = await api('delete_ingredient', { id }, 'POST');
  if (res.success) {
    toast(res.message || 'Ingredient deleted', 'success');
    loadIngredients();
  } else {
    toast(res.message || 'Failed to delete ingredient', 'error');
  }
}

async function deleteProduct(id, name) {
  if (!confirm(`Delete product "${name}"?`)) return;
  const res = await api('delete_product', { id }, 'POST');
  if (res.success) {
    toast(res.message || 'Product deleted', 'success');
    loadInventory();
  } else {
    toast(res.message || 'Failed to delete product', 'error');
  }
}

function openAdjustStock(id, name, current) {
  document.getElementById('adjust-product-id').value = id;
  document.getElementById('adjust-product-name').textContent = name;
  document.getElementById('adjust-current-stock').textContent = current;
  document.getElementById('adjust-qty').value = '';
  document.getElementById('adjust-type').value = 'restock';
  document.getElementById('adjust-notes').value = '';
  openModal('adjust-modal');
}

async function saveAdjustment() {
  const id = document.getElementById('adjust-product-id').value;
  const type = document.getElementById('adjust-type').value;
  let qty = parseInt(document.getElementById('adjust-qty').value);
  if (isNaN(qty) || qty === 0) { toast('Enter a valid quantity', 'error'); return; }
  if (type === 'sale' || type === 'waste') qty = -Math.abs(qty);
  const res = await api('adjust_stock', {
    product_id: id, quantity: qty, type,
    notes: document.getElementById('adjust-notes').value
  }, 'POST');
  if (res.success) {
    toast(`Stock updated → ${res.new_stock}`, 'success');
    closeModal('adjust-modal');
    loadInventory();
  } else {
    toast(res.message || 'Adjustment failed', 'error');
  }
}

// ════════════════════════════════════════════
// ORDERS PAGE
// ════════════════════════════════════════════
async function loadOrders() {
  const date = document.getElementById('orders-date')?.value || new Date().toISOString().split('T')[0];
  const status = document.getElementById('orders-status')?.value || '';
  const res = await api('get_orders', { date, status });
  if (!res.success) return;
  renderOrdersTable(res.data);
}

function renderOrdersTable(data) {
  const tbody = document.getElementById('orders-tbody');
  if (!data.length) {
    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted" style="padding:40px">No orders found</td></tr>`;
    return;
  }
  tbody.innerHTML = data.map(o => {
    const statusMap = { completed: 'badge-green', cancelled: 'badge-red', pending: 'badge-caramel', refunded: 'badge-blue' };
    const time = new Date(o.created_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    return `<tr>
      <td><strong>${o.order_number}</strong></td>
      <td>${time}</td>
      <td>${o.customer_name}<div class="text-muted" style="font-size:11px">${getOrderTypeLabel(o.order_type)}</div></td>
      <td>${o.item_count} item${o.item_count != 1 ? 's' : ''}</td>
      <td><strong>${CURRENCY}${parseFloat(o.total).toFixed(2)}</strong></td>
      <td><span class="badge ${statusMap[o.status] || 'badge-gray'}">${o.status}</span></td>
      <td>
        <div class="d-flex gap-8">
          <button class="btn btn-sm btn-secondary" onclick="viewOrderDetail(${o.id})">View</button>
          ${o.status === 'completed' ? `<button class="btn btn-sm btn-danger" onclick="voidOrder(${o.id})">Void</button>` : ''}
        </div>
      </td>
    </tr>`;
  }).join('');
}

async function viewOrderDetail(id) {
  const res = await api('get_order_detail', { id });
  if (!res.success) return;
  const { order, items } = res;
  const time = new Date(order.created_at).toLocaleString();
  const cashierName = order.cashier_name || 'Unknown';
  const paymentMethod = getPaymentMethodLabel(order.payment_method);
  
  document.getElementById('order-detail-content').innerHTML = `
    <div style="margin-bottom:16px">
      <div class="d-flex align-center" style="gap:12px;margin-bottom:8px">
        <strong style="font-size:16px">${order.order_number}</strong>
        <span class="badge ${order.status === 'completed' ? 'badge-green' : 'badge-red'}">${order.status}</span>
      </div>
      <div style="font-size:14px;margin-bottom:4px"><strong>Cashier:</strong> ${cashierName}</div>
      <div style="font-size:14px;margin-bottom:4px"><strong>Payment Method:</strong> ${paymentMethod}</div>
      <div class="text-muted">${time} &bull; ${order.customer_name} &bull; ${getOrderTypeLabel(order.order_type)}</div>
    </div>
    <table style="width:100%;font-size:13.5px;border-collapse:collapse">
      <thead><tr style="background:var(--mist)">
        <th style="padding:8px 10px;text-align:left">Item</th>
        <th style="padding:8px 10px;text-align:right">Price</th>
        <th style="padding:8px 10px;text-align:right">Total</th>
      </tr></thead>
      <tbody>${items.map(i => `
        <tr style="border-bottom:1px solid var(--fog)">
          <td style="padding:8px 10px">
            <div style="font-weight:600">${i.product_name}</div>
            ${i.variant_name ? `<div style="font-size:11px;color:var(--roast)">${i.variant_name}</div>` : ''}
            <div style="font-size:12px;color:var(--roast)">${i.quantity} x ${CURRENCY}${parseFloat(i.unit_price).toFixed(2)}</div>
          </td>
          <td style="padding:8px 10px;text-align:right">${CURRENCY}${parseFloat(i.unit_price).toFixed(2)}</td>
          <td style="padding:8px 10px;text-align:right;font-weight:600">${CURRENCY}${parseFloat(i.subtotal).toFixed(2)}</td>
        </tr>`).join('')}
      </tbody>
    </table>
    <div style="margin-top:14px;border-top:1px solid var(--fog);padding-top:12px">
      <div class="cart-row"><span>Subtotal</span><span>${CURRENCY}${parseFloat(order.subtotal).toFixed(2)}</span></div>
      <div class="cart-row"><span>Discount</span><span>−${CURRENCY}${parseFloat(order.discount).toFixed(2)}</span></div>
      <div class="cart-row total"><span>Total</span><span>${CURRENCY}${parseFloat(order.total).toFixed(2)}</span></div>
      <div class="cart-row" style="margin-top:8px"><span>Paid</span><span>${CURRENCY}${parseFloat(order.amount_paid).toFixed(2)}</span></div>
      <div class="cart-row"><span>Change</span><span>${CURRENCY}${parseFloat(order.change_given).toFixed(2)}</span></div>
    </div>
    ${order.notes ? `<div style="margin-top:12px;padding:10px;background:var(--cream);border-radius:8px;font-size:13px"><strong>Notes:</strong> ${order.notes}</div>` : ''}`;
  openModal('order-detail-modal');
}

async function voidOrder(id) {
  if (!confirm('Are you sure you want to void this order? Stock will be restored.')) return;
  const res = await api('void_order', { id }, 'POST');
  if (res.success) {
    toast('Order voided', 'info');
    loadOrders();
  } else {
    toast(res.message || 'Cannot void order', 'error');
  }
}

// ── AUDIT LOGS ────────────────────────────────
async function loadAuditLogs() {
  const res = await api('get_audit_logs', { limit: 200 });
  if (!res.success) return;
  renderAuditLogs(res.data);
}

function renderAuditLogs(data) {
  const tbody = document.getElementById('audit-tbody');
  if (!tbody) return;
  if (!data.length) {
    tbody.innerHTML = `<tr><td colspan="3" class="text-center text-muted" style="padding:40px">No audit logs found</td></tr>`;
    return;
  }
  tbody.innerHTML = data.map(row => {
    const user = row.user_name || 'Unknown';
    const time = new Date(row.created_at).toLocaleString('en-US', { hour: '2-digit', minute: '2-digit', year: 'numeric', month: 'short', day: 'numeric' });
    return `<tr>
      <td><strong>${user}</strong></td>
      <td>${row.action_text}</td>
      <td>${time}</td>
    </tr>`;
  }).join('');
}

// ════════════════════════════════════════════
// DASHBOARD PAGE
// ════════════════════════════════════════════
async function loadDashboard() {
  const period = document.getElementById('dash-period')?.value || 'day';
  const res = await api('get_dashboard', { period });
  if (!res.success) return;
  const d = res.data;
  document.getElementById('dash-revenue').textContent = `${CURRENCY}${parseFloat(d.period_revenue).toFixed(2)}`;
  document.getElementById('dash-orders').textContent = d.period_sales;
  document.getElementById('dash-lowstock').textContent = d.low_stock_count;
  document.getElementById('dash-products').textContent = d.total_products;
  const periodLabel = d.period_label || 'Day';
  const revenueLabel = document.getElementById('dash-revenue-label');
  const ordersLabel = document.getElementById('dash-orders-label');
  const chartTitle = document.getElementById('dash-chart-title');
  const chartSub = document.getElementById('dash-chart-subtitle');
  const topSub = document.getElementById('dash-top-subtitle');
  if (revenueLabel) revenueLabel.textContent = `Revenue (${periodLabel})`;
  if (ordersLabel) ordersLabel.textContent = `Orders (${periodLabel})`;
  if (chartTitle) chartTitle.textContent = `${periodLabel} Revenue`;
  if (chartSub) chartSub.textContent = d.chart_subtitle || 'Selected period';
  if (topSub) topSub.textContent = periodLabel;
  renderWeeklyChart(d.chart_data || []);
  renderTopProducts(d.top_products);
  renderRecentOrders(d.recent_orders);
}

function renderWeeklyChart(data) {
  const chart = document.getElementById('weekly-chart');
  if (!data || !data.length) { chart.innerHTML = '<p class="text-muted">No data for selected period</p>'; return; }
  const max = Math.max(...data.map(d => d.revenue), 1);
  chart.innerHTML = data.map(d => {
    const revenue = parseFloat(d.revenue || 0);
    const h = Math.max(10, Math.round((revenue / max) * 160));
    const day = d.label || '';
    return `<div class="chart-bar-wrap">
      <div class="chart-bar" style="height:${h}px" title="${CURRENCY}${revenue.toFixed(0)}"></div>
      <span class="chart-bar-label">${day}</span>
    </div>`;
  }).join('');
}

function renderTopProducts(data) {
  const el = document.getElementById('top-products');
  if (!data || !data.length) { el.innerHTML = '<p class="text-muted" style="padding:16px">No sales today</p>'; return; }
  el.innerHTML = data.map((p, i) => `
    <div class="top-item">
      <div class="top-rank">${i + 1}</div>
      <div class="top-name">${p.name}</div>
      <div class="top-sold">${p.sold} sold</div>
      <div style="font-size:13px;font-weight:600">${CURRENCY}${parseFloat(p.revenue).toFixed(0)}</div>
    </div>`).join('');
}

function renderRecentOrders(data) {
  const el = document.getElementById('recent-orders');
  if (!data || !data.length) { el.innerHTML = '<p class="text-muted" style="padding:16px">No recent orders</p>'; return; }
  el.innerHTML = data.map(o => {
    const time = new Date(o.created_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    const badge = o.status === 'completed' ? 'badge-green' : o.status === 'cancelled' ? 'badge-red' : 'badge-caramel';
    return `<div class="top-item">
      <div style="flex:1">
        <div style="font-size:13px;font-weight:500">${o.order_number}</div>
        <div class="text-muted">${o.customer_name} &bull; ${time}</div>
      </div>
      <span class="badge ${badge}">${o.status}</span>
      <div style="font-size:13px;font-weight:600;min-width:70px;text-align:right">${CURRENCY}${parseFloat(o.total).toFixed(2)}</div>
    </div>`;
  }).join('');
}

// ── MODAL HELPERS ─────────────────────────────
function openModal(id) {
  document.getElementById(id).classList.add('open');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}

// Close modal on overlay click - DISABLED
// document.addEventListener('click', e => {
//   if (e.target.classList.contains('modal-overlay')) {
//     if (e.target.id === 'login-modal') return;
//     if (e.target.id === 'cash-calculator-modal') {
//       closeCashCalculator();
//       return;
//     }
//     e.target.classList.remove('open');
//   }
// });

// ════════════════════════════════════════════
// SALES REPORTS PAGE
// ════════════════════════════════════════════
let _reportData = [];

function initReportsPage() {
  // Set default date range: first of current month → today
  const today = new Date();
  const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
  const fmt = d => d.toISOString().split('T')[0];
  const fromEl = document.getElementById('report-from');
  const toEl = document.getElementById('report-to');
  if (fromEl && !fromEl.value) fromEl.value = fmt(firstDay);
  if (toEl && !toEl.value) toEl.value = fmt(today);
  // Load report data on page init
  loadReports();
}

async function loadReports() {
  const from = document.getElementById('report-from')?.value;
  const to = document.getElementById('report-to')?.value;
  const group = document.getElementById('report-group')?.value || 'day';
  if (!from || !to) { toast('Select a date range', 'error'); return; }

  const wrap = document.getElementById('report-table-wrap');
  if (wrap) wrap.innerHTML = `<div style="padding:40px;text-align:center"><div class="spinner" style="margin:0 auto"></div></div>`;

  const res = await api('get_sales_report', { from, to, group });
  if (!res.success) { toast(res.message || 'Failed to load report', 'error'); return; }

  _reportData = res.data || [];
  const summary = res.summary || {};

  // Update summary stats
  const summaryEl = document.getElementById('report-summary');
  if (summaryEl) summaryEl.style.display = 'grid';
  setEl('report-revenue', `${CURRENCY}${parseFloat(summary.revenue || 0).toFixed(2)}`);
  setEl('report-orders', summary.orders || 0);
  setEl('report-items', summary.items_sold || 0);
  const avg = summary.orders > 0 ? (summary.revenue / summary.orders) : 0;
  setEl('report-avg', `${CURRENCY}${parseFloat(avg).toFixed(2)}`);

  // Update table title
  const titles = { day: 'Daily Breakdown', product: 'Sales by Product', category: 'Sales by Category' };
  setEl('report-table-title', titles[group] || 'Report');

  // Render table
  if (!_reportData.length) {
    if (wrap) wrap.innerHTML = `<div style="padding:40px;text-align:center;color:var(--roast);opacity:.5">No sales data for this period</div>`;
    return;
  }

  let headers = '', rows = '';
  if (group === 'day') {
    headers = '<tr><th>Date</th><th>Orders</th><th>Items Sold</th><th>Revenue</th><th>Avg. Order</th></tr>';
    rows = _reportData.map(r => `<tr>
      <td>${r.label}</td>
      <td>${r.orders}</td>
      <td>${r.items_sold}</td>
      <td><strong>${CURRENCY}${parseFloat(r.revenue).toFixed(2)}</strong></td>
      <td>${CURRENCY}${r.orders > 0 ? (parseFloat(r.revenue)/r.orders).toFixed(2) : '0.00'}</td>
    </tr>`).join('');
  } else if (group === 'product') {
    headers = '<tr><th>Product</th><th>Category</th><th>Qty Sold</th><th>Revenue</th></tr>';
    rows = _reportData.map(r => `<tr>
      <td><strong>${r.label}</strong></td>
      <td>${r.category || '—'}</td>
      <td>${r.items_sold}</td>
      <td><strong>${CURRENCY}${parseFloat(r.revenue).toFixed(2)}</strong></td>
    </tr>`).join('');
  } else {
    headers = '<tr><th>Category</th><th>Orders</th><th>Qty Sold</th><th>Revenue</th></tr>';
    rows = _reportData.map(r => `<tr>
      <td><strong>${r.label}</strong></td>
      <td>${r.orders}</td>
      <td>${r.items_sold}</td>
      <td><strong>${CURRENCY}${parseFloat(r.revenue).toFixed(2)}</strong></td>
    </tr>`).join('');
  }

  if (wrap) wrap.innerHTML = `<table><thead>${headers}</thead><tbody>${rows}</tbody></table>`;
  const exportBtn = document.getElementById('export-csv-btn');
  if (exportBtn) exportBtn.style.display = 'inline-flex';
}

function setEl(id, val) {
  const el = document.getElementById(id);
  if (el) el.textContent = val;
}

function exportReportCSV() {
  if (!_reportData.length) return;
  const group = document.getElementById('report-group')?.value || 'day';
  let csv = '';
  if (group === 'day') {
    csv = 'Date,Orders,Items Sold,Revenue,Avg Order\n' +
      _reportData.map(r => `"${r.label}",${r.orders},${r.items_sold},${parseFloat(r.revenue).toFixed(2)},${r.orders>0?(parseFloat(r.revenue)/r.orders).toFixed(2):'0.00'}`).join('\n');
  } else if (group === 'product') {
    csv = 'Product,Category,Qty Sold,Revenue\n' +
      _reportData.map(r => `"${r.label}","${r.category||''}",${r.items_sold},${parseFloat(r.revenue).toFixed(2)}`).join('\n');
  } else {
    csv = 'Category,Orders,Qty Sold,Revenue\n' +
      _reportData.map(r => `"${r.label}",${r.orders},${r.items_sold},${parseFloat(r.revenue).toFixed(2)}`).join('\n');
  }
  const from = document.getElementById('report-from')?.value || '';
  const to = document.getElementById('report-to')?.value || '';
  const blob = new Blob([csv], { type: 'text/csv' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `sales_report_${from}_to_${to}.csv`;
  a.click();
}
