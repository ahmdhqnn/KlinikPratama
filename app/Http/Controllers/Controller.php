<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

abstract class Controller
{
    protected function pageData(LengthAwarePaginator $page): array
    {
        return [
            'data' => $page->items(), 'currentPage' => $page->currentPage(), 'lastPage' => $page->lastPage(),
            'from' => $page->firstItem(), 'to' => $page->lastItem(), 'total' => $page->total(),
            'previousUrl' => $page->previousPageUrl(), 'nextUrl' => $page->nextPageUrl(),
        ];
    }
}
