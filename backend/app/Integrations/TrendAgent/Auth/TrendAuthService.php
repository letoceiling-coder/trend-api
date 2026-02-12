<?php

namespace App\Integrations\TrendAgent\Auth;

use App\Models\Domain\TrendAgent\TaReloginEvent;
use App\Models\Domain\TrendAgent\TaSsoSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TrendAuthService
{
    private const RELOGIN_RATE_TTL_MINUTES = 10;
    private const RELOGIN_COOLDOWN_MINUTES = 30;
    private const RELOGIN_FAILURES_THRESHOLD = 2;
    private const RATE_KEY_PREFIX = 'ta:auth:relogin:last:';
    private const COOLDOWN_KEY = 'ta:auth:relogin:cooldown';
    private const FAILURES_KEY_PREFIX = 'ta:auth:relogin:failures:';
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
     * Self-healing: получить auth_token, при отсутствии/отклонении сессии
     * при TRENDAGENT_AUTO_RELOGIN=true выполнить один programmatic login и повторить.
     * Rate-limit: не чаще 1 relogin в 10 мин на (phone, city_id). Cooldown 30 мин при частых неудачах.
     *
     * @throws \InvalidArgumentException если cityId пустой
     * @throws TrendAgentNotAuthenticatedException
     */
    public function ensureAuthenticated(string $cityId, string $lang = 'ru'): string
    {
        $cityId = trim($cityId);
        if ($cityId === '') {
            throw new \InvalidArgumentException('TrendAgent city_id is required for ensureAuthenticated (set TRENDAGENT_DEFAULT_CITY_ID or pass non-empty cityId).');
        }

        try {
            return $this->getAuthToken($cityId, $lang);
        } catch (TrendAgentNotAuthenticatedException $e) {
            if (! Config::get('trendagent.auto_relogin', false)) {
                throw $e;
            }

            $phone = Config::get('trendagent.default_phone');
            $password = Config::get('trendagent.default_password');
            if ($phone === null || $phone === '' || $password === null || $password === '') {
                throw $e;
            }

            $rateKey = self::RATE_KEY_PREFIX . $this->reloginSlotKey($phone, $cityId);
            if (Cache::has($rateKey)) {
                throw new TrendAgentNotAuthenticatedException(
                    'Relogin rate-limited (max once per ' . self::RELOGIN_RATE_TTL_MINUTES . ' min).',
                    0,
                    $e
                );
            }

            if (Cache::has(self::COOLDOWN_KEY)) {
                throw new TrendAgentNotAuthenticatedException(
                    'Relogin in cooldown (too many recent failures).',
                    0,
                    $e
                );
            }

            try {
                $this->loginAndStoreSession($phone, $password, $lang);
            } catch (TrendAgentNotAuthenticatedException $loginEx) {
                $this->recordReloginAttempt($cityId, false);
                $this->incrementReloginFailures($phone, $cityId);
                throw $loginEx;
            } catch (\Throwable $loginEx) {
                $this->recordReloginAttempt($cityId, false);
                $this->incrementReloginFailures($phone, $cityId);
                throw new TrendAgentNotAuthenticatedException(
                    'Auto-relogin failed: ' . $this->sanitizeForLog($loginEx->getMessage()),
                    0,
                    $loginEx
                );
            }

            $this->recordReloginAttempt($cityId, true);
            $this->clearReloginFailures($phone, $cityId);
            Cache::put($rateKey, true, self::RELOGIN_RATE_TTL_MINUTES * 60);
            $this->invalidate($cityId, $lang);

            Log::info('TrendAgent auto-relogin succeeded', [
                'phone_masked' => self::maskPhone($phone),
                'city_id' => $cityId,
            ]);

            return $this->getAuthToken($cityId, $lang);
        }
    }

    private function reloginSlotKey(string $phone, string $cityId): string
    {
        return hash('sha256', $phone . ':' . $cityId);
    }

    private function recordReloginAttempt(string $cityId, bool $success): void
    {
        TaReloginEvent::create([
            'attempted_at' => now(),
            'success' => $success,
            'city_id' => $cityId,
        ]);
    }

    private function incrementReloginFailures(string $phone, string $cityId): void
    {
        $key = self::FAILURES_KEY_PREFIX . $this->reloginSlotKey($phone, $cityId);
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, self::RELOGIN_RATE_TTL_MINUTES * 60);
        if ($count >= self::RELOGIN_FAILURES_THRESHOLD) {
            Cache::put(self::COOLDOWN_KEY, true, self::RELOGIN_COOLDOWN_MINUTES * 60);
        }
    }

    private function clearReloginFailures(string $phone, string $cityId): void
    {
        Cache::forget(self::FAILURES_KEY_PREFIX . $this->reloginSlotKey($phone, $cityId));
    }

    /**
     * Маскировать телефон для логов (оставляем начало и конец).
     */
    public static function maskPhone(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '***';
        }
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) < 4) {
            return '***';
        }
        return substr($digits, 0, 3) . '***' . substr($digits, -2);
    }

    /**
     * Убрать из строки токены/пароли (для логов и исключений).
     */
    protected function sanitizeForLog(string $message): string
    {
        return preg_replace(
            '/(auth_token|refresh_token|token|access_token|password|Bearer\s+)[\w\-\.]+/i',
            '$1***',
            $message
        );
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

