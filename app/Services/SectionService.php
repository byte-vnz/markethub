<?php

namespace App\Services;

use App\Models\{Market, MarketSection, User};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\ValidationException;

class SectionService
{
    public const MAX_REORDER_SECTIONS = 500;

    public static function rules(): array
    {
        return [
            'market_id' => 'required|integer|exists:markets,id',
            'code' => 'required|string|max:40',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'color' => ['nullable', 'string', 'regex:/\A#[0-9a-fA-F]{6}\z/'],
            'sort_order' => 'sometimes|required|integer|min:0|max:999999',
            'status' => 'required|in:active,inactive',
        ];
    }

    public function save(User $actor, array $data, $id = null): MarketSection
    {
        $this->authorize($actor);
        $data = Validator::make($data, self::rules())->validate();
        $data['code'] = trim($data['code']);
        $data['name'] = trim($data['name']);
        $normalizedName = self::normalizedName($data['name']);
        if ($normalizedName === '' || $data['code'] === '') {
            throw ValidationException::withMessages($normalizedName === ''
                ? ['name' => 'Enter a section name.'] : ['code' => 'Enter a section code.']);
        }
        $originalMarket = $id === null ? $data['market_id'] : MarketSection::findOrFail($id)->market_id;

        return DB::transaction(function () use ($actor, $data, $id, $normalizedName, $originalMarket) {
            $markets = Market::whereIn('id', [$originalMarket, $data['market_id']])->orderBy('id')->lockForUpdate()->get();
            abort_unless($markets->contains('id', $data['market_id']), 404);
            $section = $id === null ? new MarketSection() : MarketSection::lockForUpdate()->findOrFail($id);
            if ($section->exists && (int) $section->market_id !== (int) $originalMarket) {
                throw ValidationException::withMessages(['market_id' => 'The section market changed. Reload the record and try again.']);
            }
            $moving = $section->exists && (int) $section->market_id !== (int) $data['market_id'];
            if ($moving && $section->stalls()->exists()) {
                throw ValidationException::withMessages(['market_id' => 'A section containing stalls cannot change market.']);
            }
            if ($section->exists && $section->status !== 'inactive' && $data['status'] === 'inactive'
                && $section->stalls()->where('status', 'occupied')->exists()) {
                throw ValidationException::withMessages(['status' => 'End occupied assignments before deactivating this section.']);
            }

            $others = MarketSection::where('market_id', $data['market_id']);
            if ($section->exists) {
                $others->where('id', '!=', $section->id);
                if ($data['code'] === trim($section->code)) {
                    $data['code'] = $section->code;
                }
            }
            if ((clone $others)->where('code', $data['code'])->lockForUpdate()->first(['id'])) {
                throw ValidationException::withMessages(['code' => 'This code is already used in the selected market.']);
            }
            $unchangedName = $section->exists && $data['name'] === trim($section->name);
            if (($moving || !$unchangedName) && $others->lockForUpdate()->pluck('name')->contains(function ($name) use ($normalizedName) {
                return self::normalizedName($name) === $normalizedName;
            })) {
                throw ValidationException::withMessages(['name' => 'This section name is already used in the selected market.']);
            }
            // Preserve original spelling, including whitespace trimmed during HTTP input processing.
            if ($unchangedName) {
                $data['name'] = $section->name;
            }
            $before = $section->getAttributes();
            $section->fill($data);
            if (!$section->exists) {
                $section->created_by = $actor->id;
            }
            $section->updated_by = $actor->id;
            $section->save();
            Audit::record($actor, $id === null ? 'sections.created' : 'sections.updated', $section, $before, $section->getAttributes());
            return $section;
        }, 3);
    }

    public function reorder(User $actor, $marketId, array $ids): void
    {
        $this->authorize($actor);
        Validator::make(['market_id' => $marketId, 'ids' => $ids], [
            'market_id' => 'required|integer|exists:markets,id',
            'ids' => 'required|array|min:1|max:'.self::MAX_REORDER_SECTIONS,
            'ids.*' => 'required|integer|distinct|min:1',
        ])->validate();

        DB::transaction(function () use ($actor, $marketId, $ids) {
            $market = Market::lockForUpdate()->findOrFail($marketId);
            $sections = $market->sections()->lockForUpdate()->get()->keyBy('id');
            if ($sections->count() !== count($ids) || $sections->keys()->diff($ids)->isNotEmpty()) {
                throw ValidationException::withMessages(['ids' => 'Submit every section in this market exactly once. Reload the order if sections changed.']);
            }
            foreach (array_values($ids) as $index => $id) {
                $section = $sections->get($id);
                $order = $index + 1;
                if ($section->sort_order === $order) {
                    continue;
                }
                $before = ['sort_order' => $section->sort_order];
                $section->update(['sort_order' => $order, 'updated_by' => $actor->id]);
                Audit::record($actor, 'sections.reordered', $section, $before, ['sort_order' => $order]);
            }
        }, 3);
    }

    public static function normalizedName(string $name): string
    {
        return mb_convert_case(trim(preg_replace('/[\p{Z}\s]+/u', ' ', $name)), MB_CASE_FOLD, 'UTF-8');
    }

    private function authorize(User $actor): void
    {
        if (!$actor->active || !$actor->can('sections.manage')) {
            throw new AuthorizationException();
        }
    }
}
