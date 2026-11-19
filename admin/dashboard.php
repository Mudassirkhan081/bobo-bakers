<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/db.php";
require_login_page();

// ---- Fetch summary stats ----
$stats = $pdo->query("
    SELECT
      COUNT(*) AS total_orders,
      SUM(status = 'pending') AS pending_count,
      SUM(status = 'delivered') AS delivered_count,
      SUM(total_price) AS total_revenue
    FROM orders
")->fetch();

$leadCount = (int) $pdo->query("SELECT COUNT(*) c FROM leads WHERE status = 'new'")->fetch()['c'];

// ---- Which tab? ----
$tab = $_GET['tab'] ?? 'orders';
if (!in_array($tab, ['orders','leads'], true)) { $tab = 'orders'; }

// ---- Fetch orders (with customer, product, toppings) ----
$statusFilter = $_GET['status'] ?? 'all';
$allowedOrderStatuses = ['all','pending','confirmed','preparing','out_for_delivery','delivered','cancelled'];
if (!in_array($statusFilter, $allowedOrderStatuses, true)) { $statusFilter = 'all'; }

$sql = "SELECT o.id, o.total_price, o.base_price, o.addons_total, o.delivery_datetime,
               o.note, o.status, o.created_at,
               c.name AS customer_name, c.phone, c.address,
               p.name AS product_name
        FROM orders o
        JOIN customers c ON c.id = o.customer_id
        JOIN products  p ON p.id = o.product_id";
$params = [];
if ($statusFilter !== 'all') {
    $sql .= " WHERE o.status = :status";
    $params['status'] = $statusFilter;
}
$sql .= " ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

foreach ($orders as &$order) {
    $stmt2 = $pdo->prepare("
        SELECT t.name, t.emoji, ot.price
        FROM order_toppings ot JOIN toppings t ON t.id = ot.topping_id
        WHERE ot.order_id = :order_id
    ");
    $stmt2->execute(['order_id' => $order['id']]);
    $order['toppings'] = $stmt2->fetchAll();
}
unset($order);

// ---- Fetch leads (custom-order contact form enquiries) ----
$leadStatusFilter = $_GET['lead_status'] ?? 'all';
if (!in_array($leadStatusFilter, ['all','new','contacted','converted','closed'], true)) { $leadStatusFilter = 'all'; }
$leadSql = "SELECT * FROM leads";
$leadParams = [];
if ($leadStatusFilter !== 'all') {
    $leadSql .= " WHERE status = :status";
    $leadParams['status'] = $leadStatusFilter;
}
$leadSql .= " ORDER BY created_at DESC";
$leadStmt = $pdo->prepare($leadSql);
$leadStmt->execute($leadParams);
$leads = $leadStmt->fetchAll();

$leadStatusLabels = [
    'new'       => ['🆕 New', '#3b82f6'],
    'contacted' => ['📞 Contacted', '#f59e0b'],
    'converted' => ['✅ Converted', '#10b981'],
    'closed'    => ['❌ Closed', '#6b7280'],
];

$statusLabels = [
    'pending'          => ['🕐 Pending', '#f59e0b'],
    'confirmed'        => ['✅ Confirmed', '#3b82f6'],
    'preparing'        => ['👩‍🍳 Preparing', '#8b5cf6'],
    'out_for_delivery' => ['🚚 Out for Delivery', '#ec4899'],
    'delivered'        => ['🎉 Delivered', '#10b981'],
    'cancelled'        => ['❌ Cancelled', '#ef4444'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Admin Dashboard | Bobo Baker's</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
  body { background: #f6f3ef; color: #2d2d2d; }

  .topbar {
    background: #1a1a2e; color: #fff; padding: 16px 28px;
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;
  }
  .topbar h1 { font-size: 1.25rem; }
  .topbar .right { display: flex; align-items: center; gap: 16px; font-size: 0.9rem; }
  .topbar a { color: #ffb3ce; text-decoration: none; font-weight: 600; }

  .container { max-width: 1200px; margin: 0 auto; padding: 24px; }

  .stats-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px; margin-bottom: 28px;
  }
  .stat-card {
    background: #fff; border-radius: 14px; padding: 18px 20px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.05);
  }
  .stat-card .label { font-size: 0.8rem; color: #999; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
  .stat-card .value { font-size: 1.6rem; font-weight: 700; color: #C8104A; }

  .filter-row { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
  .filter-row a {
    padding: 8px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;
    text-decoration: none; color: #555; background: #fff; border: 1px solid #eee;
  }
  .filter-row a.active { background: #C8104A; color: #fff; border-color: #C8104A; }

  .main-tabs { display: flex; gap: 8px; margin-bottom: 22px; border-bottom: 2px solid #eee; }
  .main-tabs a {
    padding: 12px 20px; text-decoration: none; font-size: 0.92rem; font-weight: 700;
    color: #888; border-bottom: 3px solid transparent; margin-bottom: -2px; position: relative;
  }
  .main-tabs a.active { color: #C8104A; border-bottom-color: #C8104A; }
  .tab-lead-count {
    display: inline-block; background: #C8104A; color: #fff; font-size: 0.68rem; font-weight: 800;
    border-radius: 10px; padding: 1px 7px; margin-left: 6px; vertical-align: middle;
  }
  .design-cell { max-width: 260px; font-size: 0.82rem; color: #555; }

  .orders-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.05); }
  .orders-table th, .orders-table td { padding: 12px 14px; text-align: left; font-size: 0.85rem; border-bottom: 1px solid #f2f2f2; vertical-align: top; }
  .orders-table th { background: #faf7f4; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #999; }
  .orders-table tr:last-child td { border-bottom: none; }

  .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; color: #fff; }
  .toppings-list { font-size: 0.78rem; color: #888; margin-top: 4px; }

  select.status-select {
    padding: 6px 10px; border-radius: 8px; border: 1px solid #ddd; font-size: 0.8rem; font-weight: 600;
    cursor: pointer;
  }

  .empty-state { text-align: center; padding: 60px 20px; color: #999; }
  .toast {
    position: fixed; bottom: 24px; right: 24px; background: #1a1a2e; color: #fff;
    padding: 12px 20px; border-radius: 10px; font-size: 0.9rem; opacity: 0; transition: opacity 0.3s;
    pointer-events: none;
  }
  .toast.show { opacity: 1; }

  @media (max-width: 768px) {
    .orders-table thead { display: none; }
    .orders-table, .orders-table tbody, .orders-table tr, .orders-table td { display: block; width: 100%; }
    .orders-table tr { margin-bottom: 14px; border: 1px solid #f0f0f0; border-radius: 10px; padding: 8px; }
    .orders-table td { border-bottom: none; padding: 6px 10px; }
    .orders-table td::before { content: attr(data-label); display: block; font-size: 0.7rem; color: #aaa; text-transform: uppercase; margin-bottom: 2px; }
  }
</style>
</head>
<body>

<div class="topbar">
  <h1>🎂 Bobo Baker's — Admin Panel</h1>
  <div class="right">
    <span>Welcome, <?= htmlspecialchars($_SESSION['admin_name']) ?></span>
    <a href="logout.php">Logout</a>
  </div>
</div>

<div class="container">

  <div class="stats-grid">
    <div class="stat-card">
      <div class="label">Total Orders</div>
      <div class="value"><?= (int)$stats['total_orders'] ?></div>
    </div>
    <div class="stat-card">
      <div class="label">Pending Orders</div>
      <div class="value"><?= (int)$stats['pending_count'] ?></div>
    </div>
    <div class="stat-card">
      <div class="label">Delivered</div>
      <div class="value"><?= (int)$stats['delivered_count'] ?></div>
    </div>
    <div class="stat-card">
      <div class="label">Total Revenue</div>
      <div class="value">Rs. <?= number_format((float)$stats['total_revenue']) ?></div>
    </div>
    <div class="stat-card">
      <div class="label">New Enquiries</div>
      <div class="value"><?= $leadCount ?></div>
    </div>
  </div>

  <div class="main-tabs">
    <a href="?tab=orders" class="<?= $tab === 'orders' ? 'active' : '' ?>">📦 Catalog Orders</a>
    <a href="?tab=leads" class="<?= $tab === 'leads' ? 'active' : '' ?>">💬 Custom Order Enquiries<?php if ($leadCount > 0): ?><span class="tab-lead-count"><?= $leadCount ?> new</span><?php endif; ?></a>
  </div>

  <?php if ($tab === 'leads'): ?>

  <div class="filter-row">
    <a href="?tab=leads&lead_status=all" class="<?= $leadStatusFilter === 'all' ? 'active' : '' ?>">All</a>
    <?php foreach ($leadStatusLabels as $key => [$label, $color]): ?>
      <a href="?tab=leads&lead_status=<?= $key ?>" class="<?= $leadStatusFilter === $key ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($leads)): ?>
    <div class="empty-state">📭 No custom order enquiries yet.</div>
  <?php else: ?>
    <table class="orders-table">
      <thead>
        <tr>
          <th>Ref</th>
          <th>Customer</th>
          <th>Occasion / Date</th>
          <th>Flavour / Size / Budget</th>
          <th>Design Details</th>
          <th>Received</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($leads as $l): ?>
          <tr>
            <td data-label="Ref"><?= htmlspecialchars($l['order_ref'] ?? ('#' . $l['id'])) ?></td>
            <td data-label="Customer">
              <strong><?= htmlspecialchars($l['name']) ?></strong><br>
              📞 <?= htmlspecialchars($l['phone']) ?><br>
              📍 <?= htmlspecialchars($l['area']) ?>
            </td>
            <td data-label="Occasion / Date">
              <?= htmlspecialchars($l['occasion'] ?? '—') ?><br>
              <small><?= $l['required_by'] ? date('d M Y', strtotime($l['required_by'])) : '—' ?></small>
            </td>
            <td data-label="Flavour / Size / Budget">
              <?php if ($l['flavour']): ?><div class="toppings-list"><?= htmlspecialchars($l['flavour']) ?></div><?php endif; ?>
              <?php if ($l['size']): ?><div class="toppings-list"><?= htmlspecialchars($l['size']) ?></div><?php endif; ?>
              <?php if ($l['budget']): ?><div class="toppings-list"><?= htmlspecialchars($l['budget']) ?></div><?php endif; ?>
            </td>
            <td data-label="Design Details" class="design-cell">
              <?= nl2br(htmlspecialchars($l['design_details'])) ?>
              <?php if ($l['cake_message']): ?><br><em>✍️ "<?= htmlspecialchars($l['cake_message']) ?>"</em><?php endif; ?>
            </td>
            <td data-label="Received"><?= date('d M Y, h:i A', strtotime($l['created_at'])) ?></td>
            <td data-label="Status">
              <select class="lead-status-select" data-lead-id="<?= $l['id'] ?>"
                style="background:<?= $leadStatusLabels[$l['status']][1] ?>22; color:<?= $leadStatusLabels[$l['status']][1] ?>; border-color:<?= $leadStatusLabels[$l['status']][1] ?>55;">
                <?php foreach ($leadStatusLabels as $key => [$label, $color]): ?>
                  <option value="<?= $key ?>" <?= $l['status'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php else: ?>

  <div class="filter-row">
    <a href="?tab=orders&status=all" class="<?= $statusFilter === 'all' ? 'active' : '' ?>">All</a>
    <?php foreach ($statusLabels as $key => [$label, $color]): ?>
      <a href="?tab=orders&status=<?= $key ?>" class="<?= $statusFilter === $key ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($orders)): ?>
    <div class="empty-state">📭 No orders found.</div>
  <?php else: ?>
    <table class="orders-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Customer</th>
          <th>Cake</th>
          <th>Add-ons</th>
          <th>Total</th>
          <th>Delivery</th>
          <th>Placed On</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td data-label="Order #">#<?= $o['id'] ?></td>
            <td data-label="Customer">
              <strong><?= htmlspecialchars($o['customer_name']) ?></strong><br>
              📞 <?= htmlspecialchars($o['phone']) ?><br>
              📍 <?= htmlspecialchars($o['address']) ?>
              <?php if (!empty($o['note'])): ?>
                <div class="toppings-list">📝 <?= htmlspecialchars($o['note']) ?></div>
              <?php endif; ?>
            </td>
            <td data-label="Cake">
              <?= htmlspecialchars($o['product_name']) ?><br>
              <small>Rs. <?= number_format($o['base_price']) ?></small>
            </td>
            <td data-label="Add-ons">
              <?php if (!empty($o['toppings'])): ?>
                <?php foreach ($o['toppings'] as $t): ?>
                  <div class="toppings-list"><?= $t['emoji'] ?> <?= htmlspecialchars($t['name']) ?> (+Rs. <?= number_format($t['price']) ?>)</div>
                <?php endforeach; ?>
              <?php else: ?>
                <span style="color:#bbb;">—</span>
              <?php endif; ?>
            </td>
            <td data-label="Total"><strong>Rs. <?= number_format($o['total_price']) ?></strong></td>
            <td data-label="Delivery"><?= date('d M Y, h:i A', strtotime($o['delivery_datetime'])) ?></td>
            <td data-label="Placed On"><?= date('d M Y, h:i A', strtotime($o['created_at'])) ?></td>
            <td data-label="Status">
              <select class="status-select" data-order-id="<?= $o['id'] ?>"
                style="background:<?= $statusLabels[$o['status']][1] ?>22; color:<?= $statusLabels[$o['status']][1] ?>; border-color:<?= $statusLabels[$o['status']][1] ?>55;">
                <?php foreach ($statusLabels as $key => [$label, $color]): ?>
                  <option value="<?= $key ?>" <?= $o['status'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php endif; ?>

</div>

<div class="toast" id="toast"></div>

<script>
const CSRF = <?= json_encode(csrf_token()) ?>;

document.querySelectorAll('.status-select').forEach(sel => {
  sel.addEventListener('change', function() {
    const orderId = this.dataset.orderId;
    const status  = this.value;

    fetch('../api/update_order_status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ order_id: orderId, status: status, csrf_token: CSRF })
    })
    .then(res => res.json())
    .then(data => {
      const toast = document.getElementById('toast');
      if (data.success) {
        toast.textContent = `✅ Order #${orderId} updated to "${status}"`;
        // reload after short delay so badge color/filter stays correct
        setTimeout(() => window.location.reload(), 700);
      } else {
        toast.textContent = `❌ ${data.error || 'Update failed'}`;
      }
      toast.classList.add('show');
      setTimeout(() => toast.classList.remove('show'), 2500);
    })
    .catch(() => {
      const toast = document.getElementById('toast');
      toast.textContent = '❌ Network error. Please try again.';
      toast.classList.add('show');
      setTimeout(() => toast.classList.remove('show'), 2500);
    });
  });
});

document.querySelectorAll('.lead-status-select').forEach(sel => {
  sel.addEventListener('change', function() {
    const leadId = this.dataset.leadId;
    const status = this.value;

    fetch('../api/update_lead_status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ lead_id: leadId, status: status, csrf_token: CSRF })
    })
    .then(res => res.json())
    .then(data => {
      const toast = document.getElementById('toast');
      if (data.success) {
        toast.textContent = `✅ Enquiry #${leadId} updated to "${status}"`;
        setTimeout(() => window.location.reload(), 700);
      } else {
        toast.textContent = `❌ ${data.error || 'Update failed'}`;
      }
      toast.classList.add('show');
      setTimeout(() => toast.classList.remove('show'), 2500);
    })
    .catch(() => {
      const toast = document.getElementById('toast');
      toast.textContent = '❌ Network error. Please try again.';
      toast.classList.add('show');
      setTimeout(() => toast.classList.remove('show'), 2500);
    });
  });
});
</script>

</body>
</html>
