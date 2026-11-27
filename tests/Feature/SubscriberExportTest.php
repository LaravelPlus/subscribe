<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelPlus\Subscribe\Models\Subscriber;
use LaravelPlus\Subscribe\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class SubscriberExportTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Admin auth is the host app's middleware; this test is about the CSV.
        $app['config']->set('subscribe.admin.middleware', ['web']);
    }

    #[Test]
    public function export_neutralises_spreadsheet_formulas(): void
    {
        Subscriber::factory()->create([
            'email' => 'evil@example.com',
            'first_name' => '=HYPERLINK("https://evil","x")',
            'last_name' => '@SUM(1)',
            'phone' => '+386 1 234',
            'company' => "\tTabbed",
            'tags' => ['-1+1'],
            'source' => "\rhttps://ref.example",
        ]);

        $csv = $this->get('/admin/subscribers/subscribers/export')->assertOk()->streamedContent();
        $row = str_getcsv(explode("\n", trim($csv))[1]);

        $this->assertSame("'=HYPERLINK(\"https://evil\",\"x\")", $row[1]);
        $this->assertSame("'@SUM(1)", $row[2]);
        $this->assertSame("'+386 1 234", $row[3]);
        $this->assertSame("'\tTabbed", $row[4]);
        $this->assertSame("'-1+1", $row[6]);
        $this->assertSame("'\rhttps://ref.example", $row[8]);
        $this->assertSame('evil@example.com', $row[0]);
    }
}
