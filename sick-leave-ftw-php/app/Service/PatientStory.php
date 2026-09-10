<?php

declare(strict_types=1);

namespace App\Service;

use App\Repo\Cases;
use App\Repo\Ftw;
use App\Repo\Users;

/**
 * The unified "patient story" — one employee's whole sick leave and
 * fit-to-work history plus their balance and any pattern flag.
 *
 * Reused by the medical review screen, the RTW screen, the FTW assessment screen
 * and the patient profile lookup, which is exactly how the prototype's
 * patientStoryHtml() was used.
 *
 * @return array{employee:array<string,mixed>|null,balance:array<string,int>,flag:array{flag:bool,reason:string},cases:array,ftws:array}
 */
final class PatientStory
{
    /** @return array<string,mixed> */
    public static function for(string $employeeId): array
    {
        return [
            'employee' => Users::find($employeeId),
            'balance'  => Analytics::balance($employeeId),
            'flag'     => Analytics::patternFlag($employeeId),
            'cases'    => Cases::forEmployee($employeeId),
            'ftws'     => Ftw::forEmployee($employeeId),
        ];
    }
}
