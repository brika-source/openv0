<?php
/**
 * Application shell: header, tab bar, content, footer, toasts.
 *
 * @var array<string,mixed>            $user
 * @var string                         $portal
 * @var string                         $role
 * @var string                         $active_tab
 * @var list<array{0:string,1:string}> $tabs
 * @var int                            $unread
 * @var array<int,array{message:string,type:string}> $flashes
 * @var string                         $page_title
 * @var string                         $brand_title
 * @var string                         $brand_tag
 * @var string                         $content
 * @var string                         $app_version
 * @var string                         $other_portal
 */
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($page_title) ?></title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<div id="app">
  <header class="top">
    <div class="brand">
      <svg width="28" height="28" viewBox="0 0 34 34" aria-hidden="true"><rect width="34" height="34" rx="8" fill="#ffffff" opacity=".15"/>
        <path d="M17 7v20M9 12c0 3 2 5 8 5s8-2 8-5M9 22c0-3 2-5 8-5s8 2 8 5" stroke="#fff" stroke-width="1.6" fill="none" stroke-linecap="round"/></svg>
      <div>
        <h1><?= e($brand_title) ?></h1>
        <div class="tag"><?= e($brand_tag) ?></div>
      </div>
    </div>

    <div class="user-box">
      <a class="header-link" href="<?= e(url(['p' => $other_portal])) ?>"><?= topte('switch_portal') ?></a>
      <a class="header-link" href="<?= e(url(['p' => $portal, 'r' => 'account'])) ?>"><?= topte('tab_account') ?></a>

      <a class="bell-link" href="<?= e(url(['p' => $portal, 'r' => 'notifications'])) ?>"
         title="<?= topte('notif_title') ?>" aria-label="<?= topte('notif_title') ?>">🔔<?php
        if ($unread > 0): ?><span class="badge"><?= e($unread > 99 ? '99+' : (string) $unread) ?></span><?php endif; ?></a>

      <div class="user-chip"><span class="dot"></span><span><?= e($user['name'] . ' · ' . App\I18n::topt('role_' . $role)) ?></span></div>

      <form method="post" action="<?= e(url()) ?>" class="inline">
        <?= csrf() ?>
        <input type="hidden" name="p" value="<?= e($portal) ?>">
        <input type="hidden" name="r" value="logout">
        <button type="submit" class="btn ghost sm"><?= te('sign_out') ?></button>
      </form>
    </div>
  </header>

  <nav class="tabs">
    <?php foreach ($tabs as [$tabName, $labelKey]): ?>
      <a href="<?= e(url(['p' => $portal, 'r' => 'tab', 't' => $tabName])) ?>"
         class="<?= $tabName === $active_tab ? 'active' : '' ?>"
         <?= $tabName === $active_tab ? 'aria-current="page"' : '' ?>><?= te($labelKey) ?></a>
    <?php endforeach; ?>
  </nav>

  <main><?= $content ?></main>

  <footer class="app-footer">
    <?= te('footer_text') ?> · v<?= e($app_version) ?>
  </footer>
</div>

<?php if ($flashes !== []): ?>
<div class="toast-wrap">
  <?php foreach ($flashes as $flash): ?>
    <div class="toast <?= e($flash['type']) ?>"><?= enl($flash['message']) ?></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script src="assets/js/app.js"></script>
</body>
</html>
