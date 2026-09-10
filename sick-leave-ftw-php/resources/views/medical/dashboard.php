<?php
/**
 * Occupational Health dashboard: KPIs, employee lookup, all-cases activity feed.
 *
 * @var string               $portal
 * @var array<string,int>    $kpis
 * @var array<int,array{ts:string,text:string}> $activity
 * @var string               $search
 * @var array<int,array<string,mixed>> $matches
 */
?>
<div class="kpi-row">
  <div class="kpi amber"><div class="num"><?= e((string) $kpis['pending_review']) ?></div><div class="lbl"><?= te('kpi_pending_review') ?></div></div>
  <div class="kpi"><div class="num"><?= e((string) $kpis['rtw_pending']) ?></div><div class="lbl"><?= te('kpi_rtw_pending') ?></div></div>
  <div class="kpi green"><div class="num"><?= e((string) $kpis['rtw_done']) ?></div><div class="lbl"><?= te('kpi_rtw_done') ?></div></div>
  <div class="kpi"><div class="num"><?= e((string) $kpis['ftw_pending']) ?></div><div class="lbl"><?= te('kpi_ftw_pending') ?></div></div>
  <div class="kpi green"><div class="num"><?= e((string) $kpis['ftw_done']) ?></div><div class="lbl"><?= te('kpi_ftw_done') ?></div></div>
  <div class="kpi amber"><div class="num"><?= e((string) $kpis['expiring_soon']) ?></div><div class="lbl"><?= te('kpi_expiring_soon') ?></div></div>
  <div class="kpi amber"><div class="num"><?= e((string) $kpis['flags']) ?></div><div class="lbl"><?= te('kpi_flags') ?></div></div>
  <div class="kpi red"><div class="num"><?= e((string) $kpis['sla_breach']) ?></div><div class="lbl"><?= te('kpi_sla_breach') ?></div></div>
</div>

<div class="card">
  <h3><?= te('dash_lookup_title') ?></h3>

  <form method="get" action="<?= e(url()) ?>">
    <input type="hidden" name="p" value="<?= e($portal) ?>">
    <input type="hidden" name="r" value="tab">
    <input type="hidden" name="t" value="mdash">
    <div class="form-row">
      <div>
        <label for="q"><?= te('lbl_search_employee') ?></label>
        <input type="text" id="q" name="q" value="<?= e($search) ?>" placeholder="<?= topte('dash_lookup_ph') ?>">
      </div>
      <div class="flex-none">
        <button type="submit" class="btn secondary"><?= topte('btn_lookup') ?></button>
      </div>
    </div>
  </form>

  <?php if ($search !== ''): ?>
    <div class="mt-12" id="searchResults">
      <?php if ($matches === []): ?>
        <div class="empty"><?= te('empty_no_cases') ?></div>
      <?php else: ?>
        <?php foreach ($matches as $match): ?>
          <div class="hist-item">
            <b><?= e($match['name']) ?></b> — <?= e($match['dept'] . ' (' . $match['id'] . ')') ?>
            &nbsp;
            <a class="link" href="<?= e(url(['p' => $portal, 'r' => 'patient', 'id' => $match['id']])) ?>"><?= topte('btn_lookup') ?></a>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h3><?= te('dash_recent_activity') ?></h3>
  <?php if ($activity === []): ?>
    <div class="empty"><?= te('empty_no_items') ?></div>
  <?php else: ?>
    <?php foreach ($activity as $item): ?>
      <div class="hist-item">
        <div class="ts"><?= e(fmt_dt($item['ts'])) ?></div>
        <?= e($item['text']) ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
