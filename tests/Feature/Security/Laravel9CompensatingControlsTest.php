<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Middleware\RejectEmailHeaderInjection;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class Laravel9CompensatingControlsTest extends TestCase
{
    public function test_email_header_injection_is_rejected_before_controller_dispatch(): void
    {
        $request = Request::create('/api/forgot-password', 'POST', [
            'email' => "victim@example.com\r\nBcc: attacker@example.com",
        ]);

        $response = (new RejectEmailHeaderInjection())->handle(
            $request,
            fn (): Response => response()->json(['unexpected' => true])
        );

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertStringNotContainsString('attacker@example.com', (string) $response->getContent());
    }

    public function test_nested_email_header_injection_is_rejected(): void
    {
        $request = Request::create('/register', 'POST', [
            'profile' => ['email' => "victim@example.com\nCc: attacker@example.com"],
        ]);

        $response = (new RejectEmailHeaderInjection())->handle(
            $request,
            fn (): Response => response()->json(['unexpected' => true])
        );

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function test_normal_email_reaches_the_application(): void
    {
        $request = Request::create('/api/forgot-password', 'POST', [
            'email' => 'person@example.com',
        ]);

        $response = (new RejectEmailHeaderInjection())->handle(
            $request,
            fn (): Response => response()->json(['ok' => true])
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function test_file_upload_rules_do_not_use_vulnerable_wildcard_validation(): void
    {
        $source = '';
        foreach (glob(app_path('Http/Requests/**/*.php')) ?: [] as $file) {
            $source .= (string) file_get_contents($file);
        }

        $this->assertDoesNotMatchRegularExpression(
            '/[\'"][^\'"]+\.\*[\'"]\s*=>[^\n]*(?:file|image|mimes|mimetypes)/i',
            $source
        );

        $validator = Validator::make(
            ['picture' => UploadedFile::fake()->create('payload.php', 1, 'application/x-httpd-php')],
            ['picture' => ['image', 'mimes:jpeg,jpg,png,gif,webp']]
        );
        $this->assertTrue($validator->fails());
    }

    public function test_temporary_signed_local_filesystem_urls_are_not_used(): void
    {
        $source = '';
        foreach ([app_path(), base_path('routes'), config_path()] as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $source .= (string) file_get_contents($file->getPathname());
                }
            }
        }

        $this->assertDoesNotMatchRegularExpression(
            '/temporaryUrl|temporaryUploadUrl|buildTemporaryUrlsUsing/',
            $source
        );
    }

    public function test_framework_patch_blocks_environment_query_manipulation(): void
    {
        $this->assertTrue(version_compare(app()->version(), '9.52.17', '>='));
        $environment = app()->environment();

        $this->get('/?--env=attacker')->assertStatus(Response::HTTP_OK);

        $this->assertSame($environment, app()->environment());
    }
}
