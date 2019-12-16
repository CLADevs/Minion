<?php

declare(strict_types=1);

namespace CLADevs\Minion\utils;

use pocketmine\math\Vector3;

class MinionAPI{

    /** @var string */
    protected $name = "";
    /** @var null|string */
    protected $owner = null;
    /** @var Vector3 */
    protected $chestLocation;

    public function __construct(string $name, string $owner, Vector3 $chestLocation){
        $this->name = $name;
        $this->owner = $owner;
        $this->chestLocation = $chestLocation;
    }

    public function getName(): string{
        return $this->name;
    }

    public function getOwner(): ?string{
        return $this->owner;
    }

    public function getChestLocation(): Vector3{
        return $this->chestLocation;
    }
}
