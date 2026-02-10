<?php

namespace App\Integrations\TrendAgent\Auth;

use App\Models\Domain\TrendAgent\TaSsoSession;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class TrendAuthService
{
    public function __construct(
        protected TrendSsoClient $sso,
    ) {
    }

    /**
     * Убедиться, что есть активная SSO‑сессия и вернуть её.
     *
     * @throws TrendAgentNotAuthenticatedException
     */
    public function ensureSession(): TaSsoSession
    {
        /** @var TaSsoSession|null $session */
        $session = TaSsoSession::query()
            ->where('provider', 'trendagent')
            ->where('is_active', true)
            ->orderByDesc('last_login_at')
            ->first();

        if (! $session || ! $session->refresh_token) {
            throw new TrendAgentNotAuthenticatedException('TrendAgent SSO session is not available.');
        }

        return $session;
    }

    /**
     * Выполнить логин и сохранить refresh_token в БД. Сохраняет только при ok=true и непустом refresh_token.
     * app_id извлекается из refresh_token payload и сохраняется в БД.
     *
     * @throws TrendAgentNotAuthenticatedException если login вернул needs_manual_token или токен пустой
     */
    public function loginAndStoreSession(string $phone, string $password, string $lang = 'ru'): TaSsoSession
    {
        $result = $this->sso->login($phone, $password, $lang);

        if (($result['ok'] ?? false) !== true) {
            $reason = $result['reason'] ?? 'needs_manual_token';
            throw new TrendAgentNotAuthenticatedException('SSO login not saved: ' . $reason);
        }

        $refreshToken = $result['refresh_token'] ?? null;
        if ($refreshToken === null || $refreshToken === '') {
            throw new TrendAgentNotAuthenticatedException('SSO login not saved: token_not_found');
        }

        $appId = $this->sso->extractAppIdFromRefreshToken($refreshToken);
        return $this->storeSessionFromRefreshToken($phone, $refreshToken, null, $appId);
    }

    /**
     * Сохранить/обновить сессию на основе уже известного refresh_token. city_id и app_id подставляются из config/JWT если null.
     */
    public function storeSessionFromRefreshToken(string $phone, ?string $refreshToken, ?string $cityId = null, ?string $appId = null): TaSsoSession
    {
        $cityId = $cityId ?? \Illuminate\Support\Facades\Config::get('trendagent.default_city_id');

        if ($appId === null && $refreshToken !== null) {
            $appId = $this->sso->extractAppIdFromRefreshToken($refreshToken);
        }

        $session = TaSsoSession::updateOrCreate(
            [
                'provider' => 'trendagent',
                'phone'    => $phone,
            ],
            [
                'refresh_token'      => $refreshToken,
                'app_id'             => $appId,
                'city_id'            => $cityId,
                'last_login_at'      => now(),
                'last_auth_token_at' => null,
                'is_active'          => true,
                'invalidated_at'     => null,
            ],
        );

        // Все остальные сессии провайдера делаем неактивными.
        TaSsoSession::query()
            ->where('provider', 'trendagent')
            ->where('id', '!=', $session->id)
            ->update([
                'is_active'      => false,
                'invalidated_at' => now(),
            ]);

        return $session;
    }

    /**
     * Получить auth_token на основе текущего refresh_token.
     *
     * @throws \InvalidArgumentException если cityId пустой
     * @throws TrendAgentNotAuthenticatedException
     */
    public function getAuthToken(string $cityId, string $lang = 'ru'): string
    {
        $cityId = trim($cityId);
        if ($cityId === '') {
            throw new \InvalidArgumentException('TrendAgent city_id is required for getAuthToken (set TRENDAGENT_DEFAULT_CITY_ID or pass non-empty cityId).');
        }

        $cacheKey = $this->makeCacheKey($cityId, $lang);

        return Cache::remember($cacheKey, 240, function () use ($cityId, $lang) {
            $session = $this->ensureSession();

            try {
                $token = $this->sso->getAuthToken($session->refresh_token, $cityId, $lang, $session->app_id);
            } catch (RuntimeException $e) {
                $code = (int) $e->getCode();
                // Обнуляем refresh_token только при явном 401/403 от SSO (rejected).
                if ($code === 401 || $code === 403) {
                    $session->refresh_token = null;
                    $session->is_active = false;
                    $session->invalidated_at = now();
                    $session->save();
                }

                throw new TrendAgentNotAuthenticatedException($e->getMessage(), 0, $e);
            }

            $session->last_auth_token_at = now();
            $session->save();

            return $token;
        });
    }

    /**
     * Получить auth_token для конкретной сессии (без кеша). Для проверки/диагностики.
     *
     * @throws \InvalidArgumentException если city_id пустой
     * @throws TrendAgentNotAuthenticatedException
     */
    public function getAuthTokenForSession(TaSsoSession $session): string
    {
        if (! $session->refresh_token || $session->refresh_token === '') {
            throw new TrendAgentNotAuthenticatedException('Session has no refresh_token.');
        }

        $cityId = $session->city_id ?? \Illuminate\Support\Facades\Config::get('trendagent.default_city_id');
        $cityId = trim((string) $cityId);
        if ($cityId === '') {
            throw new \InvalidArgumentException('city_id required (session has none, set TRENDAGENT_DEFAULT_CITY_ID).');
        }

        $lang = (string) \Illuminate\Support\Facades\Config::get('trendagent.default_lang', 'ru');

        return $this->sso->getAuthToken($session->refresh_token, $cityId, $lang, $session->app_id);
    }

    /**
     * Сбросить кеш auth_token для города/языка.
     */
    public function invalidate(string $cityId, string $lang = 'ru'): void
    {
        Cache::forget($this->makeCacheKey($cityId, $lang));
    }

    private function makeCacheKey(string $cityId, string $lang): string
    {
        return sprintf('trendagent.auth_token.%s.%s', $cityId, $lang);
    }
}

