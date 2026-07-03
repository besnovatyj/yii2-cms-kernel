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

        if ($referer !== null && $this->isLocalReferer($referer)) {
            return $this->redirect($referer);
        }

        return $this->redirect($fallback);
    }

    private function isLocalReferer(string $url): bool
    {
        $host = Yii::$app->getRequest()->getHostInfo(); // Например, https://my-domain.com
        // Завершающий слэш в префиксе отсекает подделки вида https://my-domain.com.evil.com/
        return $host !== null && (str_starts_with($url, $host . '/') || $url === $host);
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
