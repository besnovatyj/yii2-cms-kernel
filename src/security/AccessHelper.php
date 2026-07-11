<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Kernel\security;

use Besnovatyj\Contracts\security\AccessAuthorizer;
use Yii;
use yii\web\User;

/**
 * Фасад проверки доступа к маршрутам для представлений/виджетов — чтобы модули фильтровали кнопки
 * действий и пункты интерфейса, НЕ завися напрямую от модуля безопасности (`user`).
 *
 * Заменяет прежний `Besnovatyj\User\components\Helper` в его роли «пускать ли по маршруту»:
 * {@see checkRoute()} и {@see filterActionColumn()} (для колонок GridView). Решение делегируется
 * компоненту `accessAuthorizer` (контракт {@see AccessAuthorizer}); вся RBAC-логика остаётся в модуле
 * `user` за его RbacAuthorizer. Без модуля безопасности авторизатор — DenyAllAuthorizer, поэтому
 * действия/кнопки скрываются (fail-closed), что согласовано с закрытым по умолчанию приложением.
 *
 * Нормализация маршрута ({@see normalizeRoute()}) — общая Yii-логика (относительное имя действия →
 * полный маршрут), к RBAC отношения не имеет, поэтому живёт здесь.
 */
final class AccessHelper
{
    /**
     * Разрешён ли текущему (или заданному) пользователю доступ к маршруту.
     *
     * @param array|string $route  маршрут (относительный или абсолютный, как в url меню/кнопок)
     * @param array        $params параметры запроса для RBAC-правил
     * @param User|int|null $user   пользователь/его id; null — текущий
     */
    public static function checkRoute(array|string $route, array $params = [], User|int|null $user = null): bool
    {
        $authorizer = Yii::$app->get('accessAuthorizer', false);
        if (!$authorizer instanceof AccessAuthorizer) {
            return false; // fail-closed: нет авторизатора — нет доступа
        }

        if ($user instanceof User) {
            $userId = $user->getId();
        } elseif (is_int($user)) {
            $userId = $user;
        } else {
            $userId = Yii::$app->getUser()->getId();
        }
        $userId = $userId === null ? null : (int)$userId;

        return $authorizer->isAllowed(self::normalizeRoute($route), $params, $userId);
    }

    /**
     * Шаблон колонки действий GridView, отфильтрованный по правам. Использование:
     * ```php
     * ['class' => ActionColumn::class, 'template' => AccessHelper::filterActionColumn(['view','update','delete'])]
     * ```
     * Принимает массив имён кнопок или строку-шаблон `'{view} {update} {delete}'`.
     *
     * @param array|string  $buttons имена кнопок или строка-шаблон
     * @param User|int|null  $user    пользователь; null — текущий
     */
    public static function filterActionColumn(array|string $buttons = [], User|int|null $user = null): string
    {
        if (is_array($buttons)) {
            $result = [];
            foreach ($buttons as $button) {
                if (static::checkRoute($button, [], $user)) {
                    $result[] = "{{$button}}";
                }
            }
            return implode(' ', $result);
        }

        return preg_replace_callback('/\{([\w\-\/]+)\}/', static function (array $m) use ($user): string {
            return static::checkRoute($m[1], [], $user) ? "{{$m[1]}}" : '';
        }, $buttons);
    }

    /**
     * Приведение маршрута к абсолютному виду `/{module}/{controller}/{action}` в контексте текущего
     * контроллёра. Идентично прежней нормализации в `user\Helper` — чистая Yii-логика без RBAC.
     */
    private static function normalizeRoute(array|string $route): string
    {
        $route = is_array($route) ? (string)($route[0] ?? '') : $route;

        if ($route === '') {
            return '/' . Yii::$app->controller->getRoute();
        }
        if (str_starts_with($route, '/')) {
            return $route;
        }
        if (!str_contains($route, '/')) {
            return '/' . Yii::$app->controller->getUniqueId() . '/' . $route;
        }
        if (($mid = Yii::$app->controller->module->getUniqueId()) !== '') {
            return '/' . $mid . '/' . $route;
        }
        return '/' . $route;
    }
}
