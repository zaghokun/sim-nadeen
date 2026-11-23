<?php

namespace App\Http\Controllers;

use App\Services\Posting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\View\View;

abstract class OperationController extends Controller
{
    protected function submit(Request $request, string $operation, array $rules, Closure $action, array $context = []): int
    {
        $data = $request->validate(['token' => ['required', 'uuid'], ...$rules]);
        $token = $data['token'];
        unset($data['token']);

        return app(Posting::class)->run($token, $request->user()->id, $operation, [$context, $data],
            fn () => $action($data, $request->user()->id));
    }

    protected function editor(string $title, string $action, string $back, array $fields, array $values = [], ?string $hint = null): View
    {
        return view('operations.editor', compact('title', 'action', 'back', 'fields', 'values', 'hint'));
    }

    protected function field(string $label, string $type = 'text', bool $required = true, array $options = []): array
    {
        return ['label' => $label, 'type' => $type, 'rules' => $required ? ['required'] : [], 'options' => $options];
    }
}
