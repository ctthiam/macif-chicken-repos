<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Job : NTF-06 — SMS via Twilio API.
 *
 * Fichier : app/Jobs/EnvoyerSmsJob.php
 *
 * Config requise (config/services.php + .env) :
 *   TWILIO_SID=
 *   TWILIO_TOKEN=
 *   TWILIO_FROM=+221XXXXXXXX
 *
 * Mode offline : si TWILIO_SID vide → log only, pas d'appel HTTP.
 */
class EnvoyerSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $to,
        public readonly string $message
    ) {}

    public function handle(): void
    {
        $sid   = config('services.twilio.sid', '');
        $token = config('services.twilio.token', '');
        $from  = config('services.twilio.from', '');

        // Mode offline — pas de credentials
        if (empty($sid)) {
            Log::info('[NTF-06] SMS (offline)', ['to' => $this->to, 'message' => $this->message]);
            return;
        }

        try {
            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => $from,
                    'To'   => $this->to,
                    'Body' => $this->message,
                ]);

            if ($response->successful()) {
                Log::info('[NTF-06] SMS envoyé', ['to' => $this->to, 'sid' => $response['sid'] ?? null]);
            } else {
                Log::error('[NTF-06] Erreur SMS Twilio', [
                    'to'     => $this->to,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[NTF-06] Exception SMS', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}