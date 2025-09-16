<?php

namespace App\Jobs;

use App\Services\OgImageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateOgImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 20; // seconds

    public function __construct(
        public string $uniqueCode,
        public string $title
    ) {}

    public function handle(): void
    {
        OgImageService::generateForQuiz($this->uniqueCode, $this->title);
    }
}


