<?php

namespace Webkul\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Analytics\Contracts\PageView as PageViewContract;

class PageView extends Model implements PageViewContract
{
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'visitor_id',
        'channel_id',
        'url',
        'path',
        'referrer_source',
        'referrer_host',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'created_at',
    ];
}
