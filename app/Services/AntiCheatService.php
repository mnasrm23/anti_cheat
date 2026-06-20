<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;

class AntiCheatService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.ai_anti_cheat.url');
    }

    public function analyzeFrame(string $sessionId, UploadedFile $image): array
    {
        $response = Http::attach(
            'image',
            file_get_contents($image->getRealPath()),
            $image->getClientOriginalName()
        )->post("{$this->baseUrl}/frame", [
            'session_id' => $sessionId,
        ]);

        if ($response->failed()) {
            throw new \Exception('AI Service Error: ' . $response->body());
        }

        return $response->json();
    }
}