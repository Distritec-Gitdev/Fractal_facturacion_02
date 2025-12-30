<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class ChatSeen
{
    public static int $ttlDays = 7;

    public static function key(int $chatId, int $userId): string
    {
        return "chat:last_seen:{$chatId}:{$userId}";
    }

    public static function get(int $chatId, int $userId): int
    {
        return (int) Cache::get(self::key($chatId, $userId), 0);
    }

    public static function put(int $chatId, int $userId, int $lastId): void
    {
        if ($lastId <= 0) return;

        $key = self::key($chatId, $userId);
        $current = (int) Cache::get($key, 0);
        $value = max($current, $lastId);

        $ttl = self::$ttlDays > 0 ? now()->addDays(self::$ttlDays) : null;
        $ttl ? Cache::put($key, $value, $ttl) : Cache::forever($key, $value);
    }

    public static function forget(int $chatId, int $userId): void
    {
        Cache::forget(self::key($chatId, $userId));
    }
}
