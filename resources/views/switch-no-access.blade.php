<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>No access</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f8f8; color: #0a3535; margin: 0; display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 16px; }
        .card { background: #fff; border: 1px solid #e9eef0; border-radius: 14px; padding: 36px 32px; max-width: 440px; text-align: center; box-shadow: 0 6px 18px rgba(10, 53, 53, .06); }
        h1 { font-size: 22px; margin: 0 0 10px; }
        p { color: #5a6b6b; line-height: 1.5; margin: 0 0 22px; }
        a { display: inline-block; background: #148080; color: #fff; text-decoration: none; padding: 10px 18px; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>You don't have access to JF Aesthetics {{ $site }}</h1>
        <p>Your account isn't allowed to open this site. If you think you should have access, ask a Super Admin.</p>
        <a href="{{ url('/login') }}">Go to login</a>
    </div>
</body>
</html>
