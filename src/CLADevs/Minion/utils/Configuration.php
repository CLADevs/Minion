<?php

declare(strict_types=1);

namespace CLADevs\Minion\utils;

use CLADevs\Minion\Main;
use pocketmine\utils\Config;

class Configuration{

    public static function getConfig(): Config{
        return Main::get()->getConfig();
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

    /**
     * @return float|int
     */
    public static function getSize(){
        return self::getConfig()->get("size", 0.8);
    }

    public static function isNormalPickaxe(): bool{
        return self::getConfig()->getNested("blocks.normal", true);
    }

    public static function getUnbreakableBlocks(): array{
        return self::getConfig()->getNested("blocks.cannot", []);
    }

    public static function getLevelCost(): int{
        return self::getConfig()->getNested("level.cost", 10);
    }

    public static function getMaxLevel(): int{
        return self::getConfig()->getNested("level.max", 3);
    }

    public static function allowSmeltOre(): bool{
        return self::getConfig()->get("smelt", true);
    }
}
