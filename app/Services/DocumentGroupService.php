<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Referential;
use App\Models\Space;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DocumentGroupService
{
    public const NONE = '__none__';

    private const COLUMN_GROUPS = [
        'space' => 'documents.space_id',
        'type' => 'documents.document_type_id',
        'status' => 'documents.status',
        'confidentiality' => 'documents.confidentiality',
        'criticality' => 'documents.criticality',
        'domain' => 'documents.domain_id',
        'process' => 'documents.process_id',
    ];

    private const REF_LABELS = [
        'job' => 'Poste',
        'department' => 'Département',
        'direction' => 'Direction',
        'site' => 'Site',
        'entity' => 'Entité',
        'country' => 'Pays',
    ];

    public function dimensions(bool $v02, Collection $definitions): array
    {
        $dims = $v02
            ? ['space' => 'Section', 'type' => 'Famille', 'criticality' => 'Criticité']
            : ['space' => 'Espace', 'type' => 'Type', 'status' => 'Statut', 'confidentiality' => 'Confidentialité'];

        $dims += ['domain' => 'Domaine', 'process' => 'Processus'];

        foreach (self::REF_LABELS as $type => $label) {
            $dims['ref:'.$type] = $label;
        }

        foreach ($definitions as $def) {
            $dims['meta:'.$def->id] = $def->name;
        }

        return $dims;
    }

    public function groups(Builder $query, string $group): array
    {
        $rows = null;
        $none = 0;
        $label = fn ($v) => (string) $v;

        if (str_starts_with($group, 'meta:')) {
            $id = (int) substr($group, 5);
            $rows = $this->base($query)
                ->join('metadata_values as mv', function ($j) use ($id) {
                    $j->on('mv.document_id', '=', 'documents.id')
                        ->where('mv.definition_id', '=', $id);
                })
                ->selectRaw('mv.value as gk, COUNT(*) as c')
                ->groupBy('mv.value')
                ->get();
            $none = $this->missing($query, fn (Builder $b) => $b->whereDoesntHave('metadataValues', fn ($q) => $q->where('definition_id', $id)));
        } elseif (str_starts_with($group, 'ref:')) {
            $type = substr($group, 4);
            $rows = $this->base($query)
                ->join('document_referential as dr', function ($j) use ($type) {
                    $j->on('dr.document_id', '=', 'documents.id')
                        ->where('dr.type', '=', $type);
                })
                ->selectRaw('dr.referential_id as gk, COUNT(*) as c')
                ->groupBy('dr.referential_id')
                ->get();
            $none = $this->missing($query, fn (Builder $b) => $b->whereDoesntHave('referentials', fn ($q) => $q->wherePivot('type', $type)));
            $names = Referential::where('type', $type)->pluck('name', 'id');
            $label = fn ($v) => $names[(int) $v] ?? (string) $v;
        } else {
            $col = self::COLUMN_GROUPS[$group] ?? null;
            if ($col === null) {
                return [];
            }
            $rows = $this->base($query)
                ->selectRaw("{$col} as gk, COUNT(*) as c")
                ->groupBy($col)
                ->get();
            $none = $this->missing($query, fn (Builder $b) => $b->whereNull(substr($col, 10)));
            $names = match ($group) {
                'space' => Space::pluck('name', 'id'),
                'type' => DocumentType::pluck('name', 'id'),
                'domain', 'process' => Referential::where('type', $group)->pluck('name', 'id'),
                default => null,
            };
            if ($names !== null) {
                $label = fn ($v) => $names[(int) $v] ?? (string) $v;
            }
        }

        $cards = [];
        foreach ($rows ?? [] as $row) {
            if ($row->gk === null || $row->gk === '') {
                $none += (int) $row->c;

                continue;
            }
            $cards[] = ['value' => (string) $row->gk, 'label' => $label($row->gk), 'count' => (int) $row->c];
        }
        if ($none > 0) {
            $cards[] = ['value' => self::NONE, 'label' => 'Non renseigné', 'count' => $none];
        }

        usort($cards, fn ($a, $b) => $b['count'] <=> $a['count'] ?: strcasecmp($a['label'], $b['label']));

        return $cards;
    }

    public function applyFilter(Builder $query, string $group, ?string $value): Builder
    {
        if ($value === null) {
            return $query;
        }

        $none = $value === self::NONE;

        if (str_starts_with($group, 'meta:')) {
            $id = (int) substr($group, 5);

            return $none
                ? $query->whereDoesntHave('metadataValues', fn ($q) => $q->where('definition_id', $id))
                : $query->whereHas('metadataValues', fn ($q) => $q->where('definition_id', $id)->where('value', $value));
        }

        if (str_starts_with($group, 'ref:')) {
            $type = substr($group, 4);

            return $none
                ? $query->whereDoesntHave('referentials', fn ($q) => $q->wherePivot('type', $type))
                : $query->whereHas('referentials', fn ($q) => $q->wherePivot('type', $type)->where('referential_id', (int) $value));
        }

        $col = self::COLUMN_GROUPS[$group] ?? null;
        if ($col === null) {
            return $query;
        }

        if ($none) {
            return $query->whereNull(substr($col, 10));
        }

        return in_array($group, ['status', 'confidentiality', 'criticality'], true)
            ? $query->where(substr($col, 10), $value)
            : $query->where(substr($col, 10), (int) $value);
    }

    public function valueLabel(string $group, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value === self::NONE) {
            return 'Non renseigné';
        }
        if (str_starts_with($group, 'meta:')) {
            return $value;
        }
        if (str_starts_with($group, 'ref:')) {
            return Referential::find((int) $value)?->name ?? $value;
        }
        if ($group === 'space') {
            return Space::find((int) $value)?->name ?? $value;
        }
        if ($group === 'type') {
            return DocumentType::find((int) $value)?->name ?? $value;
        }
        if (in_array($group, ['domain', 'process'], true)) {
            return Referential::find((int) $value)?->name ?? $value;
        }
        if ($group === 'status') {
            return Document::STATUS_LABELS[$value] ?? $value;
        }

        return $value;
    }

    private function base(Builder $query): \Illuminate\Database\Query\Builder
    {
        $clone = $query->clone();
        $clone->getQuery()->orders = null;
        $clone->getQuery()->columns = null;

        return $clone->toBase();
    }

    private function missing(Builder $query, callable $apply): int
    {
        $clone = $query->clone();
        $apply($clone);

        return (int) $clone->count();
    }
}
