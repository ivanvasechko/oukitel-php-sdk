<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Config;

use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Exceptions\ConfigurationMissing;

final readonly class RegionConfiguration
{
    public static function preset(Region $region): self
    {
        return RegionPreset::for($region);
    }

    public function __construct(
        public string $baseUrl,
        public string $appId,
        public string $appVersion,
        public string $userDomain,
        public string $userDomainSecret,
    ) {
        foreach ([$baseUrl, $appId, $appVersion, $userDomain, $userDomainSecret] as $value) {
            if (trim($value) === '' || preg_match('/[\r\n]/', $value) === 1) {
                throw new ConfigurationMissing;
            }
        }
        $url = parse_url($baseUrl);
        if ($url === false || ! isset($url['host'], $url['scheme'])
            || ! in_array($url['scheme'], ['https', 'http'], true)
            || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])) {
            throw new ConfigurationMissing;
        }
    }

    public static function fromArray(mixed $configuration): self
    {
        if (! is_array($configuration)) {
            throw new ConfigurationMissing;
        }
        $baseUrl = $configuration['base_url'] ?? null;
        $appId = $configuration['app_id'] ?? null;
        $appVersion = $configuration['app_version'] ?? null;
        $userDomain = $configuration['user_domain'] ?? null;
        $userDomainSecret = $configuration['user_domain_secret'] ?? null;
        if (! is_string($baseUrl) || ! is_string($appId) || ! is_string($appVersion)
            || ! is_string($userDomain) || ! is_string($userDomainSecret)) {
            throw new ConfigurationMissing;
        }

        return new self(
            rtrim($baseUrl, '/'),
            $appId,
            $appVersion,
            $userDomain,
            $userDomainSecret,
        );
    }

    public function withBaseUrl(string $value): self
    {
        return new self(rtrim($value, '/'), $this->appId, $this->appVersion, $this->userDomain, $this->userDomainSecret);
    }

    public function withAppId(string $value): self
    {
        return new self($this->baseUrl, $value, $this->appVersion, $this->userDomain, $this->userDomainSecret);
    }

    public function withAppVersion(string $value): self
    {
        return new self($this->baseUrl, $this->appId, $value, $this->userDomain, $this->userDomainSecret);
    }

    public function withUserDomain(string $value): self
    {
        return new self($this->baseUrl, $this->appId, $this->appVersion, $value, $this->userDomainSecret);
    }

    public function withUserDomainSecret(string $value): self
    {
        return new self($this->baseUrl, $this->appId, $this->appVersion, $this->userDomain, $value);
    }
}
