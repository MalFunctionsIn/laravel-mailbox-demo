<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Regression test for the first deployment to Render.
 *
 * Render terminates TLS at its edge and forwards plain HTTP to the container
 * with X-Forwarded-Proto: https. Until the app trusted that header, every
 * generated URL came out as http:// on a page served over https://. Browsers
 * block that as mixed content, so the send buttons silently did nothing and
 * the dashboard loaded without its stylesheet or script.
 *
 * Nothing errored and every status code was 200, which is precisely why this
 * deserves a test rather than a comment.
 */
class HttpsBehindProxyTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private function formActions(string $html): array
    {
        preg_match_all('/action="([^"]+)"/', $html, $matches);

        return $matches[1];
    }

    public function test_form_actions_are_https_when_the_proxy_reports_a_secure_request(): void
    {
        $response = $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-For' => '203.0.113.9',
        ])->get('/');

        $response->assertOk();

        $actions = $this->formActions($response->getContent());

        $this->assertNotEmpty($actions, 'The panel should render its send forms.');

        foreach ($actions as $action) {
            $this->assertStringStartsWith(
                'https://',
                $action,
                "Form action [{$action}] must be https behind a TLS-terminating proxy; "
                .'a browser blocks an http:// POST from an https:// page as mixed content.',
            );
        }
    }

    public function test_the_dashboard_link_is_relative_so_it_cannot_leak_the_wrong_scheme(): void
    {
        $response = $this->withHeaders(['X-Forwarded-Proto' => 'https'])->get('/');

        $response->assertOk();
        $response->assertSee('href="/mailbox"', false);
    }

    public function test_plain_http_is_untouched_without_a_proxy(): void
    {
        // Trusting proxies must not force https on a direct request, or local
        // development over `php artisan serve` breaks.
        $actions = $this->formActions($this->get('/')->getContent());

        $this->assertNotEmpty($actions);

        foreach ($actions as $action) {
            $this->assertStringStartsWith('http://', $action);
            $this->assertStringNotContainsString('https://', $action);
        }
    }
}
