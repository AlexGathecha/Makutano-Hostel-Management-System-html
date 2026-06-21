// ── AUTH CHECK ─────────────────────────────────────────────
const user = JSON.parse(localStorage.getItem('user') || '{}');

if (!user || !localStorage.getItem('access_token') || user.role !== 'admin') {
  window.location.href = 'signin.html';
}

function getToken() {
  return localStorage.getItem('access_token');
}

document.getElementById('navbar-username').textContent = user.username || '';

// ── NAVIGATION ─────────────────────────────────────────────
function showSection(name, event) {
  document.querySelectorAll('.dash-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('section-' + name).classList.add('active');
  if (event && event.target) event.target.classList.add('active');

  if (name === 'overview')    loadOverview();
  if (name === 'rooms')       loadRooms();
  if (name === 'bookings')    loadBookings();
  if (name === 'tenants')     loadTenants();
  if (name === 'payments')    loadAdminPayments();
  if (name === 'maintenance') loadAdminMaintenance();
  if (name === 'staff')       loadStaff();
  if (name === 'reports')     loadReports();
}

function logout() {
  localStorage.clear();
  window.location.href = 'signin.html';
}

function badge(status) {
  const map = {
    paid: 'success', active: 'success', resolved: 'success', approved: 'success', available: 'success',
    pending: 'warning', open: 'warning', 'in progress': 'warning', occupied: 'info',
    overdue: 'danger', rejected: 'danger', maintenance: 'danger',
    default: 'info'
  };
  const cls = map[status?.toLowerCase()] || map.default;
  return `<span class="badge badge-${cls}">${status}</span>`;
}

async function api(url, method, body) {
  method = method || 'GET';
  const opts = {
    method,
    headers: {
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + getToken(),
    },
  };
  if (body) opts.body = JSON.stringify(body);
  const res = await fetch(url, opts);
  return res.json();
}

// ── OVERVIEW ───────────────────────────────────────────────
async function loadOverview() {
  try {
    const rooms = await api('php/admin_rooms.php?summary=1');
    document.getElementById('ov-total-rooms').textContent    = rooms.total ?? '0';
    document.getElementById('ov-occupied-rooms').textContent = rooms.occupied ?? '0';

    const bookings = await api('php/admin_bookings.php?summary=1');
    document.getElementById('ov-pending-bookings').textContent = bookings.pending_count ?? '0';

    const tenants = await api('php/admin_tenants.php?summary=1');
    document.getElementById('ov-total-tenants').textContent = tenants.total ?? '0';

    const payments = await api('php/admin_payments.php?summary=1');
    document.getElementById('ov-total-collected').textContent = 'KES ' + (payments.total_collected ?? '0');
    document.getElementById('ov-total-arrears').textContent   = 'KES ' + (payments.total_arrears ?? '0');

    const maint = await api('php/admin_bookings.php?maintenance_summary=1');
    document.getElementById('ov-open-maintenance').textContent = maint.open_count ?? '0';

    const staff = await api('php/admin_staff.php?summary=1');
    document.getElementById('ov-total-staff').textContent = staff.total ?? '0';

    // Recent bookings
    const recentB = await api('php/admin_bookings.php?recent=1');
    const btbody = document.querySelector('#ov-bookings-table tbody');
    if (recentB.bookings?.length) {
      btbody.innerHTML = recentB.bookings.map(b => `
        <tr><td>${b.tenant_name}</td><td>${b.room_type_requested}</td><td>${b.date_applied}</td><td>${badge(b.status)}</td></tr>
      `).join('');
    } else {
      btbody.innerHTML = '<tr><td colspan="4" class="empty-row">No recent bookings</td></tr>';
    }

    // Recent maintenance
    const recentM = await api('php/admin_bookings.php?recent_maintenance=1');
    const mtbody = document.querySelector('#ov-maintenance-table tbody');
    if (recentM.requests?.length) {
      mtbody.innerHTML = recentM.requests.map(m => `
        <tr><td>${m.tenant_name}</td><td>${m.category}</td><td>${badge(m.priority)}</td><td>${badge(m.status)}</td></tr>
      `).join('');
    } else {
      mtbody.innerHTML = '<tr><td colspan="4" class="empty-row">No recent requests</td></tr>';
    }
  } catch (e) { console.error(e); }
}

// ── ROOMS ──────────────────────────────────────────────────
document.getElementById('addRoomForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const msg = document.getElementById('room-msg');
  msg.className = 'form-msg';

  const body = {
    room_number: document.getElementById('new-room-number').value,
    block:       document.getElementById('new-room-block').value,
    room_type:   document.getElementById('new-room-type').value,
    capacity:    document.getElementById('new-room-capacity').value,
  };

  if (!body.room_number) {
    msg.textContent = 'Room number is required.';
    msg.classList.add('error');
    return;
  }

  const data = await api('php/admin_rooms.php', 'POST', body);
  if (data.success) {
    msg.textContent = 'Room added successfully!';
    msg.classList.add('success');
    document.getElementById('addRoomForm').reset();
    loadRooms();
  } else {
    msg.textContent = data.message || 'Failed to add room.';
    msg.classList.add('error');
  }
});

async function loadRooms() {
  const statusFilter = document.getElementById('room-filter-status').value;
  const data = await api('php/admin_rooms.php?status=' + statusFilter);
  const tbody = document.querySelector('#rooms-table tbody');

  if (data.rooms?.length) {
    tbody.innerHTML = data.rooms.map(r => `
      <tr>
        <td>${r.room_number}</td>
        <td>${r.block}</td>
        <td>${r.room_type}</td>
        <td>${r.capacity}</td>
        <td>${badge(r.status)}</td>
        <td>
          <select class="status-select" onchange="updateRoomStatus(${r.id}, this.value)">
            <option value="available" ${r.status==='available'?'selected':''}>Available</option>
            <option value="occupied" ${r.status==='occupied'?'selected':''}>Occupied</option>
            <option value="maintenance" ${r.status==='maintenance'?'selected':''}>Maintenance</option>
          </select>
        </td>
      </tr>`).join('');
  } else {
    tbody.innerHTML = '<tr><td colspan="6" class="empty-row">No rooms found</td></tr>';
  }
}

async function updateRoomStatus(roomId, status) {
  await api('php/admin_rooms.php', 'POST', { update_status: true, room_id: roomId, status });
  loadRooms();
}

// ── BOOKINGS ───────────────────────────────────────────────
async function loadBookings() {
  const statusFilter = document.getElementById('booking-filter-status').value;
  const data = await api('php/admin_bookings.php?status=' + statusFilter);
  const tbody = document.querySelector('#bookings-table tbody');

  if (data.bookings?.length) {
    tbody.innerHTML = data.bookings.map(b => `
      <tr>
        <td>${b.tenant_name}</td>
        <td>${b.room_type_requested}</td>
        <td>${b.block_requested}</td>
        <td>${b.academic_year}</td>
        <td>${b.date_applied}</td>
        <td>${badge(b.status)}</td>
        <td>
          ${b.status === 'pending' ? `
            <button class="btn-approve" onclick="processBooking(${b.id}, 'approved')">Approve</button>
            <button class="btn-reject" onclick="processBooking(${b.id}, 'rejected')">Reject</button>
          ` : '—'}
        </td>
      </tr>`).join('');
  } else {
    tbody.innerHTML = '<tr><td colspan="7" class="empty-row">No bookings found</td></tr>';
  }
}

async function processBooking(bookingId, action) {
  const data = await api('php/admin_bookings.php', 'POST', { booking_id: bookingId, action });
  if (data.success) {
    loadBookings();
  } else {
    alert(data.message || 'Action failed.');
  }
}

// ── TENANTS ────────────────────────────────────────────────
async function loadTenants() {
  const search = document.getElementById('tenant-search').value;
  const data = await api('php/admin_tenants.php?search=' + encodeURIComponent(search));
  const tbody = document.querySelector('#tenants-table tbody');

  if (data.tenants?.length) {
    tbody.innerHTML = data.tenants.map(t => `
      <tr>
        <td>${t.full_name || t.username}</td>
        <td>${t.email}</td>
        <td>${t.course || '—'}</td>
        <td>${t.year_of_study || '—'}</td>
        <td>${t.room_number || 'Not Assigned'}</td>
        <td>${t.phone || '—'}</td>
      </tr>`).join('');
  } else {
    tbody.innerHTML = '<tr><td colspan="6" class="empty-row">No tenants found</td></tr>';
  }

  // Populate invoice dropdown too
  const select = document.getElementById('invoice-tenant');
  if (select && data.tenants) {
    select.innerHTML = '<option value="">-- Select tenant --</option>' +
      data.tenants.map(t => `<option value="${t.user_id}">${t.full_name || t.username} (${t.email})</option>`).join('');
  }
}

// ── PAYMENTS ───────────────────────────────────────────────
document.getElementById('generateInvoiceForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const msg = document.getElementById('invoice-msg');
  msg.className = 'form-msg';

  const body = {
    tenant_id:   document.getElementById('invoice-tenant').value,
    description: document.getElementById('invoice-description').value,
    amount:      document.getElementById('invoice-amount').value,
    due_date:    document.getElementById('invoice-due-date').value,
  };

  if (!body.tenant_id || !body.amount || !body.due_date) {
    msg.textContent = 'Please fill in all fields.';
    msg.classList.add('error');
    return;
  }

  const data = await api('php/admin_payments.php', 'POST', body);
  if (data.success) {
    msg.textContent = 'Invoice generated successfully!';
    msg.classList.add('success');
    document.getElementById('generateInvoiceForm').reset();
    loadAdminPayments();
  } else {
    msg.textContent = data.message || 'Failed to generate invoice.';
    msg.classList.add('error');
  }
});

async function loadAdminPayments() {
  const data = await api('php/admin_payments.php');

  const itbody = document.querySelector('#admin-invoices-table tbody');
  if (data.invoices?.length) {
    itbody.innerHTML = data.invoices.map(inv => `
      <tr>
        <td>#${inv.invoice_id}</td>
        <td>${inv.tenant_name}</td>
        <td>${inv.description}</td>
        <td>KES ${inv.total_amount}</td>
        <td>${inv.due_date}</td>
        <td>${badge(inv.status)}</td>
      </tr>`).join('');
  } else {
    itbody.innerHTML = '<tr><td colspan="6" class="empty-row">No invoices found</td></tr>';
  }

  const ptbody = document.querySelector('#admin-payments-table tbody');
  if (data.payments?.length) {
    ptbody.innerHTML = data.payments.map(p => `
      <tr>
        <td>${p.tenant_name}</td>
        <td>KES ${p.amount}</td>
        <td>${p.method}</td>
        <td>${p.reference || '—'}</td>
        <td>${p.payment_date}</td>
      </tr>`).join('');
  } else {
    ptbody.innerHTML = '<tr><td colspan="5" class="empty-row">No payments found</td></tr>';
  }

  loadTenants(); // refresh tenant dropdown for invoice form
}

// ── MAINTENANCE ────────────────────────────────────────────
async function loadAdminMaintenance() {
  const statusFilter = document.getElementById('maintenance-filter-status').value;
  const data = await api('php/admin_bookings.php?maintenance=1&status=' + statusFilter);
  const tbody = document.querySelector('#admin-maintenance-table tbody');

  if (data.requests?.length) {
    tbody.innerHTML = data.requests.map(r => `
      <tr>
        <td>${r.tenant_name}</td>
        <td>${r.category}</td>
        <td>${r.description}</td>
        <td>${badge(r.priority)}</td>
        <td>${r.date_reported}</td>
        <td>${badge(r.status)}</td>
        <td>
          <select class="status-select" onchange="updateMaintenanceStatus(${r.id}, this.value)">
            <option value="open" ${r.status==='open'?'selected':''}>Open</option>
            <option value="in progress" ${r.status==='in progress'?'selected':''}>In Progress</option>
            <option value="resolved" ${r.status==='resolved'?'selected':''}>Resolved</option>
          </select>
        </td>
      </tr>`).join('');
  } else {
    tbody.innerHTML = '<tr><td colspan="7" class="empty-row">No maintenance requests found</td></tr>';
  }
}

async function updateMaintenanceStatus(requestId, status) {
  await api('php/admin_bookings.php', 'POST', { update_maintenance: true, request_id: requestId, status });
  loadAdminMaintenance();
}

// ── STAFF ──────────────────────────────────────────────────
document.getElementById('addStaffForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const msg = document.getElementById('staff-msg');
  msg.className = 'form-msg';

  const body = {
    username: document.getElementById('staff-username').value,
    email:    document.getElementById('staff-email').value,
    password: document.getElementById('staff-password').value,
  };

  if (!body.username || !body.email || !body.password) {
    msg.textContent = 'Please fill in all fields.';
    msg.classList.add('error');
    return;
  }

  const data = await api('php/admin_staff.php', 'POST', body);
  if (data.success) {
    msg.textContent = 'Staff account created successfully!';
    msg.classList.add('success');
    document.getElementById('addStaffForm').reset();
    loadStaff();
  } else {
    msg.textContent = data.message || 'Failed to create staff account.';
    msg.classList.add('error');
  }
});

async function loadStaff() {
  const data = await api('php/admin_staff.php');
  const tbody = document.querySelector('#staff-table tbody');

  if (data.staff?.length) {
    tbody.innerHTML = data.staff.map(s => `
      <tr>
        <td>${s.username}</td>
        <td>${s.email}</td>
        <td>${s.created_at}</td>
        <td><button class="btn-delete" onclick="removeStaff(${s.id})">Remove</button></td>
      </tr>`).join('');
  } else {
    tbody.innerHTML = '<tr><td colspan="4" class="empty-row">No staff accounts found</td></tr>';
  }
}

async function removeStaff(staffId) {
  if (!confirm('Are you sure you want to remove this staff member?')) return;
  await api('php/admin_staff.php', 'POST', { delete: true, staff_id: staffId });
  loadStaff();
}

// ── REPORTS ────────────────────────────────────────────────
async function loadReports() {
  const data = await api('php/admin_reports.php');

  document.getElementById('rep-occupancy-rate').textContent     = (data.occupancy_rate ?? 0) + '%';
  document.getElementById('rep-collection-rate').textContent    = (data.collection_rate ?? 0) + '%';
  document.getElementById('rep-rooms-maintenance').textContent  = data.rooms_under_maintenance ?? '0';

  const occTbody = document.querySelector('#rep-occupancy-table tbody');
  if (data.by_block?.length) {
    occTbody.innerHTML = data.by_block.map(b => `
      <tr><td>${b.block}</td><td>${b.total}</td><td>${b.occupied}</td><td>${b.available}</td></tr>
    `).join('');
  } else {
    occTbody.innerHTML = '<tr><td colspan="4" class="empty-row">No data available</td></tr>';
  }

  const finTbody = document.querySelector('#rep-financial-table tbody');
  finTbody.innerHTML = `
    <tr><td>Total Invoiced</td><td>KES ${data.total_invoiced ?? 0}</td></tr>
    <tr><td>Total Collected</td><td>KES ${data.total_collected ?? 0}</td></tr>
    <tr><td>Outstanding Arrears</td><td>KES ${data.total_arrears ?? 0}</td></tr>
  `;
}

// ── LOAD ON START ──────────────────────────────────────────
loadOverview();