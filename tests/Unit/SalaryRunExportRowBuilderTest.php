<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\SalaryRunItem;
use App\Services\SalaryRunExportRowBuilder;

beforeEach(function (): void {
    $this->builder = new SalaryRunExportRowBuilder;
});

it('builds live snapshots from current employee allowance fields', function (): void {
    $employee = new Employee([
        'first_name' => 'Ahmed',
        'father_name' => 'Mohammed',
        'last_name' => 'Ali',
        'basic_salary' => 10000,
        'allowances' => 3000,
        'allowance_housing' => 2000,
        'allowance_transportation' => 500,
        'allowance_other' => 300,
        'allowance_food' => 200,
        'allowance_personal_car' => 0,
    ]);

    $item = new SalaryRunItem([
        'basic_salary' => 10000,
        'allowances' => 3000,
        'unpaid_leave_total' => 0,
        'social_insurance_deduction_total' => 100,
        'net_salary' => 13900,
        'breakdown' => [],
        'debt_deductions' => [],
    ]);

    $snapshot = $this->builder->buildLiveSnapshot($item, $employee);

    expect($snapshot['employee_name'])->toBe('Ahmed Mohammed Ali')
        ->and($snapshot['basic_salary'])->toBe(10000.0)
        ->and($snapshot['housing'])->toBe(2000.0)
        ->and($snapshot['transportation'])->toBe(500.0)
        ->and($snapshot['other'])->toBe(300.0)
        ->and($snapshot['food'])->toBe(200.0)
        ->and($snapshot['additional_allowances'])->toBe(0.0);
});

it('builds frozen snapshots without reading employee allowance fields', function (): void {
    $employee = new Employee([
        'first_name' => 'Sara',
        'last_name' => 'Hassan',
        'basic_salary' => 12000,
        'allowances' => 5000,
        'allowance_housing' => 4000,
        'allowance_transportation' => 1000,
    ]);

    $item = new SalaryRunItem([
        'basic_salary' => 8000,
        'allowances' => 2000,
        'unpaid_leave_total' => 0,
        'social_insurance_deduction_total' => 50,
        'net_salary' => 9950,
        'breakdown' => [
            [
                'source' => 'manual_addition',
                'amount' => 150,
            ],
        ],
        'debt_deductions' => [],
    ]);

    $snapshot = $this->builder->buildFrozenSnapshotFromItem($item, $employee);

    expect($snapshot['basic_salary'])->toBe(8000.0)
        ->and($snapshot['housing'])->toBe(0.0)
        ->and($snapshot['transportation'])->toBe(0.0)
        ->and($snapshot['additional_allowances'])->toBe(2150.0)
        ->and($snapshot['net_salary'])->toBe(9950.0);
});

it('uses stored snapshots for finalized excel rows without employee allowance drift', function (): void {
    $employee = new Employee([
        'first_name' => 'Old',
        'last_name' => 'Name',
        'allowance_housing' => 9999,
    ]);

    $item = new SalaryRunItem([
        'export_snapshot' => [
            'employee_name' => 'Locked Name',
            'basic_salary' => 7000.0,
            'housing' => 1500.0,
            'transportation' => 250.0,
            'transfers' => 0.0,
            'other' => 0.0,
            'food' => 0.0,
            'personal_car' => 0.0,
            'additional_allowances' => 100.0,
            'unpaid_leave' => 0.0,
            'debts' => 0.0,
            'traffic_violations' => 0.0,
            'penalties' => 0.0,
            'social_insurance' => 0.0,
            'absences' => 0.0,
            'attestations' => 0.0,
            'net_salary' => 8850.0,
        ],
    ]);
    $item->setRelation('employee', $employee);

    $row = $this->builder->snapshotToExcelRow($item->export_snapshot);

    expect($row[0])->toBe('Locked Name')
        ->and($row[2])->toBe(1500.0)
        ->and($row[16])->toBe(8850.0);
});
