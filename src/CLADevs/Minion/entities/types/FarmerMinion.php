<?php

declare(strict_types=1);

namespace CLADevs\Minion\entities\types;

use CLADevs\Minion\entities\MinionEntity;
use pocketmine\block\BlockIds;
use pocketmine\block\Chest;
use pocketmine\block\Crops;
use pocketmine\item\Item;
use pocketmine\math\Vector3;

class FarmerMinion extends MinionEntity{

    const NAME = "farmer";

    public function initNameTag(): void{
        $this->minionName = $this->player . "'s Farmer";
        parent::initNameTag();
    }

    public function entityBaseTick(int $tickDiff = 1): bool{
        $update = parent::entityBaseTick($tickDiff);
        if($this->getLevel()->getServer()->getTick() % 30 == 0){
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
            if($this->isChestLinked()){
                $i = 0;
                for($x = -1; $x <= 1; $x++){
                    for($z = -1; $z <= 1; $z++){
                        $xz = $this->add($x, 0, $z);
                        $pos = $xz->subtract(0, 1, 0);
                        if($this->level->getBlock($pos)->getId() === BlockIds::FARMLAND && $i !== 4){ //4 is middle block
                            $b = $this->level->getBlock($xz);
                            if($this->level->getBlock($xz) instanceof Crops && $b->getDamage() >= 7){
                                $this->lookAt($xz);
                                $this->breakBlock($b);
                            }
                        }
                        $i++;
                    }
                }
            }
        }
        return $update;
    }

    public function sendSpawnItems(): void{
        $this->getInventory()->setItemInHand(Item::get(Item::DIAMOND_HOE));
        parent::sendSpawnItems();
    }
}