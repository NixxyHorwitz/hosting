<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Mengalihkan...</title>
    <style>
        body { margin: 0; background: #090d18; display: flex; align-items: center; justify-content: center; height: 100vh; font-family: 'Segoe UI', sans-serif; color: #e2e8f0; flex-direction: column; gap: 16px; }
        .spinner { width: 40px; height: 40px; border: 3px solid rgba(59,130,246,0.3); border-top-color: #3b82f6; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="spinner"></div>
    <div>Mengalihkan ke dashboard pengguna...</div>
    <script>
        // Redirect ke halaman user setelah 800ms (memberi kesan halaman baru)
        setTimeout(() => { window.location.href = '/hosting'; }, 800);
    </script>
</body>
</html>
