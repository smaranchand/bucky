<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bucky — Manual Check</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f7fa;
    color: #333;
    min-height: 100vh;
}
a { color: inherit; text-decoration: none; }

.header {
    background: linear-gradient(135deg, #0dbce7 0%, #0a9ec4 100%);
    color: #fff;
    padding: 16px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 8px rgba(13, 188, 231, 0.3);
}
.header h1 {
    font-size: 20px;
    font-weight: 600;
    letter-spacing: 0.5px;
}
.btn {
    display: inline-block;
    background: #fff;
    color: #0dbce7;
    padding: 8px 18px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.5px;
    transition: background 0.2s, color 0.2s;
    border: 2px solid #fff;
}
.btn:hover {
    background: transparent;
    color: #fff;
    border-color: #fff;
}

.container {
    max-width: 560px;
    margin: 60px auto;
    padding: 0 20px;
}
.card {
    background: #fff;
    border-radius: 8px;
    padding: 32px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
}
.card h2 {
    font-size: 18px;
    margin-bottom: 6px;
}
.card p {
    font-size: 13px;
    color: #888;
    margin-bottom: 24px;
}
.form-group {
    display: flex;
    gap: 10px;
}
.form-group input[type="text"] {
    flex: 1;
    padding: 10px 14px;
    border: 2px solid #e0e4ea;
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
    outline: none;
    transition: border-color 0.2s;
}
.form-group input[type="text"]:focus {
    border-color: #0dbce7;
}
.form-group input[type="submit"] {
    background: #0dbce7;
    color: #fff;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    letter-spacing: 0.3px;
    cursor: pointer;
    transition: background 0.2s;
}
.form-group input[type="submit"]:hover {
    background: #0aa8d0;
}
</style>
</head>
<body>
<?php require_once __DIR__ . '/../csrf.php'; ?>
<div class="header">
    <h1>Bucky — Manual Check</h1>
    <a href="/buckets" class="btn">All Buckets</a>
</div>

<div class="container">
    <div class="card">
        <h2>Check a Bucket</h2>
        <p>Enter the S3 bucket name to test for misconfiguration.</p>
        <form method="POST" action="/process">
            <?= csrf_input() ?>
            <input type="hidden" name="sourceurl" value="-">
            <input type="hidden" name="hostname" value="-">
            <div class="form-group">
                <input type="text" name="bucketname" pattern="[a-z0-9.\-]{3,63}" placeholder="my-bucket-name" maxlength="63" required>
                <input type="submit" value="Check">
            </div>
        </form>
    </div>
</div>
</body>
</html>
