<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';

use Symfony\Component\Security\Csrf\CsrfToken;
use Carbon\Carbon;

$links = load_links();
$analytics = load_analytics();

$csrfId = 'dashboard_action';
$csrfToken = $csrfManager->getToken($csrfId)->getValue();
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = $_POST['_token'] ?? '';
    $tokenObj = new CsrfToken($csrfId, $posted);
    if (!$csrfManager->isTokenValid($tokenObj)) {
        $messages[] = ['type' => 'error', 'text' => 'Invalid form token.'];
    } else {
        $action = $_POST['action'] ?? null;
        $id = $_POST['id'] ?? null;
        if ($action === 'delete' && $id) {
            // remove link
            $new = array_values(array_filter($links, fn($l)=> $l['id'] !== $id));
            save_links($new);
            $links = $new;
            $messages[] = ['type' => 'success', 'text' => 'Link deleted.'];
            $logger->info('Link deleted via dashboard', ['id' => $id]);
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Dashboard - Geo-Fence</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div class="header">
    <div class="logo"><div class="mark">GF</div><div class="title">Admin Dashboard</div></div>
    <div class="nav"><a href="index.php">Create Link</a></div>
  </div>
  <div class="container">
    <h2>Links & Analytics</h2>

    <?php foreach($messages as $m): ?>
      <div class="card" style="margin-bottom:8px; background:<?= $m['type']==='error' ? 'rgba(255,0,0,0.08)' : 'rgba(0,255,0,0.04)' ?>"><?= htmlspecialchars($m['text']) ?></div>
    <?php endforeach; ?>

    <?php if (empty($links)): ?>
      <p class="small">No links found. Create one on the Create Link page.</p>
    <?php else: ?>
      <?php foreach(array_reverse($links) as $link): ?>
        <?php $stat = $analytics[$link['id']] ?? null; ?>
        <div class="card link-row">
          <div style="flex:1">
            <div><strong><?= htmlspecialchars($link['target_url']) ?></strong></div>
            <div class="small">ID: <?= htmlspecialchars(substr($link['id'],0,8)) ?>… | Expires: <?= htmlspecialchars($link['expires']) ?></div>
            <div class="stats">
              <div class="stat">Attempts: <?= $stat['total_attempts'] ?? 0 ?></div>
              <div class="stat">Success: <?= $stat['successful_access'] ?? 0 ?></div>
              <div class="stat">Failed: <?= $stat['failed_access'] ?? 0 ?></div>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:8px">
            <?php $token = jwt_sign(['sub'=>'geo-fence-link','jti'=>$link['id'],'lat'=>$link['lat'],'lng'=>$link['lng'],'radius'=>$link['radius'],'target_url'=>$link['target_url'],'exp'=>strtotime($link['expires'])]);
                  $url = rtrim($_ENV['APP_URL'] ?? (($_SERVER['REQUEST_SCHEME'] ?? 'http').'://'.$_SERVER['HTTP_HOST']), '/') . '/redirect.php?token=' . $token;
            ?>
            <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="badge">Open</a>
            <a href="<?= htmlspecialchars(generate_qr_code_url($url)) ?>" target="_blank" class="badge">QR</a>
            <form method="POST" style="margin:0">
              <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= htmlspecialchars($link['id']) ?>">
              <button type="submit" class="ghost">Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>
</body>
</html>
