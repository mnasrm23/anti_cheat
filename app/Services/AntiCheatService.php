<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AntiCheatService
{
    public function analyzeFrame(string $sessionId, UploadedFile $image): array
    {
        $baseUrl = rtrim((string) config('services.ai_anti_cheat.url'), '/');
        $url = $baseUrl.'/frame';
        $timeout = (int) config('services.ai_anti_cheat.timeout', 10);

        Log::info('Anti-cheat AI request started', [
            'url' => $url,
            'session_id' => $sessionId,
            'timeout' => $timeout,
        ]);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->attach(
                    'image',
                    file_get_contents($image->getRealPath()),
                    $image->getClientOriginalName() ?: 'frame.jpg'
                )
                ->post($url, [
                    'session_id' => $sessionId,
                ]);

            if ($response->failed()) {
                Log::error('Anti-cheat AI service returned error response', [
                    'session_id' => $sessionId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new AntiCheatServiceException(
                    'AI service returned an error.',
                    $response->status() >= 500 ? 502 : $response->status()
                );
            }

            $data = $response->json();

            if (! is_array($data)) {
                Log::error('Anti-cheat AI service returned invalid JSON', [
                    'session_id' => $sessionId,
                    'body' => $response->body(),
                ]);

                throw new AntiCheatServiceException('AI service returned invalid JSON.', 502);
            }

            Log::info('Anti-cheat AI request completed', [
                'session_id' => $sessionId,
                'anti_cheat_status' => $data['anti_cheat']['status'] ?? null,
            ]);

            return $data;
        } catch (AntiCheatServiceException $e) {
            throw $e;
        } catch (ConnectionException $e) {
            Log::error('Anti-cheat AI service connection failed', [
                'session_id' => $sessionId,
                'message' => $e->getMessage(),
            ]);

            throw new AntiCheatServiceException('Unable to reach AI service.', 503, $e);
        } catch (Throwable $e) {
            Log::error('Anti-cheat AI service request failed', [
                'session_id' => $sessionId,
                'message' => $e->getMessage(),
            ]);

            throw new AntiCheatServiceException('AI service request failed.', 502, $e);
        }
    }
}
