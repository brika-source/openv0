<?php
/**
 * Printable / PDF-ready case record.
 *
 * Opens in its own tab with no application chrome; the browser's own
 * "Print → Save as PDF" produces the archival document. That replaces the
 * prototype's document.write() pop-up, which could not be styled reliably or
 * blocked-popup-proofed.
 *
 * @var array<string,mixed>      $case
 * @var array<string,mixed>|null $employee
 * @var array<string,mixed>      $generator
 */
use App\Helpers;
use App\I18n;
use App\Repo\Cases;
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($case['case_id']) ?> — <?= e(I18n::en('print_case_record')) ?></title>
<style>
  body{font-family:'Segoe UI',Tahoma,'Noto Kufi Arabic',Arial,sans-serif;padding:30px;color:#17212E;
    font-size:13px;line-height:1.5;max-width:900px;margin:0 auto;}
  h1{color:#0B4C8C;font-size:18px;margin:0 0 4px;}
  .sub{color:#64748B;font-size:11.5px;margin-bottom:18px;}
  table{width:100%;border-collapse:collapse;margin-top:10px;}
  td,th{border:1px solid #ccc;padding:6px 8px;font-size:12px;text-align:start;vertical-align:top;}
  th{background:#F0F3F8;font-weight:700;}
  dl{display:grid;grid-template-columns:auto 1fr;gap:4px 14px;margin:0 0 16px;}
  dt{font-weight:700;color:#475569;}
  dd{margin:0;}
  .toolbar{margin-bottom:18px;}
  .toolbar button{font:inherit;padding:7px 15px;border:1px solid #0B4C8C;background:#0B4C8C;color:#fff;
    border-radius:6px;cursor:pointer;}
  h2{font-size:14px;margin:22px 0 4px;color:#0B4C8C;}
  @media print{.toolbar{display:none;} body{padding:0;}}
</style>
</head>
<body>

<div class="toolbar"><button type="button" data-print><?= e(I18n::en('btn_print')) ?></button></div>

<h1><?= topte('print_case_record') ?> <?= e($case['case_id']) ?></h1>
<div class="sub">
  <?= topte('lbl_generated_at') ?> <?= e(Helpers::fmtFull(Helpers::now())) ?>
  · <?= topte('lbl_generated_by') ?> <?= e($generator['name']) ?> (<?= e(I18n::roleLabel((string) $generator['role'])) ?>)
</div>

<dl>
  <dt><?= topte('print_employee') ?></dt>
  <dd><?= e(($employee['name'] ?? '') . ' (' . $case['employee_id'] . ') — ' . ($employee['dept'] ?? '')) ?></dd>

  <dt><?= topte('print_diagnosis') ?></dt>
  <dd><?= e($case['diagnosis']) ?> (<?= e(I18n::specialtyLabel((string) $case['specialty'])) ?>)</dd>

  <dt><?= topte('print_dates') ?></dt>
  <dd><?= e(fmt_date((string) $case['from_date'])) ?> – <?= e(fmt_date((string) $case['to_date'])) ?>
      (<?= e((string) Cases::totalDays($case)) ?> <?= topte('days_word') ?>)</dd>

  <dt><?= topte('print_status') ?></dt>
  <dd><?= e(I18n::statusLabel((string) $case['status'])) ?></dd>

  <dt><?= topte('print_documents') ?></dt>
  <dd><?php
    if ($case['documents'] === []) {
        echo topte('none_word');
    } else {
        $names = [];
        foreach ($case['documents'] as $document) {
            $names[] = $document['name'] . ' v' . $document['version'];
        }
        echo e(implode(', ', $names));
    }
  ?></dd>

  <dt><?= topte('th_reviewers') ?></dt>
  <dd><?= e(Cases::reviewersOf($case)) ?></dd>
</dl>

<?php if (($case['restriction_details'] ?? null) !== null): ?>
  <h2><?= topte('lbl_restrictions') ?></h2>
  <p><?= e($case['restriction_details']) ?><br>
    <?= topte('lbl_review_date') ?> <?= e(fmt_date((string) $case['restriction_review_date'])) ?></p>
<?php endif; ?>

<?php if ($case['extensions'] !== []): ?>
  <h2><?= topte('lbl_new_extended_to') ?></h2>
  <table>
    <tr><th><?= topte('lbl_from') ?></th><th><?= topte('lbl_to') ?></th><th><?= topte('print_timestamp') ?></th></tr>
    <?php foreach ($case['extensions'] as $extension): ?>
      <tr>
        <td><?= e(fmt_date((string) $extension['from_date'])) ?></td>
        <td><?= e(fmt_date((string) $extension['to_date'])) ?></td>
        <td><?= e(Helpers::fmtFull((string) $extension['ts'])) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>

<h2><?= topte('history_title') ?></h2>
<table>
  <tr>
    <th><?= topte('print_timestamp') ?></th>
    <th><?= topte('print_actor') ?></th>
    <th><?= topte('print_action') ?></th>
    <th><?= topte('print_comment') ?></th>
  </tr>
  <?php foreach ($case['history'] as $entry): ?>
    <tr>
      <td><?= e(Helpers::fmtFull((string) $entry['ts'])) ?></td>
      <td><?= e($entry['actor']) ?></td>
      <td><?= e($entry['action']) ?></td>
      <td><?= e($entry['comment'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<script src="assets/js/app.js"></script>
</body>
</html>
