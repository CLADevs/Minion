<?php

declare(strict_types=1);

namespace CLADevs\Minion\entities\types;

use CLADevs\Minion\entities\MinionEntity;
use CLADevs\Minion\inventories\MinerInventory;
use CLADevs\Minion\utils\Configuration;
use pocketmine\block\Block;
use pocketmine\block\Chest;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\math\Vector3;

class MinerMinion extends MinionEntity{

    const NAME = "miner";

    public function initNameTag(): void{
        $this->minionName = $this->player . "'s Miner";
        parent::initNameTag();
    }

    public function entityBaseTick(int $tickDiff = 1): bool{
        $update = parent::entityBaseTick($tickDiff);
        if($this->getLevel()->getServer()->getTick() % $this->getMineTime() == 0){
            //Checks if theres a chest behind him
            if($this->getLookingBehind() instanceof Chest){
                $b = $this->getLookingBehind();
                $this->namedtag->setString("xyz", $b->getX() . ":" . $b->getY() . ":" . $b->getZ());
            }
            //Update the coordinates
            if($this->namedtag->getString("xyz") !== "n"){
                if(isset($this->getCoord()[1])){
                    $block = $this->getLevel()->getBlock(new Vector3(intval($this->getCoord()[0]), intval($this->getCoord()[1]), intval($this->getCoord()[2])));
                    if(!$block instanceof Chest){
                        $this->namedtag->setString("xyz", "n");
                    }
                }
            }
            //Breaks
            if ($this->getLookingBlock()->getId() !== Block::AIR and $this->isChestLinked()){
                $this->breakBlock($this->getLookingBlock());
            }
        }
        return $update;
    }

    public function sendSpawnItems(): void{
        $this->getInventory()->setItemInHand(Item::get(Item::DIAMOND_PICKAXE));
        parent::sendSpawnItems();
    }

    public function getWindow(Position $position){
        return new MinerInventory($position, $this);
    }

    public function getMaxTime(): int{
        return (20 * Configuration::getMaxLevel()) + 20;
    }

    public function getMineTime(): int{
        return $this->getMaxTime() - (20 * $this->getLevelM());
    }

    public function getCost(): int{
        return Configuration::getLevelCost() * $this->getLevelM();
    }
}