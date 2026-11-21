<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'school_id',
        'code',
        'name',
        'channel',
        'subject',
        'template_text',
        'variables',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'template_id');
    }

    /**
     * Render subject and template text with parameter substitution: {param_name}
     */
    public function render(array $params = []): array
    {
        $renderedSubject = $this->subject;
        $renderedBody = $this->template_text;

        foreach ($params as $key => $val) {
            $replacement = is_scalar($val) ? (string) $val : json_encode($val);
            if ($renderedSubject) {
                $renderedSubject = str_replace('{'.$key.'}', $replacement, $renderedSubject);
            }
            $renderedBody = str_replace('{'.$key.'}', $replacement, $renderedBody);
        }

        return [
            'subject' => $renderedSubject,
            'body' => $renderedBody,
        ];
    }
}
