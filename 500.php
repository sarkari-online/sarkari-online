<?php
/**
 * EduPulse - 500 Internal Server Error Page
 */
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'Sarkari.online');
    define('SITE_URL', '/automation');
}

$pageTitle = '500 — Server Error';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 Internal Server Error | <?= htmlspecialchars(SITE_NAME) ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 1.5rem;
            box-sizing: border-box;
        }
        .error-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 2.5rem;
            max-width: 580px;
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .error-code {
            font-size: 4rem;
            font-weight: 900;
            color: #b91c1c;
            line-height: 1;
            margin-bottom: 1rem;
        }
        .error-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }
        .error-msg {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        .btn-home {
            display: inline-block;
            background: #1e3a8a;
            color: #ffffff;
            font-weight: 600;
            text-decoration: none;
            padding: 0.65rem 1.25rem;
            border-radius: 6px;
            transition: background 0.2s;
        }
        .btn-home:hover {
            background: #172554;
        }
        .debug-box {
            text-align: left;
            margin-top: 1.5rem;
            padding: 1rem;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.8rem;
            overflow-x: auto;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-code">500</div>
        <h1 class="error-title">Unexpected Server Error</h1>
        <p class="error-msg">
            Our systems encountered an unexpected condition while processing your request. Our technical team has been automatically notified and is investigating the issue.
        </p>

        <?php if (defined('APP_DEBUG') && APP_DEBUG && isset($exception)): ?>
            <div class="debug-box">
                <strong><?= htmlspecialchars(get_class($exception)) ?>:</strong> <?= htmlspecialchars($exception->getMessage()) ?><br>
                <small>in <?= htmlspecialchars($exception->getFile()) ?> on line <?= $exception->getLine() ?></small>
            </div>
        <?php endif; ?>

        <div style="margin-top: 1.5rem;">
            <a href="<?= defined('SITE_URL') ? htmlspecialchars(SITE_URL) : '/' ?>" class="btn-home">Return to Homepage</a>
        </div>
    </div>
</body>
</html>
