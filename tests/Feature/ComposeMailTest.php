<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Redberry\MailboxForLaravel\Facades\Mailbox;
use Redberry\MailboxForLaravel\Testing\InteractsWithMailbox;
use Tests\TestCase;

/**
 * The compose form: whatever the visitor types goes through the same transport
 * as the canned sends, and comes back out of the mailbox fully rendered.
 */
class ComposeMailTest extends TestCase
{
    use InteractsWithMailbox;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'to' => 'ada@example.com',
            'subject' => 'Your refund has been processed',
            'body' => "Hi Ada,\n\nWe've refunded \$32.00 to your card.",
            'format' => 'both',
        ], $overrides);
    }

    public function test_it_captures_a_composed_message(): void
    {
        $this->post('/send/compose', $this->payload())
            ->assertRedirect('/')
            ->assertSessionHas('status');

        Mailbox::assertSentCount(1);

        Mailbox::firstSent()
            ->assertHasTo('ada@example.com')
            ->assertHasSubject('Your refund has been processed')
            ->assertSeeInHtml('We&#039;ve refunded $32.00 to your card.')
            ->assertSeeInText("We've refunded \$32.00 to your card.")
            ->assertHasHeader('X-Mailbox-Demo', 'composed')
            ->assertHasNoAttachments();
    }

    public function test_it_carries_a_cc_when_one_is_given(): void
    {
        $this->post('/send/compose', $this->payload([
            'cc' => 'warehouse@acme-store.test',
        ]))->assertRedirect('/');

        Mailbox::firstSent()
            ->assertHasTo('ada@example.com')
            ->assertHasCc('warehouse@acme-store.test');
    }

    public function test_html_only_and_text_only_produce_one_part_each(): void
    {
        $this->post('/send/compose', $this->payload(['format' => 'html']));
        $htmlOnly = Mailbox::firstSent()->getMessage();

        $this->assertNotEmpty($htmlOnly->html);
        $this->assertEmpty($htmlOnly->text);

        $this->clearMailbox();

        $this->post('/send/compose', $this->payload(['format' => 'text']));
        $textOnly = Mailbox::firstSent()->getMessage();

        $this->assertNotEmpty($textOnly->text);
        $this->assertEmpty($textOnly->html);
    }

    public function test_an_uploaded_file_is_attached(): void
    {
        $this->post('/send/compose', $this->payload([
            'attachment' => UploadedFile::fake()->create('refund-note.pdf', 12, 'application/pdf'),
        ]))->assertRedirect('/');

        Mailbox::firstSent()
            ->assertAttachmentCount(1)
            ->assertHasAttachment('refund-note.pdf', 'application/pdf');
    }

    public function test_markup_in_the_body_is_escaped_rather_than_rendered(): void
    {
        // The demo is deployed publicly, so a stored <script> would run in the
        // dashboard of whoever looked at the message next.
        $this->post('/send/compose', $this->payload([
            'body' => '<script>alert(1)</script> and <b>bold</b>',
        ]))->assertRedirect('/');

        Mailbox::firstSent()
            ->assertDontSeeInHtml('<script>alert(1)</script>')
            ->assertDontSeeInHtml('<b>bold</b>')
            ->assertSeeInHtml('&lt;script&gt;');
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'missing recipient' => [['to' => ''], 'to'],
            'malformed recipient' => [['to' => 'not-an-address'], 'to'],
            'malformed cc' => [['cc' => 'also-not-one'], 'cc'],
            'missing subject' => [['subject' => ''], 'subject'],
            'missing body' => [['body' => ''], 'body'],
            'unknown format' => [['format' => 'carrier-pigeon'], 'format'],
            'overlong subject' => [['subject' => str_repeat('a', 201)], 'subject'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPayloads')]
    public function test_it_rejects_bad_input(array $overrides, string $field): void
    {
        $this->post('/send/compose', $this->payload($overrides))
            ->assertSessionHasErrors($field);

        Mailbox::assertNothingSent();
    }

    public function test_it_rejects_an_oversized_attachment(): void
    {
        $this->post('/send/compose', $this->payload([
            'attachment' => UploadedFile::fake()->create('huge.pdf', 2048, 'application/pdf'),
        ]))->assertSessionHasErrors('attachment');

        Mailbox::assertNothingSent();
    }

    public function test_the_form_renders_on_the_panel(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Compose your own')
            ->assertSee('name="subject"', false)
            ->assertSee('enctype="multipart/form-data"', false);
    }
}
