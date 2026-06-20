// ── SIGN IN ──────────────────────────────────────────────────
const signinForm = document.getElementById('signinForm');
if (signinForm) {
  signinForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    const email     = document.getElementById('email').value.trim();
    const password  = document.getElementById('password').value;
    const errorEl   = document.getElementById('signin-error');

    errorEl.style.display = 'none';
    errorEl.textContent   = '';

    try {
      // Step 1: POST to login → get JWT tokens
      const tokenRes = await fetch('php/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password }),
      });

      const tokenData = await tokenRes.json();

      if (!tokenData.success) {
        errorEl.textContent   = tokenData.message || 'Invalid username or password';
        errorEl.style.display = 'block';
        return;
      }

      // Step 2: Save tokens — mirrors localStorage.setItem("access_token")
      localStorage.setItem('access_token',  tokenData.access);
      localStorage.setItem('refresh_token', tokenData.refresh);

      // Step 3: GET profile using access token — mirrors axios.get("/api/profile/")
      const profileRes = await fetch('php/profile.php', {
        method: 'GET',
        headers: {
          'Authorization': 'Bearer ' + tokenData.access,
          'Content-Type': 'application/json',
        },
      });

      const user = await profileRes.json();

      // Step 4: Save user — mirrors localStorage.setItem("user")
      localStorage.setItem('user', JSON.stringify(user));

      // Step 5: Redirect based on role — mirrors navigate()
      if (user.role === 'admin')       window.location.href = 'admin-dashboard.html';
      else if (user.role === 'staff')  window.location.href = 'staff-dashboard.html';
      else if (user.role === 'tenant') window.location.href = 'tenant-dashboard.html';
      else                             window.location.href = 'index.html';

    } catch (err) {
      errorEl.textContent   = 'Invalid username or password';
      errorEl.style.display = 'block';
      console.error(err);
    }
  });
}


// ── SIGN UP ──────────────────────────────────────────────────
const signupForm = document.getElementById('signupForm');
if (signupForm) {
  signupForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    const username        = document.getElementById('username').value.trim();
    const email           = document.getElementById('email').value.trim();
    const password        = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const errorEl         = document.getElementById('signup-error');

    errorEl.style.display = 'none';
    errorEl.textContent   = '';

    // Mirrors: if (password !== confirmPassword) setError("Passwords do not match")
    if (password !== confirmPassword) {
      errorEl.textContent   = 'Passwords do not match';
      errorEl.style.display = 'block';
      return;
    }

    try {
      // Mirrors: axios.post("http://127.0.0.1:8000/api/register/", {...})
      const response = await fetch('php/register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, email, password }),
      });

      const data = await response.json();

      // Mirrors: if (response.data.message === "User registered successfully")
      if (data.message === 'User registered successfully') {
        window.location.href = 'signin.html'; // mirrors navigate("/signin")
      } else {
        errorEl.textContent   = data.message || 'Registration failed.';
        errorEl.style.display = 'block';
      }

    } catch (err) {
      errorEl.textContent   = err.message || 'Something went wrong.';
      errorEl.style.display = 'block';
    }
  });
}