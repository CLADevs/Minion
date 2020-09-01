<?php

declare(strict_types=1);

namespace CLADevs\Minion\minion;

use CLADevs\Minion\EventListener;
use CLADevs\Minion\Main;
use CLADevs\Minion\utils\Configuration;
use onebone\economyapi\EconomyAPI;
use pocketmine\block\Block;
use pocketmine\block\Chest;
use pocketmine\inventory\CustomInventory;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\nbt\NetworkLittleEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\types\WindowTypes;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\utils\TextFormat as C;

class HopperInventory extends CustomInventory{

    protected $holder;
    protected $entity;

    public function __construct(Position $position, Minion $entity){
        parent::__construct($position);
        $this->entity = $entity;
        $this->setItem(0, $this->getDestoryItem());
        $this->setItem(2, $this->getChestItem());
        $this->setItem(4, $this->getLevelItem());
    }

    public function getName(): string{
        return "Hopper";
    }

    public function getDefaultSize(): int{
        return 5;
    }

    public function getNetworkType(): int{
        return WindowTypes::HOPPER;
    }

    public function onOpen(Player $who): void{
        $block = Block::get(Block::HOPPER_BLOCK);
        $block->x = $this->getHolder()->getX();
        $block->y = $this->getHolder()->getY();
        $block->z = $this->getHolder()->getZ();
        $block->level = $this->getHolder()->getLevel();
        $who->getLevel()->sendBlocks([$who], [$block]);
        $w = new NetworkLittleEndianNBTStream;
        $nbt = new CompoundTag("", []);
        $nbt->setString("id", "Hopper");
        $nbt->setString("CustomName", C::GOLD . "Settings");
        $pk = new BlockActorDataPacket();
        $pk->x = $this->getHolder()->getX();
        $pk->y = $this->getHolder()->getY();
        $pk->z = $this->getHolder()->getZ();
        $pk->namedtag = $w->write($nbt);
        $who->dataPacket($pk);
        parent::onOpen($who);
    }

    public function onClose(Player $who): void{
        $block = Block::get(Block::AIR);
        $block->x = $this->getHolder()->getX();
        $block->y = $this->getHolder()->getY();
        $block->z = $this->getHolder()->getZ();
        $block->level = $this->getHolder()->getLevel();
        $who->getLevel()->sendBlocks([$who], [$block]);
        parent::onClose($who);
    }

    public function getHolder(): Position{
        return $this->holder;
    }

    public function getEntity(): Minion{
        return $this->entity;
    }

    public function getDestoryItem(): Item{
        $item = Item::get(Item::REDSTONE_DUST);
        $item->setCustomName(C::RED . "Destorys the miner");
        return $item;
    }

    public function getChestItem(): Item{
        $islinked = $this->entity->isChestLinked() ? "Yes" : "Nope";
        $item = Item::get(Item::CHEST);
        $item->setCustomName(C::DARK_GREEN . "Link a chest");
        $item->setLore([" ",  C::YELLOW . "Linked: " . C::WHITE . $islinked, C::YELLOW . "Coordinates: " . C::WHITE . $this->entity->getChestCoordinates()]);
        return $item;
    }

    public function getLevelItem(): Item{
        $item = Item::get(Item::EMERALD);
        $item->setCustomName(C::LIGHT_PURPLE . "Level: " . C::YELLOW . $this->entity->getLevelM());
        $item->setLore([C::LIGHT_PURPLE . "Cost: " . C::YELLOW . "$" . $this->entity->getCost()]);
        return $item;
    }

    public function onListener(Player $player, Item $sourceItem, EventListener $listener): void{
        $entity = $this->getEntity();
        switch($sourceItem->getId()){
            case Item::REDSTONE_DUST:
                $listener->removeLinkable($player);
                $entity->flagForDespawn();
                $player->getInventory()->addItem(Main::asItem($player, $entity->getLevelM()));
                break;
            case Item::CHEST:
                if($entity->getLookingBehind() instanceof Chest){
                    $player->sendMessage(TextFormat::RED . "Please remove the chest behind the miner, to set new linkable chest.");
                    return;
                }
                if(isset($this->linkable[$player->getName()])){
                    $player->sendMessage(TextFormat::RED . "You are already on linking mode.");
                    return;
                }
                $listener->addLinkable($player, $entity);
                $player->sendMessage(TextFormat::LIGHT_PURPLE . "Please tap the chest that you want to link with.");
                break;
            case Item::EMERALD:
                if($entity->getLevelM() >= Configuration::getMaxLevel()){
                    $player->sendMessage(TextFormat::RED . "You have maxed the level!");
                    return;
                }
                if(EconomyAPI::getInstance()->myMoney($player) < $entity->getCost()){
                    $player->sendMessage(TextFormat::RED . "You don't have enough money.");
                    return;
                }
                $entity->namedtag->setInt("level", $entity->namedtag->getInt("level") + 1);
                $player->sendMessage(TextFormat::GREEN . "Leveled up to " . $entity->getLevelM());
                EconomyAPI::getInstance()->reduceMoney($player, $entity->getCost());
                break;
        }
        $this->onClose($player);
    }
}