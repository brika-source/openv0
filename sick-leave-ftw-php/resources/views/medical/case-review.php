<?php
/**
 * Screen A3/A4 — the full case record for Occupational Health (read-only for
 * HR and Admin), with the decision controls and, for a case awaiting clearance,
 * the return-to-work form.
 *
 * @var array<string,mixed>      $case
 * @var array<string,mixed>|null $employee
 * @var array<string,mixed>      $story
 * @var array<string,mixed>      $user
 * @var string                   $portal
 * @var bool                     $can_decide
 * @var bool                     $can_record_rtw
 * @var string                   $decision       pre-opened panel, from ?d=
 */
use App\Domain;
use App\Helpers;
use App\Http\View;
use App\I18n;
use App\Service\Analytics;

$status   = (string) $case['status'];
$sla      = Analytics::sla($case);
$backTab  = $status === Domain::STATUS_RTW_PENDING ? 'rtw' : 'review';
$formBase = ['p' => $portal];
?>
<div class="breadcrumb">
  <a href="<?= e(url(['p' => $portal, 'r' => 'tab', 't' => $backTab])) ?>">←
    <?= topte($backTab === 'rtw' ? 'title_rtw_queue' : 'title_review_queue') ?></a>
</div>

<div class="card">
  <div class="detail-head">
    <div>
      <h2><?= e($case['case_id']) ?> <span class="fs-13 muted"><?= topte('modal_review_suffix') ?></span></h2>
      <div class="meta">
        <?= badge(I18n::statusLabel($status), Domain::statusBadgeClass($status)) ?>
        <?php if ($sla !== null): ?>
          &nbsp;<span class="sla-<?= e($sla['level']) ?>"><?= enl($sla['label']) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <div class="btn-row no-print">
      <a class="btn ghost sm" href="<?= e(url(['p' => $portal, 'r' => 'case.print', 'id' => $case['case_id']])) ?>" target="_blank" rel="noopener">
        <?= topte('btn_print') ?>
      </a>
    </div>
  </div>

  <dl class="kv">
    <dt><?= topte('lbl_employee_colon') ?></dt>
    <dd><?= e(($employee['name'] ?? '') . ' (' . ($employee['dept'] ?? '') . ', ' . $case['employee_id'] . ')') ?></dd>
    <dt><?= topte('lbl_diagnosis_colon') ?></dt>
    <dd><?= e($case['diagnosis']) ?> <span class="muted">(<?= e(I18n::specialtyLabel((string) $case['specialty'])) ?>)</span></dd>
    <dt><?= topte('th_dates2') ?></dt>
    <dd><?= e(fmt_date((string) $case['from_date'])) ?> – <?= e(fmt_date((string) $case['to_date'])) ?>
      (<?= e((string) Helpers::daysBetween((string) $case['from_date'], (string) $case['to_date'])) ?>)</dd>
    <dt><?= topte('lbl_documents_v') ?></dt>
    <dd><?= View::render('partials/documents', [
        'documents'    => $case['documents'],
        'portal'       => $portal,
        'type'         => 'case',
        'can_download' => true,
    ]) ?></dd>
  </dl>

  <?php if ($case['extensions'] !== []): ?>
    <div class="banner warn">
      <b><?= topte('status_Extended – Not Fit') ?>:</b>
      <?php foreach ($case['extensions'] as $extension): ?>
        <?= e(fmt_date((string) $extension['from_date'])) ?> → <b><?= e(fmt_date((string) $extension['to_date'])) ?></b>&nbsp;
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (($case['restriction_details'] ?? null) !== null): ?>
    <div class="banner warn">
      <b><?= topte('lbl_restrictions') ?></b> <?= e($case['restriction_details']) ?><br>
      <span class="muted"><?= topte('lbl_review_date') ?> <?= e(fmt_date((string) $case['restriction_review_date'])) ?></span>
    </div>
  <?php endif; ?>

  <hr class="divider">
  <b><?= topte('patient_story_title') ?></b> <span class="muted"><?= topte('req_visibility_note') ?></span>
  <div class="mt-8">
    <?= View::render('partials/patient-story', ['story' => $story, 'portal' => $portal]) ?>
  </div>

  <hr class="divider">
  <b><?= te('case_activity_title') ?></b>
  <?= View::render('partials/history', ['entries' => $case['history']]) ?>

  <?php if ($can_decide): ?>
    <hr class="divider">
    <label><?= te('decision_title') ?></label>

    <div class="row-actions no-print">
      <button type="button" class="btn green" data-show-panel="approve"><?= te('btn_approve') ?></button>
      <button type="button" class="btn amber" data-show-panel="pending"><?= te('btn_mark_pending') ?></button>
      <button type="button" class="btn secondary" data-show-panel="comment"><?= te('btn_comment_request') ?></button>
      <button type="button" class="btn red" data-show-panel="reject"><?= te('btn_reject') ?></button>
    </div>

    <!-- Approve -->
    <div class="decision-panel" data-panel="approve" <?= $decision === 'approve' ? '' : 'hidden' ?>>
      <form method="post" action="<?= e(url()) ?>" data-guard>
        <?= csrf() ?>
        <input type="hidden" name="p" value="<?= e($portal) ?>">
        <input type="hidden" name="r" value="case.approve">
        <input type="hidden" name="id" value="<?= e($case['case_id']) ?>">

        <label for="rtw_required"><?= te('lbl_rtw_required') ?></label>
        <select id="rtw_required" name="rtw_required">
          <option value="no"><?= te('opt_rtw_no') ?></option>
          <option value="yes"><?= te('opt_rtw_yes') ?></option>
        </select>

        <label for="approve_note"><?= te('lbl_reviewer_note') ?></label>
        <textarea id="approve_note" name="note" rows="2"></textarea>

        <button type="submit" class="btn block green"><?= te('btn_confirm_approval') ?></button>
      </form>
    </div>

    <!-- Mark pending -->
    <div class="decision-panel" data-panel="pending" <?= $decision === 'pending' ? '' : 'hidden' ?>>
      <form method="post" action="<?= e(url()) ?>" data-guard>
        <?= csrf() ?>
        <input type="hidden" name="p" value="<?= e($portal) ?>">
        <input type="hidden" name="r" value="case.pending">
        <input type="hidden" name="id" value="<?= e($case['case_id']) ?>">

        <label class="req" for="pending_comment"><?= te('lbl_comment_employee') ?></label>
        <textarea id="pending_comment" name="comment" rows="2" required placeholder="<?= topte('ph_pending') ?>"></textarea>

        <button type="submit" class="btn block amber"><?= te('btn_send_pending') ?></button>
      </form>
    </div>

    <!-- Log comment -->
    <div class="decision-panel" data-panel="comment" <?= $decision === 'comment' ? '' : 'hidden' ?>>
      <form method="post" action="<?= e(url()) ?>" data-guard>
        <?= csrf() ?>
        <input type="hidden" name="p" value="<?= e($portal) ?>">
        <input type="hidden" name="r" value="case.comment">
        <input type="hidden" name="id" value="<?= e($case['case_id']) ?>">

        <label class="req" for="cmt_comment"><?= te('lbl_comment_request2') ?></label>
        <textarea id="cmt_comment" name="comment" rows="2" required placeholder="<?= topte('ph_comment') ?>"></textarea>

        <button type="submit" class="btn block secondary"><?= te('btn_log_comment') ?></button>
      </form>
    </div>

    <!-- Reject -->
    <div class="decision-panel" data-panel="reject" <?= $decision === 'reject' ? '' : 'hidden' ?>>
      <form method="post" action="<?= e(url()) ?>" data-guard>
        <?= csrf() ?>
        <input type="hidden" name="p" value="<?= e($portal) ?>">
        <input type="hidden" name="r" value="case.reject">
        <input type="hidden" name="id" value="<?= e($case['case_id']) ?>">

        <label class="req" for="reject_comment"><?= te('lbl_reject_reason') ?></label>
        <textarea id="reject_comment" name="comment" rows="2" required placeholder="<?= topte('ph_reject') ?>"></textarea>

        <button type="submit" class="btn block red"><?= te('btn_confirm_reject') ?></button>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($can_record_rtw): ?>
    <hr class="divider">
    <h3><?= topte('title_rtw_assessment') ?> <?= e($case['case_id']) ?></h3>
    <div class="mb-10 muted">
      <?= topte('lbl_original_leave') ?> <?= e(fmt_date((string) $case['from_date'])) ?> – <?= e(fmt_date((string) $case['to_date'])) ?>
    </div>

    <div class="decision-panel">
      <!-- Fit to return -->
      <form method="post" action="<?= e(url()) ?>" enctype="multipart/form-data" data-guard>
        <?= csrf() ?>
        <input type="hidden" name="p" value="<?= e($portal) ?>">
        <input type="hidden" name="r" value="case.rtw">
        <input type="hidden" name="id" value="<?= e($case['case_id']) ?>">
        <input type="hidden" name="outcome" value="fit">

        <label for="rtw_notes"><?= te('lbl_assessment_notes') ?></label>
        <textarea id="rtw_notes" name="notes" rows="2"></textarea>

        <?= View::render('partials/upload-field', ['name' => 'documents', 'label' => 'lbl_supporting_upload']) ?>

        <label><?= te('lbl_outcome') ?></label>
        <button type="submit" class="btn green"><?= te('btn_fit_return') ?></button>
      </form>

      <div class="mt-10 row-actions no-print">
        <button type="button" class="btn amber" data-show-panel="rtw-restricted"><?= te('btn_rtw_restricted') ?></button>
        <button type="button" class="btn red" data-show-panel="rtw-extend"><?= te('btn_not_fit_extend') ?></button>
      </div>
    </div>

    <!-- Returned with restrictions -->
    <div class="decision-panel" data-panel="rtw-restricted" hidden>
      <form method="post" action="<?= e(url()) ?>" enctype="multipart/form-data" data-guard>
        <?= csrf() ?>
        <input type="hidden" name="p" value="<?= e($portal) ?>">
        <input type="hidden" name="r" value="case.rtw">
        <input type="hidden" name="id" value="<?= e($case['case_id']) ?>">
        <input type="hidden" name="outcome" value="restricted">

        <label for="rtw_notes_r"><?= te('lbl_assessment_notes') ?></label>
        <textarea id="rtw_notes_r" name="notes" rows="2"></textarea>

        <label class="req" for="restriction_details_r"><?= te('lbl_restriction_details') ?></label>
        <textarea id="restriction_details_r" name="restriction_details" rows="2" required
                  placeholder="<?= topte('ph_restriction') ?>"></textarea>

        <label class="req" for="restriction_review_date_r"><?= te('lbl_review_expiry') ?></label>
        <input type="date" id="restriction_review_date_r" name="restriction_review_date" required>

        <?= View::render('partials/upload-field', ['name' => 'documents', 'label' => 'lbl_supporting_upload']) ?>

        <button type="submit" class="btn block amber"><?= te('btn_rtw_restricted') ?></button>
      </form>
    </div>

    <!-- Not fit — extend -->
    <div class="decision-panel" data-panel="rtw-extend" hidden>
      <form method="post" action="<?= e(url()) ?>" enctype="multipart/form-data" data-guard>
        <?= csrf() ?>
        <input type="hidden" name="p" value="<?= e($portal) ?>">
        <input type="hidden" name="r" value="case.rtw">
        <input type="hidden" name="id" value="<?= e($case['case_id']) ?>">
        <input type="hidden" name="outcome" value="notfit">

        <label for="rtw_notes_e"><?= te('lbl_assessment_notes') ?></label>
        <textarea id="rtw_notes_e" name="notes" rows="2"></textarea>

        <label class="req" for="extend_to"><?= te('lbl_new_extended_to') ?></label>
        <input type="date" id="extend_to" name="extend_to" required
               min="<?= e(date('Y-m-d', (int) strtotime((string) $case['to_date'] . ' +1 day'))) ?>">

        <?= View::render('partials/upload-field', ['name' => 'documents', 'label' => 'lbl_supporting_upload']) ?>

        <button type="submit" class="btn block red"><?= te('btn_confirm_extension') ?></button>
      </form>
    </div>
  <?php endif; ?>

  <?php if (!$can_decide && !$can_record_rtw): ?>
    <hr class="divider">
    <div class="muted-block"><?= topte('req_visibility_note') ?></div>
  <?php endif; ?>
</div>
