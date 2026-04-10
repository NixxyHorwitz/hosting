<?php
// Shared CSS for all auth pages (login, register, otp, forgot, reset)
// Included via standard include statement inside a style tag.
?>
*, *::before, *::after { box-sizing: border-box; }
:root {
    --blue: #3b5bdb;
    --blue-dark: #2f4ac4;
    --blue-light: #4c6ef5;
    --bg: #f1f3f9;
    --border: #e0e5f0;
    --text: #1a1d2e;
    --sub: #6c757d;
    --input-bg: #f8faff;
}
body { font-family: 'Inter', sans-serif; background: var(--bg); min-height: 100vh; margin: 0; display: flex; }

/* ── Brand Panel (left side) ── */
.brand-panel {
    width: 360px; min-height: 100vh;
    background: linear-gradient(160deg, #3b5bdb 0%, #1a3070 100%);
    position: fixed; left: 0; top: 0; bottom: 0;
    display: flex; flex-direction: column; justify-content: space-between;
    padding: 40px 36px; z-index: 1; overflow: hidden;
}
.brand-panel::before {
    content: ''; position: absolute; inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.brand-logo {
    display: flex; align-items: center; gap: 10px;
    font-size: 22px; font-weight: 800; color: white;
    text-decoration: none; position: relative;
}
.brand-logo-icon {
    width: 42px; height: 42px; background: rgba(255,255,255,.15);
    border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;
}
.brand-tagline { font-size: 26px; font-weight: 800; color: white; line-height: 1.3; position: relative; margin-top: auto; }
.brand-desc    { font-size: 13px; color: rgba(255,255,255,.7); line-height: 1.7; position: relative; margin-top: 14px; }
.brand-footer  { font-size: 11px; color: rgba(255,255,255,.4); position: relative; }

/* ── Form Panel (right side) ── */
.form-panel {
    margin-left: 360px; flex: 1; min-height: 100vh;
    padding: 50px 64px;
    display: flex; flex-direction: column; justify-content: center;
    position: relative;
}
.form-topbar {
    position: absolute; top: 36px; right: 64px;
    font-size: 13px; color: var(--sub);
}
.form-topbar a { color: var(--blue); font-weight: 600; text-decoration: none; }
.form-topbar a:hover { text-decoration: underline; }

.form-wrap { max-width: 430px; width: 100%; margin: 0 auto; }
.form-title    { font-size: 28px; font-weight: 800; color: var(--text); margin-bottom: 6px; text-align: center; }
.form-subtitle { font-size: 13px; color: var(--sub); margin-bottom: 28px; line-height: 1.6; text-align: center; }

/* ── Fields ── */
.field-group  { margin-bottom: 18px; }
.field-label  { font-size: 12px; font-weight: 600; color: var(--sub); margin-bottom: 5px; display: block; }
.req          { color: #e03131; }
.field-wrap   { position: relative; }
.field-icon   { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--sub); font-size: 16px; pointer-events: none; z-index: 1; }
.form-input {
    width: 100%; height: 46px;
    border: 1.5px solid var(--border); border-radius: 10px;
    background: var(--input-bg); font-size: 13.5px; color: var(--text);
    padding: 0 14px 0 38px;
    transition: border-color .15s, box-shadow .15s; outline: none; font-family: inherit;
}
.form-input.no-icon { padding-left: 14px; }
.form-input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(59,91,219,.1); background: white; }
.form-input::placeholder { color: #adb5bd; }

.pass-toggle {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    background: none; border: none; color: var(--sub); cursor: pointer; font-size: 18px; padding: 0;
}
.forgot-link { font-size: 12px; color: var(--blue); font-weight: 600; text-decoration: none; display: block; text-align: right; margin-top: 6px; }
.forgot-link:hover { text-decoration: underline; }

/* ── Buttons ── */
.btn-submit {
    width: 100%; height: 48px;
    background: linear-gradient(135deg, var(--blue-light) 0%, var(--blue-dark) 100%);
    color: white; border: none; border-radius: 12px;
    font-size: 14px; font-weight: 700; letter-spacing: .5px;
    cursor: pointer; transition: all .2s; margin-top: 6px;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    font-family: inherit;
}
.btn-submit:hover { box-shadow: 0 6px 20px rgba(59,91,219,.35); transform: translateY(-1px); }
.btn-submit:active { transform: translateY(0); }
.btn-submit:disabled { opacity: .6; cursor: not-allowed; transform: none; }

/* ── Google SSO ── */
.btn-google {
    display: flex; align-items: center; justify-content: center; gap: 10px;
    width: 100%; height: 46px; background: #fff;
    border: 1.5px solid var(--border); border-radius: 12px;
    font-size: 13.5px; font-weight: 600; color: var(--text); text-decoration: none;
    cursor: pointer; transition: all .15s; box-shadow: 0 1px 3px rgba(0,0,0,.06); font-family: inherit;
    margin-bottom: 0;
}
.btn-google:hover { border-color: #4285F4; box-shadow: 0 3px 12px rgba(66,133,244,.18); color: var(--text); }
.btn-google.disabled { opacity: .5; cursor: not-allowed; pointer-events: none; }

/* ── Divider ── */
.or-divider { display: flex; align-items: center; gap: 12px; margin: 20px 0; }
.or-divider div { flex: 1; height: 1px; background: var(--border); }
.or-divider span { font-size: 12px; color: var(--sub); white-space: nowrap; }

/* ── Alert boxes ── */
.alert-box { border-radius: 10px; padding: 12px 16px; font-size: 13px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.alert-box i { font-size: 20px; flex-shrink: 0; }
.alert-err  { background:#fff5f5; border:1px solid #ffa8a8; color:#c92a2a; }
.alert-ok   { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
.alert-info { background:#eff6ff; border:1px solid #93c5fd; color:#1e40af; }

/* ── OTP box ── */
.otp-input {
    text-align: center; font-size: 26px; font-weight: 800;
    letter-spacing: 12px; padding: 12px 14px;
}

/* ── Single-col center card (for OTP / forgot / reset) ── */
.auth-center {
    flex: 1; display: flex; align-items: center; justify-content: center;
    padding: 40px 24px; margin-left: 360px;
}
.auth-card {
    background: white; border-radius: 20px; padding: 44px 48px;
    box-shadow: 0 8px 40px rgba(0,0,0,.08); width: 100%; max-width: 440px;
}
.auth-icon-wrap {
    width: 64px; height: 64px; border-radius: 18px;
    background: #eff6ff; display: flex; align-items: center; justify-content: center;
    font-size: 30px; color: var(--blue); margin: 0 auto 20px;
}
.auth-card-title { font-size: 22px; font-weight: 800; color: var(--text); text-align: center; margin-bottom: 8px; }
.auth-card-sub   { font-size: 13px; color: var(--sub); text-align: center; margin-bottom: 28px; line-height: 1.6; }

/* ── Bottom note ── */
.bottom-note { text-align: center; font-size: 12px; color: var(--sub); margin-top: 28px; }
.bottom-note a { color: var(--blue); font-weight: 700; text-decoration: none; }
.bottom-note a:hover { text-decoration: underline; }

@media (max-width: 768px) {
    .brand-panel  { display: none; }
    .form-panel   { margin-left: 0; padding: 40px 28px; }
    .form-topbar  { right: 28px; }
    .auth-center  { margin-left: 0; }
    .auth-card    { padding: 32px 24px; }
}
