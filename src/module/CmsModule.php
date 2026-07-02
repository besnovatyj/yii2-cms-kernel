<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Kernel\module;

use Besnovatyj\Contracts\theme\LayoutPathProvider;
use Yii;
use yii\base\Module;

/**
 * Тонкий рантайм-базовый класс модулей системы управления (modman).
 *
 * Несёт ТОЛЬКО общее поведение экземпляра Yii-модуля, которое нельзя выразить статическим
 * контрактом (интерфейс задаёт сигнатуру, но не тело метода-override):
 *  - {@see init()} — дефолтная раскладка controllerNamespace по контексту приложения;
 *  - {@see getLayoutPath()} — каталог layout'ов из активной темы (темизация модуля).
 *
 * Метаданные модуля (id, версия, вклады) сюда НЕ входят — они объявляются типизированными
 * контрактами `Besnovatyj\Contracts\module\*`. Поэтому модуль одновременно `extends CmsModule`
 * (поведение) и `implements DeclaresModule, Provides...` (метаданные) — это разные оси, без
 * конфликта. В отличие от legacy {@see BaseModule}, здесь нет `method_exists`-магии и abstract-методов
 * получения конфигурации.
 *
 * Любой метод можно переопределить в конкретном модуле (например, менеджер переопределяет init).
 */
abstract class CmsModule extends Module
{
    /**
     * Дефолтная настройка пространства имён контроллёров по контексту приложения + DI способа A.
     *
     * Раскладка namespace совпадает со старым {@see BaseModule}:
     *  - app-frontend → `<ns>\controllers\frontend` (+ layout 'main'); URL без сегмента `/frontend/`;
     *  - app-console  → `<ns>\commands`;
     *  - app-backend (и прочее) → дефолт `<ns>\controllers`; поэтому URL включает сегмент `/backend/`.
     *
     * Если устанавливаем controllerNamespace -> ссылки вида `/{moduleName}/{controllerName}/{actionName}`
     * Если дефолтный controllerNamespace     -> ссылки вида `/{moduleName}/rest/{controllerName}/{actionName}`
     * (Доступные на данный момент приложения: 'id' => 'app-frontend', 'id' => 'app-backend', 'id' => 'app-console', 'id' => 'app-rest')
     * Значения общие для всех модулей сейчас, но модуль вправе переопределить init/хуки под себя.
     */
    public function init(): void
    {
        // parent::init() уже выставил controllerNamespace в `<ns>\controllers` (через strrpos/substr).
        // Достраиваем готовое значение, а не пересчитываем namespace заново.
        parent::init();

        if (Yii::$app->id === 'app-frontend') {
            $this->controllerNamespace .= '\\frontend';
            $this->layout = 'main';
        } elseif (Yii::$app->id === 'app-console') {
            // Консольные контроллёры лежат в `<ns>\commands`, а не под `\controllers`.
            $this->controllerNamespace = substr($this->controllerNamespace, 0, -strlen('controllers')) . 'commands';
        }

        $this->bootstrapContainer();
    }

    /**
     * Способ A проводки DI: настройки контейнера, нужные ТОЛЬКО самому модулю, из его
     * `/config/container.php`. Применяется лениво при инициализации модуля. Замена `method_exists`
     * автовызова `setContainerConfig` из старого {@see BaseModule} на проверку файла-конвенции.
     *
     * Способы B (Bootstrap-класс в composer.extra) и C ({@see \Besnovatyj\Contracts\module\ProvidesBootstrap})
     * — для глобальной/устанавливаемой проводки, не здесь. Модуль с особыми требованиями (например,
     * менеджер, чей контейнер грузится глобально через Bootstrap) переопределяет этот хук со своим guard.
     */
    protected function bootstrapContainer(): void
    {
        $file = $this->getBasePath() . '/config/container.php';
        if (is_file($file)) {
            (require $file)(Yii::$container);
        }
    }

    /**
     * Каталог layout'ов берётся из активной темы — чтобы layout модуля темизировался вместе с темой.
     *
     * Сделано здесь (а не в init): на момент init компонент `view`/`theme` ещё не поднят. Метод
     * вызывается Yii лениво при рендере, когда тема уже доступна. Если тема не наша (или не задана —
     * например, на бэкенде) — тихо откатываемся к дефолту `yii\base\Module` (`@module/views/layouts`).
     */
    public function getLayoutPath(): string
    {
        $theme = Yii::$app->view->theme ?? null;
        if ($theme instanceof LayoutPathProvider) {
            try {
                $this->setLayoutPath($theme->getLayoutsPath());
            } catch (\Throwable $e) {
                Yii::warning('Не удалось получить layouts из темы: ' . $e->getMessage(), 'common/module');
            }
        }

        return parent::getLayoutPath();
    }
}
