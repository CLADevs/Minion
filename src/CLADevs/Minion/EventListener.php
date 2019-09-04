<?php

declare(strict_types=1);

namespace CLADevs\Minion;

use CLADevs\Minion\minion\HopperInventory;
use CLADevs\Minion\minion\Minion;
use pocketmine\block\Chest;
use pocketmine\entity\Entity;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\nbt\tag\StringTag;
use pocketmine\utils\TextFormat as C;

use onebone\economyapi\EconomyAPI;

class EventListener implements Listener{

    public $linkable = [];

    public function onInv(InventoryTransactionEvent $e): void{
        $tr = $e->getTransaction();
        foreach($tr->getActions() as $act){
            if($act instanceof SlotChangeAction){
                $inv = $act->getInventory();
                if($inv instanceof HopperInventory){
                    $player = $tr->getSource();
                    $entity = $inv->getEntity();
                    $e->setCancelled();
//                    if($act->getSourceItem()->getId() === Item::EMERALD){
//                        $time = $time = $entity->namedtag->getInt("Time");
//                        if($time <= 1){
//                            $tr->getSource()->sendMessage(C::RED . "You already maxed the mine speed!");
//                            return;
//                        }
//                        if(!$tr->getSource()->getInventory()->contains(Item::get(Item::DIAMOND, 0, $entity->getCost()))){
//                            $tr->getSource()->sendMessage(C::RED . "You don't have enough diamonds to upgrade..");
//                            return;
//                        }
//                        $time = $entity->namedtag->getInt("Time") - 1;
//                        $entity->namedtag->setInt("Time", $time);
//                        $tr->getSource()->sendMessage(C::YELLOW . "Upgraded the mine speed to " . $time . "s!");
//                        $inv->setItem(2, $inv->getSpeedUp());
//                        $tr->getSource()->getInventory()->removeItem(Item::get(Item::DIAMOND, 0, $entity->getCost()));
//                    }
                    switch($act->getSourceItem()->getId()){
                        case Item::REDSTONE_DUST:
                            $entity->flagForDespawn();
                            $player->getInventory()->addItem(Main::get()->getItem($player, $entity->getLevelM()));
                            break;
                        case Item::CHEST:
                            if($entity->getLookingBehind() instanceof Chest){
                                $player->sendMessage(C::RED . "Please remove the chest behind the miner, to set new linkable chest.");
                                return;
                            }
                            if(isset($this->linkable[$player->getName()])){
                                $player->sendMessage(C::RED . "You are already on linking mode.");
                                return;
                            }
                            $this->linkable[$player->getName()] = $entity;
                            $player->sendMessage(C::LIGHT_PURPLE . "Please tap the chest that you want to link with.");
                            break;
                        case Item::EMERALD:
                            if($entity->getLevelM() >= Main::get()->getConfig()->getNested("level.max")){
                                $player->sendMessage(C::RED . "You have maxed the level!");
                                return;
                            }
                            if(EconomyAPI::getInstance()->myMoney($player) < $entity->getCost()){
                                $player->sendMessage(C::RED . "You don't have enough money.");
                                return;
                            }
                            $entity->namedtag->setInt("level", $entity->namedtag->getInt("level") + 1);
                            $player->sendMessage(C::GREEN . "Leveled up to " . $entity->getLevelM());
                            EconomyAPI::getInstance()->reduceMoney($player, $entity->getCost());
                            break;
                    }
                    $inv->onClose($player);
                }
            }
        }
    }

    public function onInteract(PlayerInteractEvent $e): void{
        $player = $e->getPlayer();
        $item = $e->getItem();
        $dnbt = $item->getNamedTag();

        if(isset($this->linkable[$player->getName()])){
            if(!$e->getBlock() instanceof Chest){
                $player->sendMessage(C::RED . "Please tap a chest not a " . $e->getBlock()->getName());
                return;
            }
            $entity = $this->linkable[$player->getName()];
            $block = $e->getBlock();
            if($entity instanceof Minion) $entity->namedtag->setString("xyz", $block->getX() . ":" . $block->getY() . ":" . $block->getZ());
            unset($this->linkable[$player->getName()]);
            $player->sendMessage(C::GREEN . "You have linked a chest!");
            return;
        }

        if($dnbt->hasTag("summon", StringTag::class)){
            $nbt = Entity::createBaseNBT($player, null, (90 + ($player->getDirection() * 90)) % 360);
            $nbt->setInt("level", $dnbt->getInt("level"));
            $nbt->setString("player", $player->getName());
            $nbt->setString("xyz", $dnbt->getString("xyz"));
            $nbt->setTag($player->namedtag->getTag("Skin"));
            $entity = new Minion($player->getLevel(), $nbt);
            $entity->spawnToAll();
            $item->setCount($item->getCount() - 1);
            $player->getInventory()->setItemInHand($item);
        }
    }
}