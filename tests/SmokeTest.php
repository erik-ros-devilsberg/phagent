<?php

declare(strict_types=1);

namespace Phagent\Tests;

use Phagent\Phagent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Phagent::class)]
final class SmokeTest extends TestCase
{
    public function testNamespaceAutoloads(): void
    {
        self::assertSame('phagent', Phagent::name());
    }
}
