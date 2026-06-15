<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function apiResponse(bool $success, string $message, mixed $body = [], int $status = 200): \Illuminate\Http\JsonResponse
    {
        return response()->json(['success' => $success, 'message' => $message, 'body' => $body], $status);
    }
}