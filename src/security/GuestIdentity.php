<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Kernel\security;

use yii\web\IdentityInterface;

/**
 * Провайдер идентичности «никого» — дефолтный `identityClass` ядра.
 *
 * Пока не установлен модуль безопасности (`user`), в приложении нет способа кого-либо
 * аутентифицировать: {@see findIdentity()}/{@see findIdentityByAccessToken()} всегда возвращают null,
 * поэтому залогиниться нельзя в принципе. Для закрытых приложений это и есть безопасный минимум:
 * гость остаётся гостем, а гейт (deny-by-default) не пускает его никуда, кроме whitelist. Модуль
 * `user` через {@see \Besnovatyj\Contracts\module\ProvidesAppConfig} подменяет `user.identityClass`
 * на свой рабочий Identity.
 */
final class GuestIdentity implements IdentityInterface
{
    public static function findIdentity($id): ?IdentityInterface
    {
        return null;
    }

    public static function findIdentityByAccessToken($token, $type = null): ?IdentityInterface
    {
        return null;
    }

    public function getId(): null
    {
        return null;
    }

    public function getAuthKey(): null
    {
        return null;
    }

    public function validateAuthKey($authKey): bool
    {
        return false;
    }
}
