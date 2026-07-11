<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Kernel\security;

use Besnovatyj\Contracts\security\AccessAuthorizer;

/**
 * Авторизатор «запретить всё» — дефолт ядра.
 *
 * Ядро связывает компонент `accessAuthorizer` с этой реализацией по умолчанию, поэтому у гейта всегда
 * есть кого спросить (биндинг никогда не пустой — нечему сломаться в fail-open). Пока не установлен
 * модуль безопасности (`user`), закрытые приложения (backend, rest) закрыты полностью: единственный
 * доступ — маршруты из whitelist гейта (страница ошибки/установки). Модуль `user` через
 * {@see \Besnovatyj\Contracts\module\ProvidesAppConfig} перекрывает `accessAuthorizer` своей
 * RBAC-реализацией.
 */
final class DenyAllAuthorizer implements AccessAuthorizer
{
    public function isAllowed(string $route, array $params = [], ?int $userId = null): bool
    {
        return false;
    }
}
