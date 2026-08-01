<?php

namespace App\Observers;

use App\Models\DeploymentSetting;
use App\Models\Document;
use App\Models\DocumentOrigin;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\ReceivingBox;
use App\Models\User;
use App\Services\AuditLogger;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SignificantActivityObserver
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function created(Model $model): void
    {
        $fields = $this->auditedFields($model);
        $values = [];

        foreach ($fields as $field) {
            if ($field === 'password') {
                continue;
            }

            if (array_key_exists($field, $model->getAttributes())) {
                $values[$field] = $this->normalize($model->getAttribute($field));
            }
        }

        $this->audit->record(
            $this->eventPrefix($model).'.created',
            $model,
            ['values' => $values],
        );
    }

    public function updated(Model $model): void
    {
        $changes = [];

        foreach ($this->auditedFields($model) as $field) {
            if (! $model->wasChanged($field)) {
                continue;
            }

            if ($field === 'password') {
                $changes[$field] = ['changed' => true];

                continue;
            }

            $changes[$field] = [
                'before' => $this->normalize($model->getRawOriginal($field)),
                'after' => $this->normalize($model->getAttribute($field)),
            ];
        }

        if ($changes === []) {
            return;
        }

        $this->audit->record(
            $this->eventPrefix($model).'.updated',
            $model,
            ['changes' => $changes],
        );
    }

    public function deleted(Model $model): void
    {
        $values = [];

        foreach ($this->auditedFields($model) as $field) {
            if ($field === 'password') {
                continue;
            }

            if (array_key_exists($field, $model->getAttributes())) {
                $values[$field] = $this->normalize($model->getAttribute($field));
            }
        }

        $this->audit->record(
            $this->eventPrefix($model).'.deleted',
            $model,
            ['values' => $values],
        );
    }

    /**
     * @return list<string>
     */
    private function auditedFields(Model $model): array
    {
        return match ($model::class) {
            DeploymentSetting::class => [
                'deployment_profile',
                'system_name',
                'managing_office_name',
                'managing_office_code',
                'managing_office_level',
                'upstream_office_label',
                'level_1_unit_label',
                'receiving_box_label',
                'tracking_prefix',
                'status',
            ],
            OrganizationalUnit::class => [
                'parent_id',
                'unit_type',
                'unit_code',
                'unit_name',
                'short_name',
                'address',
                'contact_person',
                'contact_number',
                'email',
                'status',
            ],
            User::class => [
                'organizational_unit_id',
                'full_name',
                'position',
                'email',
                'username',
                'password',
                'role',
                'status',
            ],
            DocumentType::class => [
                'name',
                'description',
                'default_workflow',
                'status',
            ],
            DocumentOrigin::class => [
                'origin_type',
                'origin_name',
                'office_code',
                'address',
                'status',
            ],
            Document::class => [
                'tracking_no',
                'document_type_id',
                'subject',
                'description',
                'submitting_unit_id',
                'priority',
                'date_received',
                'due_date',
                'remarks',
                'created_by',
            ],
            ReceivingBox::class => [
                'organizational_unit_id',
                'box_location',
                'status',
            ],
            default => [],
        };
    }

    private function eventPrefix(Model $model): string
    {
        return Str::snake(class_basename($model));
    }

    private function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            is_scalar($value), $value === null => $value,
            default => (string) $value,
        };
    }
}
