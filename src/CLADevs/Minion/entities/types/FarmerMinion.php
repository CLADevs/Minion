<?php

declare(strict_types=1);

namespace CLADevs\Minion\entities\types;

use CLADevs\Minion\entities\MinionEntity;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\block\Block;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\Chest;
use pocketmine\block\Crops;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\Server;

class FarmerMinion extends MinionEntity{

    public static function getMinionType(): string{
        return "Farmer";
    }

    public function initNameTag(): void{
        $this->customName = $this->player . "'s Farmer";
        parent::initNameTag();
    }

    public function entityBaseTick(int $tickDiff = 1): bool{
        $update = parent::entityBaseTick($tickDiff);

        if(Server::getInstance()->getTick() % 30 == 0){
            //Checks if theres a chest behind him
            if(($block = $this->getLookingBehind()) instanceof Chest){
                $this->chestPosition = $block->getPosition();
            }
            //Update the coordinates
            if($this->chestPosition !== null){
                $block = $this->getWorld()->getBlock($this->chestPosition);

                if(!$block instanceof Chest){
                    $this->chestPosition = null;
                }
            }
            if($this->isChestLinked()){
                $i = 0;
                for($x = -1; $x <= 1; $x++){
                    for($z = -1; $z <= 1; $z++){
                        $xz = clone $this->getPosition()->add($x, 0, $z);
                        $pos = clone $xz->subtract(0, 1, 0);

                        if($this->getWorld()->getBlock($pos)->getId() === BlockLegacyIds::FARMLAND && $i !== 4){ //4 is middle block
                            $b = $this->getWorld()->getBlock($xz);

                            if($b instanceof Crops && $b->getAge() >= 7){
                                $this->lookAt($xz);
                                $this->breakBlock($b);
                                return $update;
                            }
                        }
                        $i++;
                    }
                }
            }
        }
        return $update;
    }

    public function breakBlock(Block $block): bool{
        $parent = parent::breakBlock($block);

        if($this->chestPosition === null){
            return false;
        }
        if($parent){
            $tile = $this->getWorld()->getTile($this->chestPosition);

            if($tile instanceof \pocketmine\block\tile\Chest){
                $inv = $tile->getInventory();

                foreach($inv->getContents() as $slot => $item){
                    if($item->getBlock()->getId() === $block->getId()){
                        $this->getInventory()->setItemInHand($item);
                        $this->getWorld()->setBlock($block->getPosition(), $item->getBlock(), true);
                        $this->sendSpawnItems();
                        $inv->setItem($slot, $item->setCount($item->getCount() - 1));
                        break;
                    }
                }
            }
        }
        return $parent;
    }

    public function sendSpawnItems(): void{
        $this->getInventory()->setItemInHand(VanillaItems::DIAMOND_HOE());
        parent::sendSpawnItems();
    }

    protected function handleInventory(Player $attacker): void{
        $this->getMainInventory(function (InvMenuTransaction $tr): InvMenuTransactionResult{
            InvMenuHandler::getPlayerManager()->get($tr->getPlayer())->removeCurrentMenu();
            return $tr->discard();
        })->send($attacker);
    }
}