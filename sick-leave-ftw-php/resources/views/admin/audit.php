<?php
/**
 * The append-only audit trail.
 *
 * @var string $portal
 * @var array<int,array<string,mixed>> $entries
 * @var int $page
 * @var int $pages
 * @var int $total
 */
use App\Helpers;
?>
<div class="banner info"><?= te('audit_note') ?></div>

<div class="card">
  <h3><?= te('title_audit') ?></h3>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th><?= te('th_when') ?></th>
          <th><?= te('th_actor2') ?></th>
          <th><?= te('th_action2') ?></th>
          <th><?= te('th_entity') ?></th>
          <th><?= te('th_detail') ?></th>
          <th><?= te('th_ip') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($entries === []): ?>
          <tr><td colspan="6" class="empty"><?= te('empty_no_audit') ?></td></tr>
        <?php else: ?>
          <?php foreach ($entries as $entry): ?>
            <tr>
              <td class="nowrap"><?= e(Helpers::fmtFull((string) $entry['ts'])) ?></td>
              <td class="nowrap">
                <?= e($entry['user_name'] ?? Helpers::EM_DASH) ?>
                <?php if (($entry['role'] ?? null) !== null): ?>
                  <div class="doc-meta"><?= e($entry['role']) ?><?php
                    if (($entry['portal'] ?? null) !== null) { echo ' · ' . e($entry['portal']); }
                  ?></div>
                <?php endif; ?>
              </td>
              <td class="nowrap"><code><?= e($entry['action']) ?></code></td>
              <td class="nowrap"><?= e(($entry['entity_id'] ?? '') !== '' ? $entry['entity_id'] : Helpers::EM_DASH) ?></td>
              <td><?= e($entry['detail'] ?? '') ?></td>
              <td class="nowrap"><?= e($entry['ip'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
    <div class="pager">
      <span><?= topte('lbl_showing') ?> <?= e((string) $total) ?> <?= topte('lbl_records') ?></span>
      <?php if ($page > 1): ?>
        <a href="<?= e(url(['p' => $portal, 'r' => 'tab', 't' => 'audit', 'page' => $page - 1])) ?>"><?= topte('btn_prev') ?></a>
      <?php endif; ?>
      <span class="current"><?= e((string) $page) ?> / <?= e((string) $pages) ?></span>
      <?php if ($page < $pages): ?>
        <a href="<?= e(url(['p' => $portal, 'r' => 'tab', 't' => 'audit', 'page' => $page + 1])) ?>"><?= topte('btn_next') ?></a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
