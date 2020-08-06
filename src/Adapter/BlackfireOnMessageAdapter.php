<?php

declare(strict_types=1);

namespace Chubbyphp\WorkermanRequestHandler\Adapter;

use Blackfire\Client;
use Blackfire\Exception\ExceptionInterface;
use Blackfire\Probe;
use Blackfire\Profile\Configuration;
use Chubbyphp\WorkermanRequestHandler\OnMessageInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Workerman\Connection\TcpConnection as WorkermanTcpConnection;
use Workerman\Protocols\Http\Request as WorkermanRequest;

final class BlackfireOnMessageAdapter implements OnMessageInterface
{
    /**
     * @var OnMessageInterface
     */
    private $onRequest;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var Configuration
     */
    private $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        OnMessageInterface $onRequest,
        Client $client,
        ?Configuration $config = null,
        ?LoggerInterface $logger = null
    ) {
        $this->onRequest = $onRequest;
        $this->client = $client;
        $this->config = $config ?? new Configuration();
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(WorkermanTcpConnection $workermanTcpConnection, WorkermanRequest $workermanRequest): void
    {
        if (null === $workermanRequest->header('x-blackfire-query')) {
            $this->onRequest->__invoke($workermanTcpConnection, $workermanRequest);

            return;
        }

        $probe = $this->startProbe();

        $this->onRequest->__invoke($workermanTcpConnection, $workermanRequest);

        if (null === $probe) {
            return;
        }

        $this->endProbe($probe);
    }

    private function startProbe(): ?Probe
    {
        try {
            return $this->client->createProbe($this->config);
        } catch (ExceptionInterface $exception) {
            $this->logger->error(sprintf('Blackfire exception: %s', $exception->getMessage()));
        }

        return null;
    }

    private function endProbe(Probe $probe): void
    {
        try {
            $this->client->endProbe($probe);
        } catch (ExceptionInterface $exception) {
            $this->logger->error(sprintf('Blackfire exception: %s', $exception->getMessage()));
        }
    }
}
