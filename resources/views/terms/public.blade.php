<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms & Conditions - SafeeMeet</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #000;
            color: #fff;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px 60px;
        }
        .brand {
            text-align: center;
            margin-bottom: 24px;
        }
        .brand img {
            height: 56px;
            width: auto;
            background: #fff;
            border-radius: 8px;
            padding: 6px 14px;
            object-fit: contain;
        }
        h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 24px;
            text-align: center;
            color: #fff;
        }
        .card {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 28px;
            line-height: 1.7;
            color: #cdd9f0;
            word-wrap: break-word;
        }
        .card img { max-width: 100%; height: auto; }
        .card h1, .card h2, .card h3 { color: #fff; }
        .card a { color: #DC131C; }
        .card p:last-child { margin-bottom: 0; }
        .footer {
            text-align: center;
            margin-top: 28px;
            font-size: 12px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="brand">
            <img src="{{ asset('images/logo.png') }}" alt="Safee Meet">
        </div>
        <h1>Terms & Conditions</h1>
        <div class="card">
            {!! $terms->content ?? '' !!}
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} SafeeMeet. All rights reserved.
        </div>
    </div>
</body>
</html>
