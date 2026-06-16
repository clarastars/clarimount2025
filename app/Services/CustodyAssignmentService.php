<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetTemplate;
use App\Models\CustodyChange;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CustodyAssignmentService
{
    /**
     * @param  array<int>  $accessibleCompanyIds
     */
    public function updateEmployeeCustody(
        Employee $employee,
        User $user,
        array $newAssetIds,
        ?string $changesSummary,
        array $accessibleCompanyIds
    ): CustodyChange {
        return DB::transaction(function () use ($employee, $user, $newAssetIds, $changesSummary, $accessibleCompanyIds) {
            $currentAssets = $employee->assets()
                ->with(['assetCategory', 'location', 'company'])
                ->where('status', 'assigned')
                ->get();

            $newAssets = $newAssetIds !== []
                ? Asset::query()
                    ->whereIn('id', $newAssetIds)
                    ->whereIn('company_id', $accessibleCompanyIds)
                    ->with(['assetCategory', 'location', 'company'])
                    ->get()
                : collect();

            $currentAssetIds = $currentAssets->pluck('id')->toArray();

            $assetsToAdd = $newAssets->filter(
                fn (Asset $asset) => ! in_array($asset->id, $currentAssetIds, true)
            );

            foreach ($assetsToAdd as $asset) {
                if ($asset->status !== 'available') {
                    throw new \RuntimeException(
                        __('messages.custody.asset_not_available', ['tag' => $asset->asset_tag])
                    );
                }
            }

            $previousState = [
                'assets' => $currentAssets->map(fn (Asset $asset) => $this->serializeAssetState($asset))->toArray(),
                'count' => $currentAssets->count(),
            ];

            $newState = [
                'assets' => $newAssets->map(fn (Asset $asset) => [
                    ...$this->serializeAssetState($asset),
                    'status' => 'assigned',
                ])->toArray(),
                'count' => $newAssets->count(),
            ];

            $custodyChange = CustodyChange::query()->create([
                'employee_id' => $employee->id,
                'updated_by' => $user->id,
                'previous_state' => $previousState,
                'new_state' => $newState,
                'changes_summary' => $changesSummary,
                'document_path' => null,
                'status' => 'pending',
            ]);

            $newAssetIdList = $newAssets->pluck('id')->toArray();

            $assetsToReturn = $currentAssets->filter(
                fn (Asset $asset) => ! in_array($asset->id, $newAssetIdList, true)
            );

            $assetsToAssign = $newAssets->filter(
                fn (Asset $asset) => ! in_array($asset->id, $currentAssetIds, true)
            );

            foreach ($assetsToReturn as $asset) {
                $this->returnAssetFromEmployee($asset, $employee, $user, $custodyChange);
            }

            foreach ($assetsToAssign as $asset) {
                $this->assignAssetToEmployee($asset, $employee, $user, $custodyChange);
            }

            return $custodyChange->load(['updatedBy']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createAvailableAssetForCompany(int $companyId, array $data): Asset
    {
        $template = AssetTemplate::query()->findOrFail($data['asset_template_id']);

        if (! $template->asset_category_id) {
            throw new \RuntimeException(__('messages.custody.template_missing_category'));
        }

        $location = Location::query()
            ->whereKey($data['location_id'])
            ->where('is_active', true)
            ->first();

        if (! $location) {
            throw new \RuntimeException(__('messages.custody.invalid_location'));
        }

        $asset = Asset::query()->create([
            'company_id' => $companyId,
            'asset_category_id' => $template->asset_category_id,
            'location_id' => $location->id,
            'asset_template_id' => $template->id,
            'serial_number' => $data['serial_number'] ?? null,
            'condition' => $data['condition'] ?? 'good',
            'model_name' => $template->model_name,
            'model_number' => $template->model_number,
            'manufacturer' => $template->manufacturer,
            'notes' => $template->default_notes,
            'status' => 'available',
        ]);

        $template->increment('usage_count');

        return $asset->load(['assetCategory', 'location', 'company', 'assetTemplate']);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAssetState(Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'asset_tag' => $asset->asset_tag,
            'model_name' => $asset->model_name,
            'model_number' => $asset->model_number,
            'serial_number' => $asset->serial_number,
            'category_name' => $asset->assetCategory->name ?? null,
            'location_name' => $asset->location->name ?? null,
            'status' => $asset->status,
            'condition' => $asset->condition,
        ];
    }

    private function returnAssetFromEmployee(
        Asset $asset,
        Employee $employee,
        User $user,
        CustodyChange $custodyChange
    ): void {
        $activeAssignment = AssetAssignment::query()
            ->where('asset_id', $asset->id)
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->first();

        if ($activeAssignment) {
            $activeAssignment->update([
                'returned_date' => now(),
                'returned_by' => $user->id,
                'status' => 'returned',
                'return_notes' => 'Returned due to custody change',
                'custody_change_id' => $custodyChange->id,
            ]);
        } else {
            AssetAssignment::query()->create([
                'asset_id' => $asset->id,
                'employee_id' => $employee->id,
                'assigned_by' => $user->id,
                'assigned_date' => $asset->assigned_date ?? now(),
                'returned_date' => now(),
                'returned_by' => $user->id,
                'status' => 'returned',
                'return_notes' => 'Returned due to custody change',
                'custody_change_id' => $custodyChange->id,
            ]);
        }

        $asset->update([
            'assigned_to' => null,
            'assigned_date' => null,
            'status' => 'available',
        ]);
    }

    private function assignAssetToEmployee(
        Asset $asset,
        Employee $employee,
        User $user,
        CustodyChange $custodyChange
    ): void {
        AssetAssignment::query()->create([
            'asset_id' => $asset->id,
            'employee_id' => $employee->id,
            'assigned_by' => $user->id,
            'assigned_date' => now(),
            'status' => 'active',
            'assignment_notes' => 'Assigned due to custody change',
            'custody_change_id' => $custodyChange->id,
        ]);

        $asset->update([
            'assigned_to' => $employee->id,
            'assigned_date' => now(),
            'status' => 'assigned',
        ]);
    }
}
