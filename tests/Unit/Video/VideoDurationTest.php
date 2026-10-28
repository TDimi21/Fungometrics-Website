<?php
namespace Tests\Unit\Video;

use App\Services\Video\VideoDuration;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VideoDurationTest extends TestCase
{
    public function test_fractional_clip_below_fifteen_is_allowed(): void
    {
        $this->assertSame(14.999, (new VideoDuration)->validateMetadata(['format' => ['duration' => '14.999'], 'streams' => [['codec_type' => 'video']]]));
    }
    /** @dataProvider invalidMetadata */
    public function test_invalid_and_fifteen_second_clips_are_rejected(array $metadata): void
    {
        $this->expectException(ValidationException::class);
        (new VideoDuration)->validateMetadata($metadata);
    }
    public static function invalidMetadata(): array
    {
        return array_map(fn ($duration) => [['format' => ['duration' => $duration], 'streams' => [['codec_type' => 'video']]]], [15, 15.001, 60, 0, -1, null, 'N/A']) + [
            7 => [['format' => ['duration' => 14], 'streams' => [['codec_type' => 'video', 'duration' => 16]]]],
            8 => [['format' => ['duration' => 14], 'streams' => [['codec_type' => 'audio']]]],
        ];
    }
}
