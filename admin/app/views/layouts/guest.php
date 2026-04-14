<?php

declare(strict_types=1);

use Revita\Crm\Helpers\Escape;
use Revita\Crm\Helpers\Url;

/** @var string $title */
/** @var string $content */
$assetLogo = Url::to('/assets/img/logoRevita.png');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= Escape::html($title) ?></title>
  <link rel="icon" type="image/png" href="<?= Escape::html($assetLogo) ?>">
  <link rel="stylesheet" href="<?= Escape::html(Url::to('/assets/css/guest-shell.css')) ?>">
  <style>
    :root {
      --revita-orange: #FF912C;
      --revita-orange-hover: #e67e24;
      --revita-bg: #f4f5f7;
      --revita-text: #2b2f36;
    }
    body {
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      background: var(--revita-bg);
      color: var(--revita-text);
    }
    .revita-card {
      border: none;
      border-radius: 16px;
      box-shadow: 0 8px 24px rgba(43, 47, 54, 0.08);
    }
    .btn-revita {
      background-color: var(--revita-orange);
      border-color: var(--revita-orange);
      color: #fff;
      font-weight: 600;
      border-radius: 10px;
      padding: 0.55rem 1.25rem;
    }
    .btn-revita:hover {
      background-color: var(--revita-orange-hover);
      border-color: var(--revita-orange-hover);
      color: #fff;
    }
    .logo-box {
      max-width: 220px;
    }
  </style>
</head>
<body class="d-flex align-items-center min-vh-100 py-4" style="margin:0;">
  <div class="container">
    <div class="text-center mb-4">
      <img class="logo-box img-fluid" src="<?= Escape::html($assetLogo) ?>" alt="Revita Comunicação">
    </div>
    <div class="card revita-card">
      <div class="card-body p-4 p-md-5">
        <?= $content ?>
      </div>
    </div>
    <p class="text-center text-secondary small mt-3 mb-0">Revita CMS — uso interno</p>
  </div>
</body>
</html>
