<?php

namespace Webkul\Analytics\Providers;

use Webkul\Analytics\Models\PageView;
use Webkul\Core\Providers\CoreModuleServiceProvider;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Models.
     *
     * @var array
     */
    protected $models = [
        PageView::class,
    ];
}
