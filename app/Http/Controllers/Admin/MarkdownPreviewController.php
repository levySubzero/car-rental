<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\PreviewMarkdownRequest;
use App\Support\Markdown;
use Illuminate\Http\JsonResponse;

class MarkdownPreviewController extends Controller
{
    public function __invoke(PreviewMarkdownRequest $request): JsonResponse
    {
        return response()->json(['html' => Markdown::toHtml($request->validated('markdown'))]);
    }
}
