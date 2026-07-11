<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Kernel\security;

use Besnovatyj\Contracts\security\AccessAuthorizer;
use Yii;
use yii\base\ActionFilter;
use yii\base\InvalidConfigException;
use yii\base\Module;
use yii\di\Instance;
use yii\web\ForbiddenHttpException;
use yii\web\User;

/**
 * Гейт закрытого приложения: «запретить всё, кроме явно разрешённого» — принадлежит ЯДРУ.
 *
 * Объявляется в конфиге закрытого приложения как поведение `as access` и остаётся там независимо от
 * установленных модулей — модуль не может его ни добавить, ни снять. Само решение «можно/нельзя» гейт
 * делегирует авторизатору (компонент `accessAuthorizer`, контракт {@see AccessAuthorizer}); ядро по
 * умолчанию связывает его с {@see DenyAllAuthorizer}, а модуль `user` перекрывает RBAC-реализацией.
 *
 * FAIL-CLOSED: если авторизатор не резолвится или не реализует контракт — доступ ЗАПРЕЩАЕТСЯ, а не
 * пропускается. Так «забыть/сломать авторизатор» никогда не открывает закрытое приложение.
 *
 * ```php
 * 'as access' => [
 *     'class' => \Besnovatyj\Kernel\security\DefaultDenyAccessControl::class,
 *     'allowActions' => ['site/error'],
 * ]
 * ```
 * @property User $user
 */
class DefaultDenyAccessControl extends ActionFilter
{
    /** @var array Маршруты, доступные без проверки (whitelist ядра + вклад модулей через allowActions). */
    public array $allowActions = [];

    /** @var string|AccessAuthorizer id компонента-авторизатора или сам объект. */
    public string|AccessAuthorizer $authorizer = 'accessAuthorizer';

    /** @var string|User Пользователь, чей доступ проверяется. */
    private string|User $_user = 'user';

    /**
     * @throws InvalidConfigException
     * @throws ForbiddenHttpException
     */
    public function beforeAction($action): bool
    {
        $route = '/' . $action->getUniqueId();
        $user = $this->getUser();
        $authorizer = $this->resolveAuthorizer();

        if ($authorizer !== null
            && $authorizer->isAllowed($route, Yii::$app->getRequest()->get(), $user->getId())
        ) {
            return true;
        }

        $this->denyAccess($user);
        return false;
    }

    /**
     * Резолв авторизатора. Отсутствие/несоответствие контракту трактуется как «нет авторизатора» —
     * fail-closed (гейт запретит доступ). Исключения наружу не пробрасываем, чтобы поломка биндинга
     * приводила к запрету, а не к 500 с открытым обходом.
     */
    private function resolveAuthorizer(): ?AccessAuthorizer
    {
        if ($this->authorizer instanceof AccessAuthorizer) {
            return $this->authorizer;
        }
        $resolved = Yii::$app->get((string)$this->authorizer, false);
        return $resolved instanceof AccessAuthorizer ? $resolved : null;
    }

    /**
     * @return User
     * @throws InvalidConfigException
     */
    public function getUser(): User
    {
        if (!$this->_user instanceof User) {
            $this->_user = Instance::ensure($this->_user, User::class);
        }
        return $this->_user;
    }

    public function setUser(User|string $user): void
    {
        $this->_user = $user;
    }

    /**
     * Реализация по умолчанию: гостя перенаправляет на вход, авторизованному — 403.
     *
     * @throws ForbiddenHttpException если пользователь уже вошёл в систему.
     */
    protected function denyAccess(User $user): void
    {
        if ($user->getIsGuest()) {
            $user->loginRequired();
        } else {
            throw new ForbiddenHttpException(Yii::t('yii', 'You are not allowed to perform this action.'));
        }
    }

    /**
     * Пропускаем проверку для страницы ошибки, страницы входа (гостю), whitelist `allowActions`
     * (точное совпадение или префикс `*`) и метода-опт-аута `allowAction()` контроллёра.
     */
    protected function isActive($action): bool
    {
        $uniqueId = $action->getUniqueId();
        if ($uniqueId === Yii::$app->getErrorHandler()->errorAction) {
            return false;
        }

        $user = $this->getUser();
        if ($user->getIsGuest()) {
            $loginUrl = null;
            if (is_array($user->loginUrl) && isset($user->loginUrl[0])) {
                $loginUrl = $user->loginUrl[0];
            } elseif (is_string($user->loginUrl)) {
                $loginUrl = $user->loginUrl;
            }
            if ($loginUrl !== null && trim((string)$loginUrl, '/') === $uniqueId) {
                return false;
            }
        }

        if ($this->owner instanceof Module) {
            $mid = $this->owner->getUniqueId();
            $id = $uniqueId;
            if ($mid !== '' && str_starts_with($id, $mid . '/')) {
                $id = substr($id, strlen($mid) + 1);
            }
        } else {
            $id = $action->id;
        }

        foreach ($this->allowActions as $route) {
            if (str_ends_with($route, '*')) {
                $route = rtrim((string)$route, '*');
                if ($route === '' || str_starts_with($id, $route)) {
                    return false;
                }
            } elseif ($id === $route) {
                return false;
            }
        }

        if ($action->controller->hasMethod('allowAction')
            && in_array($action->id, $action->controller->allowAction(), true)
        ) {
            return false;
        }

        return true;
    }
}
