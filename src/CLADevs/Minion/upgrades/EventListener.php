<?php

namespace CLADevs\Minion\upgrades;

use CLADevs\Minion\Minion;
use pocketmine\entity\Entity;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\nbt\tag\StringTag;
use pocketmine\utils\TextFormat as C;

class EventListener implements Listener{

    public function onInv(InventoryTransactionEvent $e): void{
        $tr = $e->getTransaction();
        foreach($tr->getActions() as $act){
            if($act instanceof SlotChangeAction){
                $inv = $act->getInventory();
                if($inv instanceof HopperInventory){
                    $entity = $inv->getEntity();
                    $e->setCancelled();
                    if($act->getSourceItem()->getId() === Item::EMERALD){
                        $time = $time = $entity->namedtag->getInt("Time");
                        if($time <= 1){
                            $tr->getSource()->sendMessage(C::RED . "You already maxed the mine speed!");
                            return;
                        }
                        if(!$tr->getSource()->getInventory()->contains(Item::get(Item::DIAMOND, 0, $entity->getCost()))){
                            $tr->getSource()->sendMessage(C::RED . "You don't have enough diamonds to upgrade..");
                            return;
                        }
                        $time = $entity->namedtag->getInt("Time") - 1;
                        $entity->namedtag->setInt("Time", $time);
                        $tr->getSource()->sendMessage(C::YELLOW . "Upgraded the mine speed to " . $time . "s!");
                        $inv->setItem(2, $inv->getSpeedUp());
                        $tr->getSource()->getInventory()->removeItem(Item::get(Item::DIAMOND, 0, $entity->getCost()));
                    }
                }
            }
        }
    }

    public function onInteract(PlayerInteractEvent $e): void{
        $player = $e->getPlayer();
        $item = $e->getItem();

        if($item->getNamedTag()->hasTag("summon", StringTag::class)){
            $nbt = Entity::createBaseNBT($player, null, (90 + ($player->getDirection() * 90)) % 360);
            $nbt->setInt("Time", 3);
            $nbt->setTag($player->namedtag->getTag("Skin"));
            $entity = new Minion($player->getLevel(), $nbt);
            $entity->spawnToAll();
            $player->getInventory()->removeItem(Item::get($item->getId(), $item->getDamage(), 1));
        }
    }
}
