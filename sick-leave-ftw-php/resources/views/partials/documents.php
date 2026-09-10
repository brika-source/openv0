<?php
/**
 * Attachment list with download links.
 *
 * Seeded demo records carry filename metadata only (there is no file behind
 * them), so those are listed without a link rather than offering a download
 * that would 404.
 *
 * @var array<int,array<string,mixed>> $documents
 * @var string $portal
 * @var string $type      'case' | 'ftw'
 * @var bool   $can_download
 */
?>
<?php if ($documents === []): ?>
  <span class="muted"><?= topte('none_word') ?></span>
<?php else: ?>
  <ul class="doc-list">
    <?php foreach ($documents as $document): ?>
      <li>
        <span>
          <?= e($document['name']) ?>
          <?php if (isset($document['version'])): ?>
            <span class="doc-meta">(v<?= e((string) $document['version']) ?>)</span>
          <?php endif; ?>
        </span>
        <span class="doc-meta">
          <?= e(fmt_dt((string) $document['uploaded_at'])) ?>
          <?php if ((int) ($document['size_bytes'] ?? 0) > 0): ?>
            · <?= e(App\Helpers::humanBytes((int) $document['size_bytes'])) ?>
          <?php endif; ?>
          <?php if ($can_download && ($document['stored_name'] ?? null) !== null): ?>
            · <a class="link" href="<?= e(url(['p' => $portal, 'r' => 'download', 'type' => $type, 'id' => $document['id']])) ?>"><?= topte('link_download') ?></a>
          <?php endif; ?>
        </span>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
