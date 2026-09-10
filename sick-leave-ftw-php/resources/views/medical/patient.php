<?php
/**
 * Patient profile — the full story for one employee, reached from the dashboard
 * lookup or the pattern-flag list.
 *
 * @var array<string,mixed> $story
 * @var array<string,mixed> $employee
 * @var array<string,mixed> $user
 * @var string              $portal
 */
use App\Domain;
use App\Http\View;
?>
<div class="breadcrumb">
  <a href="<?= e(url(['p' => $portal, 'r' => 'tab', 't' => App\Http\Tabs::defaultFor((string) $user['role'])])) ?>">←
    <?= topte('title_dashboard') ?></a>
</div>

<div class="card">
  <h3><?= te('modal_patient_profile') ?></h3>

  <?= View::render('partials/patient-story', ['story' => $story, 'portal' => $portal]) ?>

  <?php if (in_array((string) $user['role'], [Domain::ROLE_MEDICAL, Domain::ROLE_HR], true)): ?>
    <hr class="divider">
    <a class="btn block" href="<?= e(url(['p' => $portal, 'r' => 'tab', 't' => 'ftwinit', 'emp' => $employee['id']])) ?>">
      <?= te('btn_init_ftw_any') ?>
    </a>
  <?php endif; ?>
</div>
