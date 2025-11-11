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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - Geo-Fence Link Generator</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="logo"><div class="mark">GF</div><div class="title">Admin Dashboard</div></div>
      <div class="nav"><a href="index.php">➕ Create Link</a></div>
    </div>

    <h1>📊 Links & Analytics</h1>

    <?php foreach($messages as $m): ?>
      <div class="card" style="margin-bottom:12px; background:<?= $m['type']==='error' ? 'rgba(239,68,68,0.1)' : 'rgba(16,185,129,0.1)' ?>; border-color:<?= $m['type']==='error' ? 'rgba(239,68,68,0.3)' : 'rgba(16,185,129,0.3)' ?>">
        <?= $m['type']==='error' ? '❌' : '✅' ?> <?= htmlspecialchars($m['text']) ?>
      </div>
    <?php endforeach; ?>

    <?php if (empty($links)): ?>
      <div class="card" style="text-align:center;padding:40px">
        <div style="font-size:3rem;margin-bottom:16px">📭</div>
        <p style="font-size:1.1rem;margin-bottom:8px">No links found</p>
        <p class="small">Create your first geo-fenced link to get started!</p>
        <a href="index.php" style="display:inline-block;margin-top:16px"><button>➕ Create New Link</button></a>
      </div>
    <?php else: ?>
      <div class="card" style="padding:16px;margin-bottom:20px">
        <div class="stats">
          <div class="stat">📊 Total Links: <strong><?= count($links) ?></strong></div>
          <div class="stat">✅ Total Success: <strong><?= array_sum(array_column($analytics, 'successful_access')) ?></strong></div>
          <div class="stat">❌ Total Failed: <strong><?= array_sum(array_column($analytics, 'failed_access')) ?></strong></div>
          <div class="stat">🔄 Total Attempts: <strong><?= array_sum(array_column($analytics, 'total_attempts')) ?></strong></div>
        </div>
      </div>

      <?php foreach(array_reverse($links) as $link): ?>
        <?php $stat = $analytics[$link['id']] ?? null; ?>
        <div class="card link-row">
          <div style="flex:1">
            <div style="margin-bottom:8px">
              <strong style="font-size:1.05rem">🎯 <?= htmlspecialchars($link['target_url']) ?></strong>
            </div>
            <div class="small" style="margin-bottom:12px">
              🆔 <?= htmlspecialchars(substr($link['id'],0,8)) ?>… | 
              📍 <?= number_format($link['lat'], 4) ?>, <?= number_format($link['lng'], 4) ?> | 
              📏 <?= $link['radius'] ?>m radius | 
              ⏰ Expires: <?= htmlspecialchars($link['expires']) ?>
            </div>
            <div class="stats">
              <div class="stat">🔄 Attempts: <strong><?= $stat['total_attempts'] ?? 0 ?></strong></div>
              <div class="stat" style="border-color:rgba(16,185,129,0.2)">✅ Success: <strong><?= $stat['successful_access'] ?? 0 ?></strong></div>
              <div class="stat" style="border-color:rgba(239,68,68,0.2)">❌ Failed: <strong><?= $stat['failed_access'] ?? 0 ?></strong></div>
              <?php if ($stat && $stat['last_access']): ?>
                <div class="stat">🕐 Last: <strong><?= date('M d, H:i', strtotime($stat['last_access'])) ?></strong></div>
              <?php endif; ?>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:8px;min-width:120px">
            <?php $token = jwt_sign(['sub'=>'geo-fence-link','jti'=>$link['id'],'lat'=>$link['lat'],'lng'=>$link['lng'],'radius'=>$link['radius'],'target_url'=>$link['target_url'],'exp'=>strtotime($link['expires'])]);
                  $url = rtrim($_ENV['APP_URL'] ?? (($_SERVER['REQUEST_SCHEME'] ?? 'http').'://'.$_SERVER['HTTP_HOST']), '/') . '/redirect.php?token=' . $token;
            ?>
            <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="badge" style="text-align:center">🔗 Open Link</a>
            <a href="<?= htmlspecialchars(generate_qr_code_url($url)) ?>" download="qr-<?= htmlspecialchars(substr($link['id'],0,8)) ?>.png" class="badge" style="text-align:center">📱 QR Code</a>
            <form method="POST" style="margin:0">
              <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= htmlspecialchars($link['id']) ?>">
              <button type="submit" class="ghost" onclick="return confirm('Are you sure you want to delete this link?')" style="font-size:0.85rem;padding:6px 12px">🗑️ Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <div class="footer" style="margin-top:32px;padding:20px;text-align:center;color:var(--text-muted)">
      <p class="small">Geo-Fence Link Generator &copy; <?= date('Y') ?></p>
    </div>
  </div>
</body>
</html>
