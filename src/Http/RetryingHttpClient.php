<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Http;

use Closure;
use IvanVasechko\Oukitel\Config\ClientOptions;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class RetryingHttpClient implements ClientInterface
{
    /** @var Closure(int): void */
    private readonly Closure $delay;

    /** @param Closure(int): void|null $delay */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly ClientOptions $options = new ClientOptions,
        ?Closure $delay = null,
        private readonly LoggerInterface $logger = new NullLogger,
    ) {
        $this->delay = $delay ?? static function (int $milliseconds): void {
            usleep($milliseconds * 1000);
        };
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $response = $this->client->sendRequest($request);
                if ($response->getStatusCode() < 500 || $attempt >= $this->options->retryTimes
                    || ! $request->getBody()->isSeekable()) {
                    return $response;
                }
                $response->getBody()->close();
            } catch (NetworkExceptionInterface $exception) {
                if ($attempt >= $this->options->retryTimes || ! $request->getBody()->isSeekable()) {
                    throw $exception;
                }
            }
            $this->logger->debug('Retrying Oukitel HTTP request.', ['attempt' => $attempt]);
            $request->getBody()->rewind();
            ($this->delay)($this->options->retryDelayMilliseconds);
        }
    }
}
