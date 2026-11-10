<?php

namespace Omnibill\ChorusPro\Tests;

use Omnibill\Exception\UsageLimitReachedException;
use Omnibill\ChorusPro\ChorusProGatewayFactory;
use Omnibill\Registry;
use Omnisense\Contracts\Policy\Permission;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Omnibill\Model\Flow;

/** Chorus Pro's usage policy: declared in policy.json, read by the gateway, enforced through the registry. */
final class PolicyTest extends TestCase
{
    public function testThePolicyIsDeclaredAndRead(): void
    {
        $declared = UsagePolicy::fromFile(__DIR__.'/../policy.json');
        $composer = json_decode((string) file_get_contents(__DIR__.'/../composer.json'), true);

        self::assertIsArray($composer);
        $extra = $composer['extra'] ?? null;
        self::assertIsArray($extra);
        self::assertSame(['policy' => 'policy.json'], $extra['omnisense'] ?? null);
        self::assertSame('chorus-pro', $declared->provider);
        self::assertSame('2026-11-10', $declared->provenance->verifiedAt);
        self::assertSame($declared->toArray(), ChorusProGatewayFactory::usagePolicy()->toArray());
        self::assertNull($declared->rateLimits, 'documented per API behind a PISTE account: unknown');
        self::assertSame(Permission::Allowed, $declared->rights->commercial);
        self::assertTrue($declared->requirements->key);

        $registry = new Registry([new ChorusProGatewayFactory(new MockHttpClient())], ['chorus-pro' => ['factory' => 'chorus-pro', 'options' => ['client_id' => 'id', 'client_secret' => 's', 'login' => 'TECH_1@cpro.fr', 'password' => 'p']]]);
        self::assertSame($declared->toArray(), $registry->policy('chorus-pro')->toArray());
    }

    public function testA429AndItsRetryAfterHoldEveryCallUntilTheWaitIsOver(): void
    {
        $requests = 0;
        $http = new MockHttpClient(function () use (&$requests): MockResponse {
            ++$requests;

            return new MockResponse('{"error":"too many requests"}', ['http_code' => 429, 'response_headers' => ['retry-after' => '30', 'content-type' => 'application/json']]);
        });
        $registry = new Registry([new ChorusProGatewayFactory($http)], ['chorus-pro' => ['factory' => 'chorus-pro', 'options' => ['client_id' => 'id', 'client_secret' => 's', 'login' => 'TECH_1@cpro.fr', 'password' => 'p']]]);

        try {
            $registry->get('chorus-pro')->fetchStatus(new Flow('42', invoiceNumber: 'F-1'));
            self::fail('A 429 was read as an answer.');
        } catch (UsageLimitReachedException $e) {
            self::assertSame(30, $e->retryAfter);
        }
        try {
            $registry->get('chorus-pro')->fetchStatus(new Flow('42', invoiceNumber: 'F-1'));
            self::fail('A call went out while Chorus Pro asked to wait.');
        } catch (UsageLimitReachedException $e) {
            self::assertGreaterThanOrEqual(29, $e->retryAfter);
        }
        self::assertSame(1, $requests, 'the wait held the next request back');
    }
}
