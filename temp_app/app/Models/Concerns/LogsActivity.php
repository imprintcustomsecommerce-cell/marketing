<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;

trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn ($model) => $model->writeActivity('created', $model->getAttributes()));
        static::updated(function ($model): void {
            $changes = collect($model->getChanges())->mapWithKeys(fn ($new, $field) => [$field => ['from' => $model->getRawOriginal($field), 'to' => $new]])->all();
            $model->writeActivity('updated', $changes);
        });
        static::deleted(fn ($model) => $model->writeActivity('deleted'));
    }

    private function writeActivity(string $action, array $changes = []): void
    {
        unset($changes['updated_at'], $changes['created_at'], $changes['password'], $changes['remember_token']);

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => static::class,
            'subject_id' => $this->getKey(),
            'description' => class_basename(static::class).' “'.$this->activityName().'” '.$action,
            'changes' => $changes ?: null,
        ]);
    }

    private function activityName(): string
    {
        $label = $this->name ?? $this->title ?? $this->reference ?? null;

        return (string) ($label ?: (method_exists($this, 'subject') ? $this->subject() : '#'.$this->getKey()));
    }
}
