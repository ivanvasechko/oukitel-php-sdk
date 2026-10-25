<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Controls;

use Closure;
use InvalidArgumentException;
use IvanVasechko\Oukitel\Clock\SystemClock;
use IvanVasechko\Oukitel\Contracts\ControlCloud;
use IvanVasechko\Oukitel\Contracts\OukitelCloud;
use IvanVasechko\Oukitel\DTOs\ControlResult;
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\DTOs\Session;
use IvanVasechko\Oukitel\Exceptions\ControlsUnavailable;
use IvanVasechko\Oukitel\Parsing\ControlObservationParser;
use IvanVasechko\Oukitel\Parsing\ControlValueParser;
use IvanVasechko\Oukitel\Session\SessionManager;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SensitiveParameter;

/** @experimental Not validated on all models. May change without a major release. */
final readonly class OukitelControls
{
    /** @var Closure(int): void */
    private Closure $delay;

    /**
     * @internal Obtain this API only through Account::controls().
     *
     * @experimental
     *
     * @param  Closure(int): void|null  $delay  Receives milliseconds.
     */
    public function __construct(
        private SessionManager $sessions,
        #[SensitiveParameter] private Credentials $credentials,
        private LoggerInterface $logger = new NullLogger,
        private ClockInterface $clock = new SystemClock,
        ?Closure $delay = null,
        private bool $unsafeMode = false,
    ) {
        $this->delay = $delay ?? static function (int $milliseconds): void {
            usleep($milliseconds * 1000);
        };
    }

    /** @experimental Turning AC off cuts power to connected devices; turning it on energizes outlets. */
    public function setAcOutput(#[SensitiveParameter] DeviceId $device, bool $enabled): void
    {
        $this->send($device, ['ac_switch' => $enabled]);
    }

    /** @experimental */
    public function setDcOutput(#[SensitiveParameter] DeviceId $device, bool $enabled): void
    {
        $this->send($device, ['dc_switch' => $enabled]);
    }

    /** @experimental */
    public function setUsbOutput(#[SensitiveParameter] DeviceId $device, bool $enabled): void
    {
        $this->send($device, ['usb_switch' => $enabled]);
    }

    /** @experimental Integer percentages 3-100; model compatibility may vary. */
    public function setAcChargingLimit(#[SensitiveParameter] DeviceId $device, int $percent): void
    {
        $this->send($device, ['ac_charging_limit' => $percent]);
    }

    /** @experimental Returns a separate instance bypassing only the code allow-list. */
    public function unsafe(): self
    {
        return new self($this->sessions, $this->credentials, $this->logger, $this->clock, $this->delay, true);
    }

    /**
     * @experimental Sends once. Never automatically replay an ambiguous outcome.
     *
     * @param  array<string, mixed>  $commands
     */
    public function send(#[SensitiveParameter] DeviceId $device, #[SensitiveParameter] array $commands): void
    {
        $commands = ControlValueParser::commands($commands, $this->unsafeMode);
        $this->sessions->withSessionOnce($this->credentials, function (Session $session, OukitelCloud $cloud) use ($device, $commands): void {
            if (! $cloud instanceof ControlCloud) {
                throw new ControlsUnavailable;
            }
            foreach (array_keys($commands) as $code) {
                $this->logger->notice('Sending experimental Oukitel control.', [
                    'device_suffix' => strlen($device->deviceKey) > 4 ? '****'.substr($device->deviceKey, -4) : '****',
                    'code' => $code,
                ]);
            }
            $cloud->control($session, $device, $commands);
        });
    }

    /**
     * @experimental Requires matching values and strictly newer numeric report timestamps.
     *
     * @param  array<string, mixed>  $commands
     */
    public function sendAndConfirm(
        #[SensitiveParameter] DeviceId $device,
        #[SensitiveParameter] array $commands,
        int $attempts = 5,
        int $delayMilliseconds = 1000,
        int $timeoutSeconds = 15,
    ): ControlResult {
        if ($attempts < 1 || $attempts > 100 || $delayMilliseconds < 0 || $delayMilliseconds > 60_000
            || $timeoutSeconds < 1 || $timeoutSeconds > 300) {
            throw new InvalidArgumentException('Confirmation requires 1-100 attempts, 0-60000ms delay and 1-300s timeout.');
        }
        $commands = ControlValueParser::commands($commands, $this->unsafeMode);
        $before = $this->observe($device, $commands);
        $this->send($device, $commands);
        $deadline = $this->now() + $timeoutSeconds;
        $observed = [];
        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            if ($attempt > 0) {
                $remainingMilliseconds = (int) floor(($deadline - $this->now()) * 1000);
                if ($remainingMilliseconds <= 0) {
                    break;
                }
                ($this->delay)(min($delayMilliseconds, $remainingMilliseconds));
            }
            if ($this->now() >= $deadline) {
                break;
            }
            $after = $this->observe($device, $commands);
            $observed = $after['observed'];
            if ($this->now() >= $deadline) {
                break;
            }
            $confirmed = true;
            foreach ($commands as $code => $desired) {
                if ($observed[$code] !== $desired || $before['timestamps'][$code] === null
                    || $after['timestamps'][$code] === null
                    || $after['timestamps'][$code] <= $before['timestamps'][$code]) {
                    $confirmed = false;
                    break;
                }
            }
            if ($confirmed) {
                return new ControlResult(true, $observed);
            }
        }

        return new ControlResult(false, $observed);
    }

    /**
     * @experimental
     *
     * @param  array<string, mixed>  $commands
     * @return array{observed: array<string, mixed>, timestamps: array<string, ?int>}
     */
    private function observe(#[SensitiveParameter] DeviceId $device, #[SensitiveParameter] array $commands): array
    {
        return ControlObservationParser::collect($this->sessions->rawAttributes($this->credentials, $device), $commands);
    }

    /** @experimental */
    private function now(): float
    {
        return (float) $this->clock->now()->format('U.u');
    }
}
