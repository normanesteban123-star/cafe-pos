<div class="page" id="page-inventory">
  <div class="page-header">
    <div>
      <div class="page-title">Product</div>
      <div class="page-subtitle">Manage products and stock levels</div>
    </div>
    <button class="btn btn-primary" onclick="openAddProduct()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Product
    </button>
  </div>
  <div class="page-body">
    <div class="inv-toolbar" style="margin-bottom:18px">
      <div class="search-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="text" class="form-control" id="inv-search" placeholder="Search products..." oninput="searchInventory()">
      </div>
      <select class="form-control" id="inv-cat-filter" style="width:180px" onchange="searchInventory()">
        <option value="">All Categories</option>
      </select>
      <div class="spacer"></div>
      <button class="btn btn-secondary" onclick="loadInventory()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="14" height="14"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
        Refresh
      </button>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Product</th><th>Category</th><th>Price</th><th>Cost</th>
            <th>Stock</th><th>Threshold</th><th>Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody id="inv-tbody">
          <tr><td colspan="8" class="text-center" style="padding:40px"><div class="spinner" style="margin:0 auto"></div></td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
