<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Tests\Unit\DTOs;

use LaravelPlus\Subscribe\DTOs\SyncResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SyncResultTest extends TestCase
{
    #[Test]
    public function testSuccessCreatesSuccessfulResult(): void
    {
        $result = SyncResult::success('ok', 'id-123');

        $this->assertTrue($result->success);
        $this->assertSame('ok', $result->message);
        $this->assertSame('id-123', $result->providerId);
        $this->assertNull($result->errorCode);
        $this->assertSame([], $result->data);
    }

    #[Test]
    public function testFailureCreatesFailedResult(): void
    {
        $result = SyncResult::failure('error', '400');

        $this->assertFalse($result->success);
        $this->assertSame('error', $result->message);
        $this->assertSame('400', $result->errorCode);
        $this->assertNull($result->providerId);
        $this->assertSame([], $result->data);
    }

    #[Test]
    public function testToArrayReturnsCorrectStructure(): void
    {
        $result = SyncResult::success('synced', 'prov-1', ['extra' => 'info']);

        $array = $result->toArray();

        $this->assertArrayHasKey('success', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('provider_id', $array);
        $this->assertArrayHasKey('error_code', $array);
        $this->assertArrayHasKey('data', $array);

        $this->assertTrue($array['success']);
        $this->assertSame('synced', $array['message']);
        $this->assertSame('prov-1', $array['provider_id']);
        $this->assertNull($array['error_code']);
        $this->assertSame(['extra' => 'info'], $array['data']);
    }

    #[Test]
    public function testSuccessWithData(): void
    {
        $result = SyncResult::success(data: ['key' => 'value']);

        $this->assertTrue($result->success);
        $this->assertNull($result->message);
        $this->assertNull($result->providerId);
        $this->assertSame(['key' => 'value'], $result->data);
    }

    #[Test]
    public function testFailureWithData(): void
    {
        $result = SyncResult::failure('Something went wrong', '500', ['detail' => 'server error']);

        $this->assertFalse($result->success);
        $this->assertSame('Something went wrong', $result->message);
        $this->assertSame('500', $result->errorCode);
        $this->assertSame(['detail' => 'server error'], $result->data);
    }
}
