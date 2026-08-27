<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

namespace Besnovatyj\Kernel\controller;

use Throwable;
use Yii;
use yii\helpers\VarDumper;
use yii\web\Response;

trait ControllerTrait
{
    /**
     * Безопасный редирект на страницу, с которой пришёл запрос.
     * Если Referer пуст или ведёт на чужой источник — уходит на $fallback.
     * Не имеет аналогов внутри Yii2, `\yii\web\Controller::goBack()`, `\yii\web\Controller::refresh()` предназначены для других целей
     *
     * @param array|string $fallback локальный маршрут/URL по умолчанию
     * @return Response
     */
    public function goReferer(array|string $fallback = ['index']): Response
    {
        $referer = Yii::$app->getRequest()->getReferrer();
        $localPath = $referer !== null ? $this->extractLocalPath($referer) : null;

        return $this->redirect($localPath ?? $fallback);
    }

    /**
     * Возвращает из абсолютного URL относительный путь (`/path?query`), если URL указывает
     * на хост текущего приложения, иначе `null`.
     *
     * Эталонный хост берётся из `\yii\web\UrlManager::$hostInfo` — то есть из конфигурации
     * приложения, а не из заголовков запроса: `\yii\web\Request::getHostName()` вычисляется
     * из `Host`/`X-Forwarded-Host`, которые присылает клиент. Если `hostInfo` в конфигурации
     * UrlManager не задан, Yii деградирует к данным запроса — тогда проверка не строже
     * прежней, но и не слабее.
     *
     * Схема НЕ сверяется намеренно: за TLS-терминирующим прокси приложение может видеть
     * соединение как `http`, тогда как браузер присылает Referer с `https`. Сверяется только
     * имя хоста — без схемы и порта.
     *
     * Редирект отдаётся относительным путём, а не исходным URL: это исключает open redirect,
     * в том числе через protocol-relative путь вида `//evil.com`.
     */
    private function extractLocalPath(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }

        $host = $parts['host'] ?? null;
        $trustedHost = parse_url((string)Yii::$app->getUrlManager()->getHostInfo(), PHP_URL_HOST);
        if ($host === null || !is_string($trustedHost) || strcasecmp($host, $trustedHost) !== 0) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        // Одиночный ведущий слэш обязателен: `//evil.com` браузер трактует как чужой хост
        if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }

        return $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }


    /**
     * Обрабатывает доменное исключение: логирует и устанавливает flash-сообщение об ошибке.
     * В режиме YII_DEBUG показывает текст исключения, в продакшене — общую фразу.
     */
    protected function handleDomainException(Throwable $e, string $genericMessage = 'Ошибка'): void
    {
        Yii::$app->errorHandler->logException($e);
        $message = YII_DEBUG ? VarDumper::dumpAsString($e->getMessage()) : $genericMessage;
        Yii::$app->session->setFlash('error', $message);
    }

}
