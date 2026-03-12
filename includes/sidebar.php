<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-mark">B</div>
    <span class="logo-text">Brewed &amp; Co.</span>
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
    <div class="nav-item" data-page="accounts">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/></svg>
      <span>Accounts</span>
    </div>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-time" id="sidebar-time"></div>
    <div class="sidebar-time" id="sidebar-user"></div>
    <button class="btn btn-ghost btn-sm" id="logout-btn" onclick="logout()" style="margin:8px 12px 0 12px;display:none;width:calc(100% - 24px);justify-content:center;color:#fff;border:1px solid rgba(255,255,255,0.15)">Logout</button>
  </div>
</aside>
