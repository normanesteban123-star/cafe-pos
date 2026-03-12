<?php
require_once __DIR__ . '/config.php';
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ASCOT HOSTEL COFFEE — POS System</title>
  <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>
<body>

<!-- ══════════════════════════════════════════ -->
<!-- SIDEBAR                                    -->
<!-- ══════════════════════════════════════════ -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-mark">B</div>
    <div class="logo-text-wrap">
      <span class="logo-text">ASCOT HOSTEL.&amp;</span>
      <span class="logo-welcome" id="sidebar-welcome">Welcome</span>
    </div>
  </div>

  <nav class="nav-section">
    <div class="nav-label">POS</div>
    <div class="nav-item active" data-page="pos">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
      <span>Point of Sale</span>
    </div>

    <div class="nav-label" style="margin-top:20px">MANAGE</div>
    <div class="nav-item" data-page="dashboard">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      <span>Dashboard</span>
    </div>
    <div class="nav-item" data-page="inventory">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
      <span>Product</span>
    </div>
    <div class="nav-item" data-page="categories">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
      <span>Categories</span>
    </div>
    <div class="nav-item" data-page="ingredients">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 3h6v4H9z"/><path d="M8 7h8l2 14H6z"/><path d="M10 11v6M14 11v6"/></svg>
      <span>Inventory</span>
    </div>
    <div class="nav-item" data-page="orders">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      <span>Orders</span>
    </div>
    <div class="nav-item" data-page="reports">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <span>Sales Reports</span>
    </div>
    <div class="nav-item" data-page="audit">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 3h6v4H9z"/><path d="M8 7h8l2 14H6z"/><path d="M10 11h4"/><path d="M10 15h4"/></svg>
      <span>Audit Logs</span>
    </div>
    <div class="nav-item" data-page="accounts">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/></svg>
      <span>Accounts</span>
    </div>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-time" id="sidebar-time"></div>
    <div class="sidebar-time" id="sidebar-user"></div>
    <button class="btn btn-ghost btn-sm" id="logout-btn" onclick="logout()" style="margin:8px 12px 0 12px;width:calc(100% - 24px);justify-content:center;color:#fff;border:1px solid rgba(255,255,255,0.15)">Logout</button>
  </div>
</aside>

<!-- ══════════════════════════════════════════ -->
<!-- MAIN CONTENT                               -->
<!-- ══════════════════════════════════════════ -->
<main class="main-content">

  <!-- ─ POS PAGE ──────────────────────────── -->
  <div class="page active" id="page-pos">
    <div class="pos-layout">

      <!-- Menu Side -->
      <div class="pos-menu">
        <div class="page-header" style="position:static;padding:0 0 20px 0;background:transparent">
          <div>
            <div class="page-title">Point of Sale</div>
            <div class="page-subtitle">Select items to add to cart</div>
          </div>
          <div class="search-box">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" class="form-control" id="pos-search" placeholder="Search menu..." oninput="loadMenuProducts(this.value)" onkeydown="if(event.key==='Enter') loadMenuProducts(this.value)">
          </div>
        </div>

        <!-- Quick Add: Most Popular Items -->
        <div class="quick-add-section" id="quick-add-section" style="display:none">
          <div class="quick-add-label">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="14" height="14" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            Popular
          </div>
          <div class="quick-add-buttons" id="quick-add-buttons"></div>
        </div>

        <div class="category-tabs" id="category-tabs"></div>
        <div class="menu-grid" id="menu-grid">
          <div style="grid-column:1/-1;display:flex;justify-content:center;padding:40px"><div class="spinner"></div></div>
        </div>
      </div>

      <!-- Cart Side -->
      <div class="pos-cart">
        <div class="cart-header">
          <div class="cart-title">Current Order</div>
          <div class="d-flex align-center" style="gap:8px">
            <span class="text-muted" id="cart-count">Empty</span>
            <button class="btn btn-ghost btn-sm" onclick="clearCart()">Clear</button>
          </div>
        </div>

        <div class="cart-items" id="cart-items">
          <div class="cart-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
            <p>Cart is empty</p>
          </div>
        </div>

        <div class="cart-footer">
          <div style="margin-bottom:12px">
            <label class="form-label">Discount (<?= CURRENCY ?>)</label>
            <input type="number" class="form-control" id="cart-discount" min="0" step="0.01" value="0" oninput="updateCartTotals()" onkeydown="if(event.key==='Enter') updateCartTotals()" placeholder="0.00">
          </div>

          <div class="cart-summary">
            <div class="cart-row"><span>Subtotal</span><span id="cart-subtotal"><?= CURRENCY ?>0.00</span></div>
            <div class="cart-row"><span>Discount</span><span id="cart-discount-display">−<?= CURRENCY ?>0.00</span></div>
            <div class="cart-row total"><span>Total</span><span id="cart-total"><?= CURRENCY ?>0.00</span></div>
          </div>

          <button class="checkout-btn" id="checkout-btn" onclick="openCheckout()" disabled>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            Proceed to Checkout
          </button>
        </div>
      </div>

    </div>
  </div>

  <!-- ─ INVENTORY PAGE ────────────────────── -->
  <?php require __DIR__ . '/includes/pages/product.php'; ?>

  <!-- ─ ORDERS PAGE ───────────────────────── -->
  <div class="page" id="page-ingredients">
    <div class="page-header">
      <div>
        <div class="page-title">Inventory</div>
        <div class="page-subtitle">Manage ingredient stock and coffee usage</div>
      </div>
      <button class="btn btn-primary" onclick="openAddIngredient()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Ingredient
      </button>
    </div>
    <div class="page-body">
      <div class="inv-toolbar" style="margin-bottom:18px">
        <div class="spacer"></div>
        <button class="btn btn-secondary" onclick="loadIngredients()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="14" height="14"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
          Refresh
        </button>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Name</th><th>Unit</th><th>Stock</th><th>Low Stock Alert</th><th>Used In</th><th>Actions</th></tr>
          </thead>
          <tbody id="ing-tbody">
            <tr><td colspan="6" class="text-center" style="padding:40px"><div class="spinner" style="margin:0 auto"></div></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="page" id="page-accounts">
    <div class="page-header">
      <div>
        <div class="page-title">Accounts</div>
        <div class="page-subtitle">Administrator can create staff accounts for POS access</div>
      </div>
    </div>
    <div class="page-body">
      <div class="card" style="max-width:560px">
        <div class="card-header">
          <span class="card-title">Create Staff Account</span>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" class="form-control" id="staff-fullname" placeholder="e.g. John Dela Cruz">
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Username *</label>
              <input type="text" class="form-control" id="staff-username" placeholder="staff.john">
            </div>
            <div class="form-group">
              <label class="form-label">Password *</label>
              <input type="password" class="form-control" id="staff-password" placeholder="Set password">
            </div>
          </div>
          <div class="d-flex" style="justify-content:flex-end">
            <button class="btn btn-primary" onclick="createStaffAccount()">Create Account</button>
          </div>
        </div>
      </div>
      <div class="card" style="margin-top:18px">
        <div class="card-header">
          <span class="card-title">Account List</span>
        </div>
        <div class="card-body" style="padding:0">
          <div class="table-wrap" style="border:0;border-radius:0">
            <table>
              <thead>
                <tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Actions</th></tr>
              </thead>
              <tbody id="accounts-tbody">
                <tr><td colspan="5" class="text-center" style="padding:30px"><div class="spinner" style="margin:0 auto"></div></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="page" id="page-categories">
    <div class="page-header">
      <div>
        <div class="page-title">Categories</div>
        <div class="page-subtitle">Add, rename, and delete menu categories</div>
      </div>
      <button class="btn btn-primary" onclick="openCategoryModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Category
      </button>
    </div>
    <div class="page-body">
      <div class="inv-toolbar" style="margin-bottom:18px">
        <div class="spacer"></div>
        <button class="btn btn-secondary" onclick="loadCategoryManager()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="14" height="14"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
          Refresh
        </button>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Name</th><th>Products</th><th>Actions</th></tr>
          </thead>
          <tbody id="cat-tbody">
            <tr><td colspan="3" class="text-center" style="padding:40px"><div class="spinner" style="margin:0 auto"></div></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="page" id="page-orders">
    <div class="page-header">
      <div>
        <div class="page-title">Orders</div>
        <div class="page-subtitle">View and manage transactions</div>
      </div>
      <div class="order-filter">
        <input type="date" class="form-control" id="orders-date" value="<?= date('Y-m-d') ?>" onchange="loadOrders()" style="width:160px">
        <select class="form-control" id="orders-status" onchange="loadOrders()" style="width:140px">
          <option value="">All Status</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
          <option value="pending">Pending</option>
        </select>
        <button class="btn btn-secondary" onclick="loadOrders()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="14" height="14"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
          Refresh
        </button>
      </div>
    </div>
    <div class="page-body">
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Order #</th><th>Time</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody id="orders-tbody">
            <tr><td colspan="7" class="text-center" style="padding:40px"><div class="spinner" style="margin:0 auto"></div></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ─ DASHBOARD PAGE ────────────────────── -->
  <div class="page" id="page-audit">
    <div class="page-header">
      <div>
        <div class="page-title">Audit Logs</div>
        <div class="page-subtitle">Track administrative actions</div>
      </div>
      <button class="btn btn-secondary" onclick="loadAuditLogs()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="14" height="14"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
        Refresh
      </button>
    </div>
    <div class="page-body">
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>User</th><th>Action</th><th>Time</th></tr>
          </thead>
          <tbody id="audit-tbody">
            <tr><td colspan="3" class="text-center" style="padding:40px"><div class="spinner" style="margin:0 auto"></div></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="page" id="page-dashboard">
    <div class="page-header">
      <div>
        <div class="page-title">Dashboard</div>
        <div class="page-subtitle">Revenue and sales analytics</div>
      </div>
      <div class="order-filter">
        <select class="form-control" id="dash-period" onchange="loadDashboard()" style="width:140px">
          <option value="day">Day</option>
          <option value="week">Week</option>
          <option value="month">Month</option>
          <option value="year">Year</option>
        </select>
        <button class="btn btn-secondary" onclick="loadDashboard()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="14" height="14"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
          Refresh
        </button>
      </div>
    </div>
    <div class="page-body">
      <div class="stat-grid">
        <div class="stat-card revenue">
          <div class="stat-label" id="dash-revenue-label">Revenue (Day)</div>
          <div class="stat-value" id="dash-revenue">—</div>
          <div class="stat-sub">Completed orders</div>
        </div>
        <div class="stat-card orders">
          <div class="stat-label" id="dash-orders-label">Orders (Day)</div>
          <div class="stat-value" id="dash-orders">—</div>
          <div class="stat-sub">Transactions</div>
        </div>
        <div class="stat-card stock">
          <div class="stat-label">Low Stock</div>
          <div class="stat-value" id="dash-lowstock">—</div>
          <div class="stat-sub">Items need restocking</div>
        </div>
        <div class="stat-card products">
          <div class="stat-label">Total Products</div>
          <div class="stat-value" id="dash-products">—</div>
          <div class="stat-sub">Active items</div>
        </div>
      </div>

      <div class="dash-grid">
        <div class="card">
          <div class="card-header">
            <span class="card-title" id="dash-chart-title">Revenue Trend</span>
            <span class="text-muted" id="dash-chart-subtitle">Selected period</span>
          </div>
          <div class="card-body">
            <div class="chart-placeholder" id="weekly-chart" style="min-height:180px">
              <div class="spinner"></div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <span class="card-title">Top Products</span>
            <span class="text-muted" id="dash-top-subtitle">Selected period</span>
          </div>
          <div class="card-body" style="padding:10px 18px">
            <div class="top-list" id="top-products"><div class="spinner" style="margin:20px auto"></div></div>
          </div>
        </div>
      </div>

      <div class="card" style="margin-top:18px">
        <div class="card-header">
          <span class="card-title">Recent Orders</span>
          <button class="btn btn-ghost btn-sm" onclick="navigate('orders')">View All</button>
        </div>
        <div class="card-body" style="padding:8px 18px">
          <div id="recent-orders"><div class="spinner" style="margin:20px auto"></div></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ─ REPORTS PAGE ─────────────────────── -->
  <div class="page" id="page-reports">
    <div class="page-header">
      <div>
        <div class="page-title">Sales Reports</div>
        <div class="page-subtitle">Revenue breakdown by date range, product, and category</div>
      </div>
      <div class="order-filter">
        <input type="date" class="form-control" id="report-from" style="width:155px">
        <span style="color:var(--roast);font-size:13px">to</span>
        <input type="date" class="form-control" id="report-to" style="width:155px">
        <select class="form-control" id="report-group" style="width:140px">
          <option value="day">By Day</option>
          <option value="product">By Product</option>
          <option value="category">By Category</option>
        </select>
        <button class="btn btn-primary" onclick="loadReports()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="14" height="14"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
          Generate
        </button>
        <button class="btn btn-secondary" onclick="exportReportCSV()" id="export-csv-btn" style="display:none">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="14" height="14"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Export CSV
        </button>
      </div>
    </div>
    <div class="page-body">
      <div class="stat-grid" id="report-summary" style="display:none">
        <div class="stat-card revenue">
          <div class="stat-label">Total Revenue</div>
          <div class="stat-value" id="report-revenue">—</div>
          <div class="stat-sub">Completed orders</div>
        </div>
        <div class="stat-card orders">
          <div class="stat-label">Total Orders</div>
          <div class="stat-value" id="report-orders">—</div>
          <div class="stat-sub">Transactions</div>
        </div>
        <div class="stat-card products">
          <div class="stat-label">Items Sold</div>
          <div class="stat-value" id="report-items">—</div>
          <div class="stat-sub">Units sold</div>
        </div>
        <div class="stat-card stock">
          <div class="stat-label">Avg. Order Value</div>
          <div class="stat-value" id="report-avg">—</div>
          <div class="stat-sub">Per transaction</div>
        </div>
      </div>
      <div class="card" style="margin-top:18px" id="report-table-card">
        <div class="card-header">
          <span class="card-title" id="report-table-title">Report</span>
        </div>
        <div class="card-body" style="padding:0">
          <div class="table-wrap" style="border:0;border-radius:0" id="report-table-wrap">
            <div style="padding:40px;text-align:center;color:var(--roast);opacity:.5">Select a date range and click Generate</div>
          </div>
        </div>
      </div>
    </div>
  </div>

</main>

<!-- ══════════════════════════════════════════ -->
<!-- MODALS                                     -->
<!-- ══════════════════════════════════════════ -->

<!-- Checkout Modal -->
<div class="modal-overlay" id="checkout-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Checkout</span>
      <button class="modal-close" onclick="closeModal('checkout-modal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Customer Name</label>
        <input type="text" class="form-control" id="checkout-customer" placeholder="Walk-in" onkeydown="if(event.key==='Enter') document.getElementById('checkout-order-type').focus()">
      </div>
      <div class="form-group">
        <label class="form-label">Order Type</label>
        <select class="form-control" id="checkout-order-type">
          <option value="dine_in">Dine In</option>
          <option value="take_out">Take Out</option>
        </select>
      </div>
        <div class="form-row">
          <div class="form-group mb-0">
            <label class="form-label">Payment Method</label>
            <select class="form-control" id="checkout-payment">
              <option value="cash">Cash</option>
              <option value="card">Credit/Debit Card</option>
              <option value="gcash">GCash</option>
              <option value="maya">Maya</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="qrph">QRPh</option>
            </select>
          </div>
        <div class="form-group mb-0">
          <label class="form-label">Order Total</label>
          <input type="text" class="form-control" id="checkout-total-field" readonly>
        </div>
      </div>
      <div style="background:var(--cream);border-radius:var(--radius-md);padding:16px;margin:16px 0">
        <div class="cart-row"><span>Order Total</span><strong id="checkout-total-display" style="font-size:18px;color:var(--espresso)"></strong></div>
      </div>
      <div class="form-group">
        <label class="form-label">Notes (optional)</label>
        <textarea class="form-control" id="checkout-notes" placeholder="Special instructions..." onkeydown="if(event.key==='Enter' && !event.shiftKey) { event.preventDefault(); placeOrder(); }"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('checkout-modal')">Cancel</button>
      <button class="btn btn-primary btn-lg" id="place-order-btn" onclick="placeOrder()">Confirm &amp; Pay</button>
    </div>
  </div>
</div>

<!-- Cash Calculator Modal -->
<div class="modal-overlay" id="cash-calculator-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Cash Calculator</span>
      <button class="modal-close" onclick="closeCashCalculator()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Amount Received</label>
        <input type="text" class="form-control" id="cash-amount-paid" inputmode="decimal" placeholder="0.00" oninput="handleCashInput()" onkeydown="if(event.key==='Enter') completeCashOrder()">
      </div>
      <div class="cash-helper-row">
        <button type="button" class="btn btn-secondary btn-sm" onclick="setCashTender('exact')">Exact Cash</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="setCashTender(50)">Total +50</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="setCashTender(100)">Total +100</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="setCashTender(200)">Total +200</button>
      </div>
      <div class="cash-calculator">
        <button type="button" class="cash-key" onclick="cashCalculatorKey('7')">7</button>
        <button type="button" class="cash-key" onclick="cashCalculatorKey('8')">8</button>
        <button type="button" class="cash-key" onclick="cashCalculatorKey('9')">9</button>
        <button type="button" class="cash-key action" onclick="cashCalculatorBackspace()">⌫</button>
        <button type="button" class="cash-key" onclick="cashCalculatorKey('4')">4</button>
        <button type="button" class="cash-key" onclick="cashCalculatorKey('5')">5</button>
        <button type="button" class="cash-key" onclick="cashCalculatorKey('6')">6</button>
        <button type="button" class="cash-key action" onclick="cashCalculatorClear()">C</button>
        <button type="button" class="cash-key" onclick="cashCalculatorKey('1')">1</button>
        <button type="button" class="cash-key" onclick="cashCalculatorKey('2')">2</button>
        <button type="button" class="cash-key" onclick="cashCalculatorKey('3')">3</button>
        <button type="button" class="cash-key action" onclick="setCashTender(500)">+500</button>
        <button type="button" class="cash-key" onclick="cashCalculatorKey('0')">0</button>
        <button type="button" class="cash-key" onclick="cashCalculatorKey('00')">00</button>
        <button type="button" class="cash-key" onclick="cashCalculatorKey('.')">.</button>
        <button type="button" class="cash-key action" onclick="setCashTender(1000)">+1000</button>
      </div>
      <div style="background:var(--cream);border-radius:var(--radius-md);padding:16px">
        <div class="cart-row"><span>Order Total</span><strong id="cash-total-display" style="font-size:18px;color:var(--espresso)"></strong></div>
        <div class="cart-row" style="margin-top:8px"><span>Change</span><strong id="cash-change" style="color:var(--green)">₱0.00</strong></div>
        <div id="cash-shortfall" style="display:none;margin-top:6px;font-size:12px;color:var(--red);font-weight:500"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeCashCalculator()">Back</button>
      <button class="btn btn-primary btn-lg" id="cash-complete-btn" onclick="completeCashOrder()">Complete Payment</button>
    </div>
  </div>
</div>

<!-- Receipt Modal -->
<div class="modal-overlay" id="receipt-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Receipt</span>
      <button class="modal-close" onclick="closeModal('receipt-modal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <div id="receipt-content"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="window.print()">Print</button>
      <button class="btn btn-primary" onclick="closeModal('receipt-modal')">Done</button>
    </div>
  </div>
</div>

<!-- Product Modal -->
<div class="modal-overlay" id="product-modal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <span class="modal-title" id="product-modal-title">Add Product</span>
      <button class="modal-close" onclick="closeModal('product-modal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="product-id">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Product Name *</label>
          <input type="text" class="form-control" id="product-name" placeholder="e.g. Iced Latte">
        </div>
        <div class="form-group">
          <label class="form-label">Category</label>
          <select class="form-control" id="product-category"></select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-control" id="product-desc" placeholder="Brief description..."></textarea>
      </div>
      <div class="form-row-3">
        <div class="form-group mb-0">
          <label class="form-label">Selling Price (<?= CURRENCY ?>)</label>
          <input type="number" class="form-control" id="product-price" min="0" step="0.01" placeholder="0.00">
        </div>
        <div class="form-group mb-0">
          <label class="form-label">Unit</label>
          <select class="form-control" id="product-unit">
            <option>pcs</option><option>cup</option><option>slice</option>
            <option>bottle</option><option>bag</option><option>box</option>
          </select>
        </div>
      </div>
      <input type="hidden" id="product-cost" value="0">
      <div class="form-group" style="margin-top:14px">
        <label class="form-label">Size (base / fallback)</label>
        <select class="form-control" id="product-size">
          <option>Regular</option>
          <option>Small</option>
          <option>Medium</option>
          <option>Large</option>
        </select>
      </div>
      <input type="hidden" id="product-threshold" value="0">

      <div class="form-group" style="margin-top:14px">
        <label class="form-label">Product Photo (attach file)</label>
        <input type="file" class="form-control" id="product-image-file" accept="image/*">
        <div style="margin-top:10px">
          <img id="product-image-preview" src="" alt="Product preview" style="display:none;max-width:140px;max-height:140px;border:1px solid var(--fog);border-radius:10px;object-fit:cover">
        </div>
      </div>
      <div class="form-group" style="margin-top:14px">
        <label class="form-label">Ingredients per 1 unit</label>
        <div id="product-ingredients-grid" class="form-row"></div>
        <div class="text-muted" id="ingredient-base-size-hint" style="margin-top:6px">Base size ingredients apply by default.</div>
        <div class="text-muted" style="margin-top:4px">For size variants, leave blank to use base quantities.</div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('product-modal')">Cancel</button>
      <button class="btn btn-primary" onclick="saveProduct()">Save Product</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="account-edit-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Account</span>
      <button class="modal-close" onclick="closeModal('account-edit-modal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="edit-user-id">
      <div class="form-group">
        <label class="form-label">Full Name *</label>
        <input type="text" class="form-control" id="edit-user-fullname">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Username *</label>
          <input type="text" class="form-control" id="edit-user-username">
        </div>
        <div class="form-group">
          <label class="form-label">Role</label>
          <select class="form-control" id="edit-user-role">
            <option value="admin">admin</option>
            <option value="staff">staff</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Status</label>
          <select class="form-control" id="edit-user-active">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">New Password (optional)</label>
          <input type="password" class="form-control" id="edit-user-password" placeholder="Leave blank to keep current">
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('account-edit-modal')">Cancel</button>
      <button class="btn btn-primary" onclick="saveAccountEdit()">Save Changes</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="category-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title" id="category-modal-title">Add Category</span>
      <button class="modal-close" onclick="closeModal('category-modal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="category-id">
      <div class="form-group mb-0">
        <label class="form-label">Category Name *</label>
        <input type="text" class="form-control" id="category-name" placeholder="e.g. Desserts">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('category-modal')">Cancel</button>
      <button class="btn btn-primary" onclick="saveCategory()">Save Category</button>
    </div>
  </div>
</div>

<!-- Ingredient Modal -->
<div class="modal-overlay" id="ingredient-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title" id="ingredient-modal-title">Add Ingredient</span>
      <button class="modal-close" onclick="closeModal('ingredient-modal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="ingredient-id">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Ingredient Name *</label>
          <input type="text" class="form-control" id="ingredient-name" placeholder="e.g. Chocolate Syrup">
        </div>
        <div class="form-group">
          <label class="form-label">Unit</label>
          <select class="form-control" id="ingredient-unit">
            <option value="g">g</option>
            <option value="kg">kg</option>
            <option value="ml">ml</option>
            <option value="l">l</option>
            <option value="pcs">pcs</option>
            <option value="cup">cup</option>
            <option value="bottle">bottle</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group mb-0">
          <label class="form-label">Current Stock</label>
          <input type="number" class="form-control" id="ingredient-stock" min="0" step="0.01" placeholder="0">
        </div>
        <div class="form-group mb-0">
          <label class="form-label">Low Stock Alert</label>
          <input type="number" class="form-control" id="ingredient-threshold" min="0" step="0.01" placeholder="0">
        </div>
      </div>
      <div class="form-group" style="margin-top:14px">
        <label class="form-label">Use per Coffee Cup (optional)</label>
        <input type="number" class="form-control" id="ingredient-coffee-qty" min="0" step="0.01" placeholder="e.g. 10">
        <div class="text-muted" style="margin-top:6px">If set, this ingredient will be deducted for all active Coffee products per cup sold.</div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('ingredient-modal')">Cancel</button>
      <button class="btn btn-primary" onclick="saveIngredient()">Save Ingredient</button>
    </div>
  </div>
</div>

<!-- Adjust Stock Modal -->
<div class="modal-overlay" id="adjust-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Adjust Stock</span>
      <button class="modal-close" onclick="closeModal('adjust-modal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="adjust-product-id">
      <div style="background:var(--cream);border-radius:var(--radius-md);padding:14px;margin-bottom:16px">
        <div style="font-size:15px;font-weight:600" id="adjust-product-name"></div>
        <div class="text-muted">Current stock: <strong id="adjust-current-stock"></strong></div>
      </div>
      <div class="form-row">
        <div class="form-group mb-0">
          <label class="form-label">Adjustment Type</label>
          <select class="form-control" id="adjust-type">
            <option value="restock">Restock (+)</option>
            <option value="sale">Sale (−)</option>
            <option value="waste">Waste (−)</option>
            <option value="adjustment">Manual Adjust</option>
          </select>
        </div>
        <div class="form-group mb-0">
          <label class="form-label">Quantity</label>
          <input type="number" class="form-control" id="adjust-qty" min="1" placeholder="Enter quantity" onkeydown="if(event.key==='Enter') saveAdjustment()">
        </div>
      </div>
      <div class="form-group" style="margin-top:14px">
        <label class="form-label">Notes (optional)</label>
        <input type="text" class="form-control" id="adjust-notes" placeholder="Reason for adjustment">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('adjust-modal')">Cancel</button>
      <button class="btn btn-primary" onclick="saveAdjustment()">Save Adjustment</button>
    </div>
  </div>
</div>

<!-- Order Detail Modal -->
<div class="modal-overlay" id="order-detail-modal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <span class="modal-title">Order Detail</span>
      <button class="modal-close" onclick="closeModal('order-detail-modal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body" id="order-detail-content"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('order-detail-modal')">Close</button>
    </div>
  </div>
</div>

<!-- Variant Picker Modal -->
<div class="modal-overlay" id="variant-picker-modal">
  <div class="modal" style="max-width:380px">
    <div class="modal-header">
      <span class="modal-title" id="variant-picker-title">Choose Size</span>
      <button class="modal-close" onclick="closeModal('variant-picker-modal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <div id="variant-picker-product-info" style="display:flex;align-items:center;gap:14px;margin-bottom:18px;padding:12px;background:var(--cream);border-radius:var(--radius-md)">
        <div id="variant-picker-thumb" class="quick-add-thumb" style="width:52px;height:52px;flex-shrink:0"></div>
        <div>
          <div style="font-weight:600;color:var(--espresso)" id="variant-picker-name"></div>
          <div style="font-size:12px;color:var(--roast)" id="variant-picker-desc"></div>
        </div>
      </div>
      <div class="variant-option-list" id="variant-option-list"></div>
    </div>
  </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toast-container"></div>

<!-- Login Modal -->
<div class="modal-overlay" id="login-modal">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <span class="modal-title">Sign In</span>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Username</label>
        <input type="text" class="form-control" id="login-username" placeholder="admin or staff">
      </div>
      <div class="form-group mb-0">
        <label class="form-label">Password</label>
        <input type="password" class="form-control" id="login-password" placeholder="Enter password" onkeydown="if(event.key==='Enter') login()">
      </div>
      <div class="text-muted" style="margin-top:10px">Default accounts: admin/admin123, staff/staff123</div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary" onclick="login()" id="login-btn">Login</button>
    </div>
  </div>
</div>

<script src="app.js?v=<?= filemtime(__DIR__ . '/app.js') ?>"></script>
</body>
</html>
