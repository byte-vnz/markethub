<?php
namespace App\Services;

use App\Http\Requests\BusinessRequest;
use App\Models\{Business, BusinessCategory, StallAssignment, Tenant, User};
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BusinessService
{
    public function save(User $actor, array $data, $id = null, $tenantId = null): Business
    {
        $this->authorize($actor);
        $scope = $tenantId;
        $tenantId = $scope ?? ($data['tenant_id'] ?? null);
        $data = Validator::make($data, BusinessRequest::fields())->validate();

        return DB::transaction(function () use ($actor, $data, $id, $scope, $tenantId) {
            $tenant = Tenant::whereKey($tenantId)->lockForUpdate()->firstOrFail();
            $query = Business::query();
            if ($scope !== null) {
                $query->where('tenant_id', $scope);
            }
            $business = $id ? $query->whereKey($id)->lockForUpdate()->firstOrFail() : new Business();
            if ($business->exists && (int)$business->tenant_id !== (int)$tenant->id) {
                throw ValidationException::withMessages(['tenant_id'=>'Use the existing tenant profile or the orphan reconciliation action.']);
            }
            if ($tenant->status === 'archived' && $data['status'] === 'active') {
                throw ValidationException::withMessages(['status'=>'Restore the tenant before activating a business.']);
            }
            if ($data['status'] !== 'active' && $business->exists && StallAssignment::where('business_id', $business->id)->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['status'=>'End the active stall assignments before archiving or deactivating this business.']);
            }
            $categoryId = $data['business_category_id'] ?? null;
            if (array_key_exists('business_category_id', $data) && $business->business_category_id && !$categoryId) {
                throw ValidationException::withMessages(['business_category_id'=>'Choose a replacement category to change the current category.']);
            }
            if ($categoryId && (int)$categoryId !== (int)$business->business_category_id && !BusinessCategory::whereKey($categoryId)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['business_category_id'=>'Choose an active business category.']);
            }
            $old = $business->getAttributes();
            $business->fill($data);
            if ($business->isDirty(['permit_from','permit_until']) && $business->permit_from && $business->permit_until && $business->permit_until < $business->permit_from) {
                throw ValidationException::withMessages(['permit_until'=>'The permit end date must be on or after its start date.']);
            }
            $business->tenant_id = $tenant->id;
            $business->updated_by = $actor->id;
            if (!$business->exists) {
                $business->created_by = $actor->id;
            }
            $changes = $business->getDirty();
            $business->save();
            if ($changes) {
                $this->invalidateDraft($tenant);
            }
            Audit::record($actor, $id ? 'businesses.updated' : 'businesses.created', $business, array_intersect_key($old, $changes), $changes);
            return $business;
        }, 3);
    }

    public function reconcile(User $actor, Business $business, Tenant $tenant, string $reason): Business
    {
        $this->authorize($actor);
        Validator::make(['reason'=>$reason], ['reason'=>'required|string|max:2000'])->validate();

        return DB::transaction(function () use ($actor, $business, $tenant, $reason) {
            $tenant = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $business = Business::whereKey($business->id)->lockForUpdate()->firstOrFail();
            if ($business->tenant()->exists() || StallAssignment::where('business_id', $business->id)->exists()) {
                throw ValidationException::withMessages(['tenant_id'=>'A business with an owner or assignment history cannot be reconciled here.']);
            }
            if ($tenant->status === 'archived') {
                throw ValidationException::withMessages(['tenant_id'=>'Choose a tenant that is not archived.']);
            }
            $old = ['tenant_id'=>$business->tenant_id];
            $business->update(['tenant_id'=>$tenant->id,'updated_by'=>$actor->id]);
            $this->invalidateDraft($tenant);
            Audit::record($actor, 'businesses.reconciled', $business, $old, ['tenant_id'=>$tenant->id], $reason);
            return $business;
        }, 3);
    }

    private function invalidateDraft(Tenant $tenant): void
    {
        if ($tenant->status === 'draft' && $tenant->registration_draft) {
            $draft = $tenant->registration_draft;
            $draft['version'] = (string)Str::uuid();
            $tenant->registration_draft = $draft;
            $tenant->save();
        }
    }

    private function authorize(User $actor)
    {
        abort_unless($actor->active && $actor->can('tenants.manage'), 403);
    }
}
