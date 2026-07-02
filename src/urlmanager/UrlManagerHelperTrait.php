<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

namespace Besnovatyj\Kernel\urlmanager;

use DomainException;
use Yii;
use yii\base\InvalidConfigException;
use yii\web\UrlManager;

trait UrlManagerHelperTrait
{
    /**
     * Генерирует абсолютную ссылку для фронтэнда, принимая путь и идентификатор сущности
     * @throws InvalidConfigException
     */
    public function getAbsoluteFrontendRoute(string $entityRoute, array $params): string
    {
        return $this->getFrontendUrlManager()->createAbsoluteUrl([$entityRoute, ...$params]);
    }

    /**
     * Генерирует относительную ссылку для фронтэнда, принимая путь и идентификатор сущности
     * @throws InvalidConfigException
     */
    public function getFrontendRoute(string $entityRoute, array $params): string
    {
        return $this->getFrontendUrlManager()->createUrl([$entityRoute, ...$params]);
    }

    /**
     * Получает из приложения frontendUrlManager
     * @throws InvalidConfigException
     */
    protected function getFrontendUrlManager(): UrlManager
    {
        $frontendUrlManager = Yii::$app->get('frontendUrlManager');
        if ($frontendUrlManager instanceof UrlManager) {
            return $frontendUrlManager;
        }
        throw new DomainException('Отсутствует экземпляр \'\yii\web\UrlManager\' для фронтэнда.');
    }
}
