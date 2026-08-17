<?php

namespace Tests\Unit;

use App\Http\Middleware\TrustHosts;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class TrustHostsTest extends TestCase
{
    #[TestDox('TrustHostsミドルウェアは信頼するホストパターンの配列を返す')]
    public function test_trust_hosts_returns_expected_hosts(): void
    {
        $middleware = new TrustHosts($this->app);

        $hosts = $middleware->hosts();

        $this->assertIsArray($hosts);
        $this->assertNotEmpty($hosts);
    }
}
