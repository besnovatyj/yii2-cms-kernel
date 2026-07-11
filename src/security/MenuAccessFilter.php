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
 * Фильтрация пунктов меню по правам — фасад ядра, чтобы представления (layout'ы) не зависели напрямую
 * от модуля безопасности.
 *
 * Решение «показывать ли пункт» делегируется тому же авторизатору (`accessAuthorizer`), что и гейт.
 * Без модуля безопасности авторизатор — {@see DenyAllAuthorizer}, поэтому пункты с маршрутом
 * отфильтровываются (меню пустое) — согласовано с закрытым по умолчанию приложением. С модулем `user`
 * это RBAC-фильтрация, эквивалентная прежней `Helper::filter`.
 *
 * Пункт с не-маршрутным `url` (строка, `#`) считается разрешённым — это заголовки/разделители, как и
 * в прежней реализации. Родитель показывается, если разрешён хотя бы один потомок.
 */
final class MenuAccessFilter
{
    /**
     * @param array          $items пункты меню (формат ProvidesAdminMenu: label/url/items/...)
     * @param User|null      $user  проверяемый пользователь; null — текущий `Yii::$app->user`
     * @return array отфильтрованные пункты
     */
    public static function filter(array $items, ?User $user = null): array
    {
        $user ??= Yii::$app->getUser();
        $authorizer = Yii::$app->get('accessAuthorizer', false);

        return self::filterRecursive(
            $items,
            $authorizer instanceof AccessAuthorizer ? $authorizer : null,
            $user?->getId(),
        );
    }

    private static function filterRecursive(array $items, ?AccessAuthorizer $authorizer, ?int $userId): array
    {
        $result = [];
        foreach ($items as $i => $item) {
            $url = $item['url'] ?? '#';
            $allow = is_array($url)
                ? ($authorizer !== null && $authorizer->isAllowed((string)$url[0], array_slice($url, 1), $userId))
                : true;

            if (isset($item['items']) && is_array($item['items'])) {
                $subItems = self::filterRecursive($item['items'], $authorizer, $userId);
                if ($subItems !== []) {
                    $allow = true;
                }
                $item['items'] = $subItems;
            }

            if ($allow && !($url === '#' && empty($item['items']))) {
                $result[$i] = $item;
            }
        }
        return $result;
    }
}
