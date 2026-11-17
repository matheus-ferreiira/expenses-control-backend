<?php

namespace App\Domains\BankSync\Services;

use App\Domains\BankSync\Exceptions\PluggyException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pluggy API (https://docs.pluggy.ai). The client id/secret never leave the backend: they are exchanged
 * for an API key (valid 2 h, cached for less) and the browser only ever gets a 30-minute connect token.
 */
class PluggyClient
{
    private const API_KEY_CACHE = 'pluggy:api-key';

    public function configured(): bool
    {
        return filled(config('services.pluggy.client_id')) && filled(config('services.pluggy.client_secret'));
    }

    /** Token for the Pluggy Connect widget, tied to our user id (checked again when the item comes back). */
    public function createConnectToken(string $clientUserId, ?string $itemId = null): string
    {
        // No avoidDuplicates: MeuPluggy items have no credentials (OAuth), so Pluggy sees every new bank
        // as a duplicate of the first one and refuses it (400 ITEM_USER_ALREADY_EXISTS)
        $body = ['options' => ['clientUserId' => $clientUserId]];
        if ($itemId !== null) {
            $body['itemId'] = $itemId;
        }

        $token = $this->send('post', '/connect_token', $body)->json('accessToken');
        if (! is_string($token) || $token === '') {
            throw PluggyException::unavailable();
        }

        return $token;
    }

    /** @return array<string, mixed>|null null when Pluggy does not know the item */
    public function item(string $itemId): ?array
    {
        $response = $this->send('get', '/items/'.rawurlencode($itemId), allowNotFound: true);

        return $response->status() === 404 ? null : $response->json();
    }

    public function deleteItem(string $itemId): void
    {
        $this->send('delete', '/items/'.rawurlencode($itemId), allowNotFound: true);
    }

    /** @return list<array<string, mixed>> */
    public function accounts(string $itemId): array
    {
        return $this->send('get', '/accounts', ['itemId' => $itemId])->json('results') ?? [];
    }

    /**
     * Every transaction of an account (cursor pagination, 500 per page), optionally from a date on.
     *
     * @return list<array<string, mixed>>
     */
    public function transactions(string $accountId, ?string $dateFrom = null): array
    {
        $query = array_filter(['accountId' => $accountId, 'dateFrom' => $dateFrom]);
        $all = [];
        for ($page = 0; $page < 200; $page++) {
            $response = $this->send('get', '/v2/transactions', $query);
            array_push($all, ...($response->json('results') ?? []));
            $next = $response->json('next');
            if (! is_string($next) || $next === '') {
                return $all;
            }
            parse_str(ltrim($next, '?'), $nextQuery);
            if (empty($nextQuery['after'])) {
                return $all;
            }
            $query = ['accountId' => $accountId, 'after' => $nextQuery['after']] + array_filter(['dateFrom' => $dateFrom]);
        }

        return $all;
    }

    private function send(string $method, string $path, array $data = [], bool $allowNotFound = false, bool $retry = true): Response
    {
        try {
            $response = $this->request()->withHeaders(['X-API-KEY' => $this->apiKey()])->{$method}($path, $data);
        } catch (ConnectionException) {
            throw PluggyException::unavailable();
        }

        // An API key revoked before its cached lifetime ends: get a new one once
        if ($response->status() === 401 && $retry) {
            Cache::forget(self::API_KEY_CACHE);

            return $this->send($method, $path, $data, $allowNotFound, false);
        }
        if ($allowNotFound && $response->status() === 404) {
            return $response;
        }
        if (! $response->successful()) {
            Log::warning('Pluggy request failed', ['path' => $path, 'status' => $response->status(), 'code' => $response->json('code')]);
            throw PluggyException::unavailable();
        }

        return $response;
    }

    private function apiKey(): string
    {
        if (! $this->configured()) {
            throw PluggyException::notConfigured();
        }

        return Cache::remember(self::API_KEY_CACHE, now()->addMinutes(100), function (): string {
            try {
                $response = $this->request()->post('/auth', [
                    'clientId' => config('services.pluggy.client_id'),
                    'clientSecret' => config('services.pluggy.client_secret'),
                ]);
            } catch (ConnectionException) {
                throw PluggyException::unavailable();
            }
            $key = $response->json('apiKey');
            if (! $response->successful() || ! is_string($key) || $key === '') {
                Log::warning('Pluggy authentication failed', ['status' => $response->status()]);
                throw new PluggyException(502, 'A Pluggy recusou as credenciais do servidor. Confira PLUGGY_CLIENT_ID e PLUGGY_CLIENT_SECRET.');
            }

            return $key;
        });
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.pluggy.base_url'), '/'))
            ->acceptJson()->asJson()->connectTimeout(10)->timeout(30);
    }
}
