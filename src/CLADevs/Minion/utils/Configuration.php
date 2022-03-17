<?php

declare(strict_types=1);

namespace CLADevs\Minion\utils;

use CLADevs\Minion\Loader;
use pocketmine\utils\Config;

class Configuration{

    public static function getConfig(): Config{
        return Loader::getInstance()->getConfig();
    }

    public static function allowRandomNames(): bool{
        return self::getConfig()->get("random-names", true);
    }

    public static function getNames(): array{
        return self::getConfig()->get("names", []);
    }

    public static function getNotAllowWorlds(): array{
        return self::getConfig()->get("worlds", []);
    }

    public static function getSize(): float|int{
        return self::getConfig()->get("size", 0.8);
    }

    public static function isNormalPickaxe(): bool{
        return self::getConfig()->getNested("blocks.normal", true);
    }

    public static function getUnbreakableBlocks(): array{
        return self::getConfig()->getNested("blocks.cannot", []);
    }

    public static function getLevelCost(int $level = 1): int{
        return self::getConfig()->getNested("levels.$level", 10);
    }

    public static function getMaxLevel(): int{
        return self::getConfig()->getNested("level.max", 3);
    }

    public static function getSmeltLevel(): int{
        return self::getConfig()->getNested("level.auto-smelt", 2);
    }
}
