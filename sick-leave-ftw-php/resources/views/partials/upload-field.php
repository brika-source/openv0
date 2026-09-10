<?php
/**
 * A multi-file upload input with the size/type hint.
 *
 * @var string $name
 * @var string $label   label key
 */
?>
<label for="<?= e($name) ?>"><?= te($label) ?></label>
<input type="file" id="<?= e($name) ?>" name="<?= e($name) ?>[]" multiple>
<div class="conf-note">
  <?= topte('upload_hint') ?> <?= e(App\Service\Uploads::maxSizeLabel()) ?>.
  <?= topte('note_docs_real') ?>
</div>
