<?php

namespace App\Filament\Resources\AuditEvents;

use App\Filament\Resources\AuditEvents\Pages\ListAuditEvents;
use App\Models\AuditEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class AuditEventResource extends Resource
{
    protected static ?string $model = AuditEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Activity Audit';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Occurred')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('event_type')
                    ->label('Activity')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Str::headline(str_replace('.', ' ', $state)))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('actor.full_name')
                    ->label('Performed by')
                    ->placeholder('System / console')
                    ->searchable(),
                TextColumn::make('auditable_type')
                    ->label('Record type')
                    ->formatStateUsing(fn (?string $state): string => filled($state)
                        ? Str::headline(class_basename($state))
                        : 'Operational activity')
                    ->searchable(),
                TextColumn::make('auditable_id')
                    ->label('Record key')
                    ->placeholder('—'),
                TextColumn::make('details_summary')
                    ->label('Recorded detail')
                    ->state(fn (AuditEvent $record): string => self::formatDetails($record->details))
                    ->wrap()
                    ->limit(140),
                TextColumn::make('ip_address')
                    ->label('IP address')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('device_info')
                    ->label('Device')
                    ->limit(80)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event_type')
                    ->label('Activity')
                    ->options(fn (): array => AuditEvent::query()
                        ->distinct()
                        ->orderBy('event_type')
                        ->pluck('event_type', 'event_type')
                        ->mapWithKeys(fn (string $event): array => [
                            $event => Str::headline(str_replace('.', ' ', $event)),
                        ])
                        ->all()),
            ])
            ->defaultSort('occurred_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('actor');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditEvents::route('/'),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $details
     */
    private static function formatDetails(?array $details): string
    {
        $changes = $details['changes'] ?? null;

        if (is_array($changes) && $changes !== []) {
            return collect($changes)
                ->map(function (mixed $change, string $field): string {
                    if (is_array($change) && ($change['changed'] ?? false) === true) {
                        return Str::headline($field).' changed';
                    }

                    if (! is_array($change)) {
                        return Str::headline($field).' changed';
                    }

                    return sprintf(
                        '%s: %s → %s',
                        Str::headline($field),
                        self::displayValue($change['before'] ?? null),
                        self::displayValue($change['after'] ?? null),
                    );
                })
                ->join('; ');
        }

        $values = $details['values'] ?? null;

        if (is_array($values) && $values !== []) {
            return 'Recorded fields: '.collect(array_keys($values))
                ->map(fn (string $field): string => Str::headline($field))
                ->join(', ');
        }

        $report = $details['report'] ?? null;

        if (is_string($report)) {
            return 'Report: '.Str::headline($report);
        }

        return 'Activity recorded';
    }

    private static function displayValue(mixed $value): string
    {
        return match (true) {
            $value === null, $value === '' => '—',
            is_bool($value) => $value ? 'Yes' : 'No',
            is_scalar($value) => (string) $value,
            default => '[structured value]',
        };
    }
}
