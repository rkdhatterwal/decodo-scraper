<?php

namespace Rkdhatterwal\DecodoScraper\DTOs;

class TaskResponse
{
    public function __construct(
        public readonly string      $id,
        public readonly string      $status,
        public readonly string      $url,
        public readonly string      $createdAt,
        public readonly string      $updatedAt,
        public readonly ?string     $target,
        public readonly ?string     $query,
        public readonly string      $deviceType,
        public readonly ?string     $headless,
        public readonly bool        $parse,
        public readonly bool        $forceHeaders,
        public readonly bool        $forceCookies,
        public readonly ?string     $geo,
        public readonly ?string     $locale,
        public readonly string      $domain,
        public readonly string      $httpMethod,
        public readonly ?string     $sessionId,
        public readonly array       $headers,
        public readonly array       $cookies,
        public readonly array       $successfulStatusCodes,
        public readonly ?string     $payload,
        public readonly ?string     $callbackUrl,
        public readonly ?string     $passthrough,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id:                   $data['id']                     ?? '',
            status:               $data['status']                 ?? 'pending',
            url:                  $data['url']                    ?? '',
            createdAt:            $data['created_at']             ?? '',
            updatedAt:            $data['updated_at']             ?? '',
            target:               $data['target']                 ?? null,
            query:                $data['query']                  ?? null,
            deviceType:           $data['device_type']            ?? 'desktop',
            headless:             $data['headless']               ?? null,
            parse:                (bool) ($data['parse']          ?? false),
            forceHeaders:         (bool) ($data['force_headers']  ?? false),
            forceCookies:         (bool) ($data['force_cookies']  ?? false),
            geo:                  $data['geo']                    ?? null,
            locale:               $data['locale']                 ?? null,
            domain:               $data['domain']                 ?? 'com',
            httpMethod:           $data['http_method']            ?? 'get',
            sessionId:            $data['session_id']             ?? null,
            headers:              $data['headers']                ?? [],
            cookies:              $data['cookies']                ?? [],
            successfulStatusCodes: $data['successful_status_codes'] ?? [],
            payload:              $data['payload']                ?? null,
            callbackUrl:          $data['callback_url']           ?? null,
            passthrough:          $data['passthrough']            ?? null,
        );
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function isFaulted(): bool
    {
        return $this->status === 'faulted';
    }

    public function toArray(): array
    {
        return [
            'id'                      => $this->id,
            'status'                  => $this->status,
            'url'                     => $this->url,
            'created_at'              => $this->createdAt,
            'updated_at'              => $this->updatedAt,
            'target'                  => $this->target,
            'query'                   => $this->query,
            'device_type'             => $this->deviceType,
            'headless'                => $this->headless,
            'parse'                   => $this->parse,
            'force_headers'           => $this->forceHeaders,
            'force_cookies'           => $this->forceCookies,
            'geo'                     => $this->geo,
            'locale'                  => $this->locale,
            'domain'                  => $this->domain,
            'http_method'             => $this->httpMethod,
            'session_id'              => $this->sessionId,
            'headers'                 => $this->headers,
            'cookies'                 => $this->cookies,
            'successful_status_codes' => $this->successfulStatusCodes,
            'payload'                 => $this->payload,
            'callback_url'            => $this->callbackUrl,
            'passthrough'             => $this->passthrough,
        ];
    }
}
