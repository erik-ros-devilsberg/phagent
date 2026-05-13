<?php

declare(strict_types=1);

namespace Phagent\Client;

interface ClientInterface
{
    /**
     * @param list<array<string, mixed>> $messages
     * @param list<array<string, mixed>> $tools
     *
     * @return array<string, mixed>
     */
    public function sendMessages(array $messages, array $tools): array;
}
