// ── AUTH CHECK ─────────────────────────────────────────────
const user = JSON.parse(localStorage.getItem('user') || '{}');
const token = localStorage.getItem('access_token');

if (!user || !token || user.role !== 'tenant') {
  window.location.href = 'signin.html';
}

// Set username in navbar
document.getElementById('navbar-username').textContent = user.username || '';
document.getElementById('overview-name').textContent = user.username || '';
document.getElementById('profile-name').textContent = user.username || '';
document.getElementById('profile-email').textContent = user.email || '';
document.getElementById('profile-avatar').textContent = (user.username || 'T')[0].toUpperCase();

// ── NAVIGATION ─────────────────────────────────────────────
function showSection(name) {
  document.querySelectorAll('.dash-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('section-' + name).classList.add('active');
  event.target.classList.add('active');

  if (name === 'overview')    loadOverview();
  if (name === 'payments')    loadPayments();
  if (name === 'maintenance') loadMaintenance();
  if (name === 'booking')     loadBooking();
  if (name === 'profile')     loadProfile();
}

function logout() {
  localStorage.clear();
  window.location.href = 'signin.html';
}

// ── HELPERS ────────────────────────────────────────────────
function badge(status) {
  const map = {
    paid: 'success', active: 'success', resolved: 'success', approved: 'success',
    pending: 'warning', open: 'warning', 'in progress': 'warning',
    overdue: 'danger', rejected: 'danger',
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
      'Authorization': 'Bearer ' + token,
    },
  };
  if (body) opts.body = JSON.stringify(body);
  const res = await fetch(url, opts);
  return res.json();
}

// ── OVERVIEW ───────────────────────────────────────────────
async function loadOverview() {
  try {
    const data = await api('php/tenant_data.php');

    document.getElementById('stat-room').textContent     = data.room    || 'Not Assigned';
    document.getElementById('stat-balance').textContent  = data.balance ? 'KES ' + data.balance : 'KES 0';
    document.getElementById('stat-maintenance').textContent = data.open_requests ?? '0';
    document.getElementById('stat-booking').textContent  = data.booking_status || 'None';

    const ptbody = document.querySelector('#overview-payments tbody');
    if (data.recent_payments?.length) {
      ptbody.innerHTML = data.recent_payments.map(p => `
        <tr>
          <td>${p.payment_date}</td>
          <td>KES ${p.amount}</td>
          <td>${badge(p.status || 'paid')}</td>
        </tr>`).join('');
    } else {
      ptbody.innerHTML = '<tr><td colspan="3" class="empty-row">No payments yet</td></tr>';
    }

    const mtbody = document.querySelector('#overview-maintenance tbody');
    if (data.recent_maintenance?.length) {
      mtbody.innerHTML = data.recent_maintenance.map(m => `
        <tr>
          <td>${m.category}</td>
          <td>${m.date_reported}</td>
          <td>${badge(m.status)}</td>
        </tr>`).join('');
    } else {
      mtbody.innerHTML = '<tr><td colspan="3" class="empty-row">No requests yet</td></tr>';
    }
  } catch (e) {
    console.error(e);
  }
}

// ── BOOKING ────────────────────────────────────────────────
async function loadBooking() {
  try {
    const data = await api('php/tenant_data.php');
    const div = document.getElementById('booking-details');
    if (data.booking) {
      const b = data.booking;
      div.innerHTML = `
        <div class="booking-info">
          <div class="booking-info-item"><label>Room Number</label><span>${b.room_number || '—'}</span></div>
          <div class="booking-info-item"><label>Block</label><span>${b.block || '—'}</span></div>
          <div class="booking-info-item"><label>Room Type</label><span>${b.room_type || '—'}</span></div>
          <div class="booking-info-item"><label>Academic Year</label><span>${b.academic_year || '—'}</span></div>
          <div class="booking-info-item"><label>Status</label><span>${badge(b.status)}</span></div>
          <div class="booking-info-item"><label>Date Applied</label><span>${b.date_applied || '—'}</span></div>
        </div>`;
    } else {
      div.innerHTML = '<p class="empty-msg">No active booking. Use the form below to apply.</p>';
    }
  } catch (e) { console.error(e); }
}

document.getElementById('bookingForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const msg = document.getElementById('booking-msg');
  msg.className = 'form-msg';
  msg.textContent = '';

  const body = {
    room_type:     document.getElementById('room-type').value,
    block:         document.getElementById('room-block').value,
    academic_year: document.getElementById('academic-year').value,
    notes:         document.getElementById('booking-notes').value,
  };

  if (!body.room_type || !body.block || !body.academic_year) {
    msg.textContent = 'Please fill in all required fields.';
    msg.classList.add('error');
    return;
  }

  try {
    const data = await api('php/book_room.php', 'POST', body);
    if (data.success) {
      msg.textContent = 'Application submitted successfully!';
      msg.classList.add('success');
      document.getElementById('bookingForm').reset();
      loadBooking();
    } else {
      msg.textContent = data.message || 'Submission failed.';
      msg.classList.add('error');
    }
  } catch (e) {
    msg.textContent = 'Server error. Please try again.';
    msg.classList.add('error');
  }
});

// ── PAYMENTS ───────────────────────────────────────────────
async function loadPayments() {
  try {
    const data = await api('php/payments.php');

    document.getElementById('pay-total').textContent   = 'KES ' + (data.total_invoiced || '0');
    document.getElementById('pay-paid').textContent    = 'KES ' + (data.total_paid || '0');
    document.getElementById('pay-balance').textContent = 'KES ' + (data.balance || '0');

    const itbody = document.querySelector('#invoices-table tbody');
    if (data.invoices?.length) {
      itbody.innerHTML = data.invoices.map(inv => `
        <tr>
          <td>#${inv.invoice_id}</td>
          <td>${inv.description || 'Accommodation Fee'}</td>
          <td>KES ${inv.total_amount}</td>
          <td>${inv.due_date}</td>
          <td>${badge(inv.status)}</td>
          <td>
            ${inv.status !== 'paid' ? `
              <button class="btn-pay" onclick="payInvoice('${inv.invoice_id}', '${inv.total_amount}')">
                Pay via M-Pesa
              </button>
            ` : '—'}
          </td>
        </tr>`).join('');
    } else {
      itbody.innerHTML = '<tr><td colspan="6" class="empty-row">No invoices found</td></tr>';
    }

    const ptbody = document.querySelector('#payments-table tbody');
    if (data.payments?.length) {
      ptbody.innerHTML = data.payments.map(p => `
        <tr>
          <td>${p.payment_date}</td>
          <td>KES ${p.amount}</td>
          <td>${p.method}</td>
          <td>${p.reference || '—'}</td>
        </tr>`).join('');
    } else {
      ptbody.innerHTML = '<tr><td colspan="4" class="empty-row">No payment history</td></tr>';
    }
  } catch (e) { console.error(e); }
}

// ── MAINTENANCE ────────────────────────────────────────────
async function loadMaintenance() {
  try {
    const data = await api('php/maintenance.php');
    const tbody = document.querySelector('#maintenance-table tbody');
    if (data.requests?.length) {
      tbody.innerHTML = data.requests.map(r => `
        <tr>
          <td>${r.category}</td>
          <td>${r.description}</td>
          <td>${badge(r.priority)}</td>
          <td>${r.date_reported}</td>
          <td>${badge(r.status)}</td>
        </tr>`).join('');
    } else {
      tbody.innerHTML = '<tr><td colspan="5" class="empty-row">No maintenance requests yet</td></tr>';
    }
  } catch (e) { console.error(e); }
}

document.getElementById('maintenanceForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const msg = document.getElementById('maintenance-msg');
  msg.className = 'form-msg';
  msg.textContent = '';

  const body = {
    category:    document.getElementById('issue-category').value,
    description: document.getElementById('issue-description').value,
    priority:    document.getElementById('issue-priority').value,
  };

  if (!body.category || !body.description) {
    msg.textContent = 'Please fill in all required fields.';
    msg.classList.add('error');
    return;
  }

  try {
    const data = await api('php/maintenance.php', 'POST', body);
    if (data.success) {
      msg.textContent = 'Request submitted successfully!';
      msg.classList.add('success');
      document.getElementById('maintenanceForm').reset();
      loadMaintenance();
    } else {
      msg.textContent = data.message || 'Submission failed.';
      msg.classList.add('error');
    }
  } catch (e) {
    msg.textContent = 'Server error. Please try again.';
    msg.classList.add('error');
  }
});

// ── PROFILE ────────────────────────────────────────────────
async function loadProfile() {
  try {
    const data = await api('php/tenant_data.php');
    if (data.profile) {
      document.getElementById('profile-fullname').value        = data.profile.full_name || '';
      document.getElementById('profile-phone').value           = data.profile.phone || '';
      document.getElementById('profile-course').value          = data.profile.course || '';
      document.getElementById('profile-year').value            = data.profile.year_of_study || '1';
      document.getElementById('profile-emergency-name').value  = data.profile.emergency_contact_name || '';
      document.getElementById('profile-emergency-phone').value = data.profile.emergency_contact_phone || '';
    }
  } catch (e) { console.error(e); }
}

document.getElementById('profileForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const msg = document.getElementById('profile-msg');
  msg.className = 'form-msg';

  const body = {
    full_name:               document.getElementById('profile-fullname').value,
    phone:                   document.getElementById('profile-phone').value,
    course:                  document.getElementById('profile-course').value,
    year_of_study:           document.getElementById('profile-year').value,
    emergency_contact_name:  document.getElementById('profile-emergency-name').value,
    emergency_contact_phone: document.getElementById('profile-emergency-phone').value,
  };

  try {
    const data = await api('php/tenant_data.php', 'POST', body);
    if (data.success) {
      msg.textContent = 'Profile updated successfully!';
      msg.classList.add('success');
    } else {
      msg.textContent = data.message || 'Update failed.';
      msg.classList.add('error');
    }
  } catch (e) {
    msg.textContent = 'Server error.';
    msg.classList.add('error');
  }
});

document.getElementById('passwordForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const msg = document.getElementById('password-msg');
  msg.className = 'form-msg';

  const newPwd     = document.getElementById('new-password').value;
  const confirmPwd = document.getElementById('confirm-new-password').value;

  if (newPwd !== confirmPwd) {
    msg.textContent = 'New passwords do not match.';
    msg.classList.add('error');
    return;
  }

  try {
    const data = await api('php/tenant_data.php', 'POST', {
      change_password: true,
      current_password: document.getElementById('current-password').value,
      new_password: newPwd,
    });
    if (data.success) {
      msg.textContent = 'Password changed successfully!';
      msg.classList.add('success');
      document.getElementById('passwordForm').reset();
    } else {
      msg.textContent = data.message || 'Change failed.';
      msg.classList.add('error');
    }
  } catch (e) {
    msg.textContent = 'Server error.';
    msg.classList.add('error');
  }
});

// ── M-PESA PAYMENT ─────────────────────────────────────────
function payInvoice(invoiceId, amount) {
  const phone = prompt('Enter your M-Pesa phone number (e.g. 0712345678):');
  if (!phone) return;

  const cleaned = phone.replace(/\s+/g, '').replace(/^0/, '254').replace(/^\+/, '');
  if (!/^2547\d{8}$|^2541\d{8}$/.test(cleaned)) {
    alert('Invalid phone number. Use format 0712345678');
    return;
  }

  showMpesaModal(invoiceId, amount, cleaned);
}

function showMpesaModal(invoiceId, amount, phone) {
  const overlay = document.createElement('div');
  overlay.className = 'mpesa-modal-overlay';
  overlay.innerHTML = `
    <div class="mpesa-modal">
      <div class="mpesa-logo">📱</div>
      <h3>M-Pesa Payment</h3>
      <p>Sending KES ${amount} request to<br><strong style="color:white">${phone}</strong></p>
      <p id="mpesa-msg" class="mpesa-status waiting">Sending STK push...</p>
      <button onclick="this.closest('.mpesa-modal-overlay').remove()"
        style="margin-top:1rem;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);
        color:white;padding:0.5rem 1.5rem;border-radius:8px;cursor:pointer;font-size:0.9rem;">
        Close
      </button>
    </div>
  `;
  document.body.appendChild(overlay);

  initiateMpesa(invoiceId, amount, phone, overlay);
}

async function initiateMpesa(invoiceId, amount, phone, overlay) {
  const msgEl = overlay.querySelector('#mpesa-msg');
  try {
    const res = await fetch('php/mpesa_pay.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + token,
      },
      body: JSON.stringify({ invoice_id: invoiceId, amount, phone }),
    });

    const data = await res.json();

    if (data.success) {
      msgEl.className = 'mpesa-status waiting';
      msgEl.textContent = '✅ Request sent! Check your phone and enter your M-Pesa PIN.';

      let attempts = 0;
      const poll = setInterval(async () => {
        attempts++;
        if (attempts > 12) {
          clearInterval(poll);
          msgEl.className = 'mpesa-status error';
          msgEl.textContent = '⏱ Timed out. If you paid, it will reflect shortly.';
          return;
        }

        const check = await fetch('php/mpesa_status.php?invoice_id=' + invoiceId, {
          headers: { 'Authorization': 'Bearer ' + token }
        });
        const status = await check.json();

        if (status.paid) {
          clearInterval(poll);
          msgEl.className = 'mpesa-status success';
          msgEl.textContent = '🎉 Payment confirmed! Thank you.';
          loadPayments();
        }
      }, 5000);

    } else {
      msgEl.className = 'mpesa-status error';
      msgEl.textContent = '❌ ' + (data.message || 'Failed to send request.');
    }
  } catch (e) {
    msgEl.className = 'mpesa-status error';
    msgEl.textContent = '❌ Server error. Please try again.';
  }
}

// ── LOAD OVERVIEW ON START ─────────────────────────────────
loadOverview();