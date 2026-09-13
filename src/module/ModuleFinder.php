<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Kernel\module;

use Yii;
use yii\base\Module;

/**
 * Поиск модулей приложения по контракту без инстанцирования непричастных.
 *
 * Реестры вкладов (теги, поиск, сайтмап, меню, алиасы, сниппеты) не знают имён модулей-провайдеров
 * и находят их перебором. Наивный перебор `getModule($id)` + `instanceof` создаёт КАЖДЫЙ
 * зарегистрированный модуль (его `init()`, `container.php`, слушатели) — на фронте это все
 * модули админки ради одной страницы тегов.
 *
 * Контракт объявлен на классе модуля, а класс известен из определения ещё до создания объекта:
 * `getModules(false)` отдаёт для незагруженных модулей строку класса либо массив с `class`/`__class`.
 * Поэтому сперва проверяем контракт по классу и только реализующим зовём `getModule($id)`.
 *
 * Крайние случаи, когда класс из определения не вывести (callable-определение) или он не
 * автозагружается, отдаём на откуп `getModule()`: модуль создаётся и проверяется `instanceof`,
 * как раньше, — безопасное надмножество. Не покрыт один сценарий: определение называет класс X,
 * а DI-контейнер подменяет его на Y (`Yii::$container->set(X, Y)`), и контракт реализует только Y;
 * для модулей такая подмена не используется.
 */
final class ModuleFinder
{
    /**
     * Модули родителя, реализующие контракт. Ключ — id модуля, порядок — порядок регистрации.
     *
     * @template T of object
     * @param class-string<T> $contract интерфейс (или класс) вклада
     * @param Module|null $parent где искать; по умолчанию — приложение
     * @return array<string, T&Module>
     */
    public static function implementing(string $contract, ?Module $parent = null): array
    {
        $parent ??= Yii::$app;
        $found = [];

        foreach ($parent->getModules(false) as $id => $definition) {
            if (!self::mayImplement($definition, $contract)) {
                continue;
            }

            $module = $parent->getModule((string)$id);
            if ($module instanceof $contract) {
                $found[(string)$id] = $module;
            }
        }

        return $found;
    }

    /**
     * Может ли модуль с таким определением реализовывать контракт.
     *
     * `false` — только когда класс определения известен и контракт точно не реализует;
     * во всех сомнительных случаях `true`, чтобы решал `instanceof` на живом объекте.
     */
    private static function mayImplement(mixed $definition, string $contract): bool
    {
        if ($definition instanceof Module) {
            return $definition instanceof $contract;
        }

        $class = self::classOf($definition);
        if ($class === null || !class_exists($class)) {
            return true;
        }

        return is_a($class, $contract, true);
    }

    /**
     * Имя класса из определения модуля (строка или массив с `class`/`__class`); `null`, если не вывести.
     */
    private static function classOf(mixed $definition): ?string
    {
        if (is_string($definition)) {
            return $definition;
        }

        if (is_array($definition)) {
            $class = $definition['class'] ?? $definition['__class'] ?? null;
            return is_string($class) ? $class : null;
        }

        return null;
    }
}
