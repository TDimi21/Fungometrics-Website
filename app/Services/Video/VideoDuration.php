<?php
namespace App\Services\Video;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class VideoDuration
{
    public function validate(UploadedFile $file): float
    {
        // Inspect the actual upload, never a duration supplied by the client.
        $process = new Process([config('player_videos.ffprobe_binary', 'ffprobe'),
            '-v', 'error', '-protocol_whitelist', 'file,pipe', '-show_entries',
            'format=duration:stream=codec_type,duration', '-of', 'json', $file->getRealPath()]);
        $process->setTimeout(20);
        try {
            $process->run();
        } catch (\Throwable $e) {
            report($e);
            abort(503, 'Video length verification is temporarily unavailable. Please try again later.');
        }
        if (in_array($process->getExitCode(), [126, 127], true)) {
            abort(503, 'Video length verification is unavailable. Please contact support.');
        }
        if (!$process->isSuccessful()) $this->invalid('Could not read this video. Upload a valid clip shorter than 15 seconds.');
        return $this->validateMetadata(json_decode($process->getOutput(), true) ?? []);
    }

    public function validateMetadata(array $metadata): float
    {
        $videoStreams = array_filter($metadata['streams'] ?? [], fn ($s) => ($s['codec_type'] ?? '') === 'video');
        $duration = $metadata['format']['duration'] ?? null;
        if (!$videoStreams || !is_numeric($duration) || !is_finite((float) $duration) || (float) $duration <= 0) {
            $this->invalid('Could not verify the video length. Choose another clip shorter than 15 seconds.');
        }
        // A longer stream must not bypass the limit via shorter container metadata.
        $durations = [(float) $duration];
        foreach ($metadata['streams'] ?? [] as $stream) {
            if (is_numeric($stream['duration'] ?? null)) $durations[] = (float) $stream['duration'];
        }
        $seconds = max($durations);
        if (!is_finite($seconds) || $seconds >= 15) $this->invalid('Clips must be shorter than 15 seconds. Trim your video and upload it again.');
        return $seconds;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['video' => $message]);
    }
}
