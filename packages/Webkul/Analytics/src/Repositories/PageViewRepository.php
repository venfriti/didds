<?php

namespace Webkul\Analytics\Repositories;

use Webkul\Analytics\Contracts\PageView;
use Webkul\Core\Eloquent\Repository;

class PageViewRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return PageView::class;
    }
}
