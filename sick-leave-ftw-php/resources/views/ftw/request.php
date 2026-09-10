<?php
/**
 * The "Initiate FTW" tab for managers, HR and the medical team.
 *
 * @var string                         $portal
 * @var array<string,mixed>            $user
 * @var array<int,array<string,mixed>> $candidates
 * @var list<string>                   $demands
 * @var string                         $preselect
 */
use App\Domain;
use App\Http\View;
?>
<?php if ((string) $user['role'] === Domain::ROLE_MANAGER): ?>
  <div class="banner info"><?= te('banner_confidential') ?></div>
<?php endif; ?>

<?= View::render('ftw/form', [
    'portal'         => $portal,
    'user'           => $user,
    'demands'        => $demands,
    'candidates'     => $candidates,
    'fixed_employee' => null,
    'preselect'      => $preselect,
]) ?>
