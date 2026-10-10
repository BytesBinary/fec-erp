<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An admin-edited subject and body for one event. Placeholders look like
 * `{name}`; only the placeholders declared for the event are filled.
 */
class EmailTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\EmailTemplateFactory> */
    use HasFactory;

    protected $fillable = ['event_key', 'name', 'subject', 'body'];
}
