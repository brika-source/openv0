<?php
/**
 * Reports & Audit (Module C) — multi-select filters, the filtered case table,
 * CSV export, printable records, and the FTW register.
 *
 * The filters are a GET form, so a filtered view is a shareable, bookmarkable
 * URL and the CSV button exports exactly what is on screen.
 *
 * @var string $portal
 * @var array<string,mixed> $filters
 * @var array<int,array<string,mixed>> $rows
 * @var array<int,array<string,mixed>> $ftw_rows
 * @var array<int,array<string,mixed>> $employees
 * @var list<string> $departments
 * @var list<string> $statuses
 * @var list<string> $specialties
 * @var int $total
 */
use App\Domain;
use App\I18n;

/** Renders one checkbox filter list. */
$group = static function (string $name, string $field, array $options, array $selected): string {
    $html = '<div class="cbox-list" data-count-group="' . e($name) . '">';
    foreach ($options as $value => $label) {
        $checked = in_array((string) $value, $selected, true) ? ' checked' : '';
        $html .= '<label><input type="checkbox" name="' . e($field) . '[]" value="'
            . e((string) $value) . '"' . $checked . '> ' . e($label) . '</label>';
    }
    return $html . '</div>';
};

$employeeOptions = [];
foreach ($employees as $employee) {
    $employeeOptions[(string) $employee['id']] = $employee['name'] . ' (' . $employee['id'] . ')';
}

$deptOptions = [];
foreach ($departments as $dept) {
    $deptOptions[$dept] = $dept;
}

$statusOptions = [];
foreach ($statuses as $status) {
    $statusOptions[$status] = I18n::statusLabel($status);
}

$specialtyOptions = [];
foreach ($specialties as $specialty) {
    $specialtyOptions[$specialty] = I18n::specialtyLabel($specialty);
}

// Carry the active filters onto the CSV link.
$csvParams = ['p' => $portal, 'r' => 'report.csv'] + [
    'employees'   => $filters['employees'],
    'depts'       => $filters['depts'],
    'statuses'    => $filters['statuses'],
    'specialties' => $filters['specialties'],
    'from'        => $filters['from'],
    'to'          => $filters['to'],
];
?>
<div class="card">
  <h3><?= te('title_reports') ?></h3>
  <div class="mb-8 conf-note"><?= te('multiselect_hint') ?></div>

  <form method="get" action="<?= e(url()) ?>">
    <input type="hidden" name="p" value="<?= e($portal) ?>">
    <input type="hidden" name="r" value="tab">
    <input type="hidden" name="t" value="reports">

    <div class="filters">
      <div>
        <label><?= te('filter_employee') ?> <span class="cbox-count" id="cnt_emp"></span></label>
        <?= $group('emp', 'employees', $employeeOptions, $filters['employees']) ?>
      </div>
      <div>
        <label><?= te('filter_dept') ?> <span class="cbox-count" id="cnt_dept"></span></label>
        <?= $group('dept', 'depts', $deptOptions, $filters['depts']) ?>
      </div>
      <div>
        <label><?= te('filter_status') ?> <span class="cbox-count" id="cnt_status"></span></label>
        <?= $group('status', 'statuses', $statusOptions, $filters['statuses']) ?>
      </div>
      <div>
        <label><?= te('filter_specialty') ?> <span class="cbox-count" id="cnt_spec"></span></label>
        <?= $group('spec', 'specialties', $specialtyOptions, $filters['specialties']) ?>
      </div>
      <div>
        <label for="from"><?= te('filter_from') ?></label>
        <input type="date" id="from" name="from" value="<?= e($filters['from']) ?>">
      </div>
      <div>
        <label for="to"><?= te('filter_to') ?></label>
        <input type="date" id="to" name="to" value="<?= e($filters['to']) ?>">
      </div>
    </div>

    <div class="mb-14 row-actions">
      <button type="submit" class="btn secondary sm"><?= te('btn_apply_filters') ?></button>
      <a class="btn ghost sm" href="<?= e(url(['p' => $portal, 'r' => 'tab', 't' => 'reports'])) ?>"><?= te('btn_clear_filters') ?></a>
      <a class="btn sm" href="<?= e(url($csvParams)) ?>"><?= te('btn_export_csv') ?></a>
    </div>
  </form>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th><?= te('th_case') ?></th>
          <th><?= te('th_employee') ?></th>
          <th><?= te('th_dept') ?></th>
          <th><?= te('th_diagnosis') ?></th>
          <th><?= te('filter_specialty') ?></th>
          <th><?= te('th_dates') ?></th>
          <th><?= te('th_status') ?></th>
          <th><?= te('th_reviewers') ?></th>
          <th><?= te('th_docs2') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rows === []): ?>
          <tr><td colspan="10" class="empty"><?= te('empty_no_match') ?></td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php $case = $row['case']; $employee = $row['employee']; ?>
            <tr>
              <td class="nowrap"><?= e($case['case_id']) ?></td>
              <td class="nowrap"><?= e(($employee['name'] ?? '') . ' (' . $case['employee_id'] . ')') ?></td>
              <td><?= e($employee['dept'] ?? '') ?></td>
              <td><?= e($case['diagnosis']) ?></td>
              <td><?= e(I18n::specialtyLabel((string) $case['specialty'])) ?></td>
              <td class="nowrap"><?= e(fmt_date((string) $case['from_date'])) ?>–<?= e(fmt_date((string) $case['to_date'])) ?></td>
              <td><?= badge(I18n::statusLabel((string) $case['status']), Domain::statusBadgeClass((string) $case['status'])) ?></td>
              <td><?= e($row['reviewers']) ?></td>
              <td><?= e((string) count($case['documents'])) ?></td>
              <td class="row-actions nowrap">
                <a class="link" href="<?= e(url(['p' => $portal, 'r' => 'case', 'id' => $case['case_id']])) ?>"><?= te('link_full_record') ?></a>
                ·
                <a class="link" href="<?= e(url(['p' => $portal, 'r' => 'case.print', 'id' => $case['case_id']])) ?>" target="_blank" rel="noopener"><?= te('link_pdf') ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="table-note">
    <?= topte('lbl_showing') ?> <?= e((string) $total) ?> <?= topte('lbl_records') ?>
  </div>
</div>

<div class="card">
  <h3><?= te('title_ftw_records') ?></h3>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th><?= te('th_id') ?></th>
          <th><?= te('th_employee') ?></th>
          <th><?= te('th_status4') ?></th>
          <th><?= te('th_requester') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($ftw_rows === []): ?>
          <tr><td colspan="5" class="empty"><?= te('empty_no_ftw') ?></td></tr>
        <?php else: ?>
          <?php foreach ($ftw_rows as $row): ?>
            <?php $record = $row['record']; $employee = $row['employee']; ?>
            <tr>
              <td class="nowrap"><?= e($record['id']) ?></td>
              <td class="nowrap"><?= e(($employee['name'] ?? '') . ' (' . $record['employee_id'] . ')') ?></td>
              <td><?= badge(I18n::ftwStatusLabel((string) $record['status']), Domain::ftwBadgeClass((string) $record['status'])) ?></td>
              <td><?= e($record['requester_label']) ?></td>
              <td class="nowrap">
                <a class="link" href="<?= e(url(['p' => $portal, 'r' => 'ftw', 'id' => $record['id']])) ?>"><?= te('link_view2') ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
