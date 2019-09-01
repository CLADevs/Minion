<?php

namespace CLADevs\Minion;

use pocketmine\block\Block;
use pocketmine\block\Chest;
use pocketmine\entity\Human;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\MobArmorEquipmentPacket;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\Player;
use pocketmine\Server;

class Minion extends Human{

    public function initEntity(): void{
        parent::initEntity();
        $this->setHealth(1);
        $this->setMaxHealth(1);
        $this->setNameTagAlwaysVisible();
        $this->setNameTag("Miner");
        $this->setScale(0.8);
    }

    public function entityBaseTick(int $tickDiff = 1): bool{
        $update = parent::entityBaseTick($tickDiff);

        if($this->getLevel()->getServer()->getTick() % $this->getMineTime() == 0){
            if ($this->getLookingBlock()->getId() !== Block::AIR and $this->getLookingBehind() instanceof Chest){
                if($this->checkEverythingElse()){
                    $pk = new AnimatePacket();
                    $pk->entityRuntimeId = $this->id;
                    $pk->action = AnimatePacket::ACTION_SWING_ARM;
                    foreach (Server::getInstance()->getOnlinePlayers() as $p) $p->dataPacket($pk);
                    $this->breakBlock($this->getLookingBlock());
                }
            }
        }
        return $update;
    }

    protected function sendSpawnPacket(Player $player): void{
        parent::sendSpawnPacket($player);
        $this->setItemInHand($player, Item::DIAMOND_PICKAXE);
        $this->setArmor($player);
    }

    public function setItemInHand(Player $player, int $id): void{
        //too lazy to change from armor stand to human
        $pk = new MobEquipmentPacket();
        $pk->entityRuntimeId = $this->id;
        $pk->inventorySlot = 0;
        $pk->hotbarSlot = 0;
        $pk->item = Item::get($id);
        $player->dataPacket($pk);
    }

    public function setArmor(Player $player): void{
        //too lazy to change from armor stand to human
        $pk = new MobArmorEquipmentPacket();
        $pk->entityRuntimeId = $this->id;
        $pk->head = Item::get(Item::SKULL, 3);
        $pk->chest = Item::get(Item::LEATHER_CHESTPLATE);
        $pk->legs = Item::get(Item::LEATHER_LEGGINGS);
        $pk->feet = Item::get(Item::LEATHER_BOOTS);
        $player->dataPacket($pk);
    }

    public function getLookingBlock(): Block{
        $block = Block::get(Block::AIR);
        switch($this->getDirection()){
            case 0:
                $block = $this->getLevel()->getBlock($this->add(1, 0, 0));
                break;
            case 1:
                $block = $this->getLevel()->getBlock($this->add(0, 0, 1));
                break;
            case 2:
                $block = $this->getLevel()->getBlock($this->add(-1, 0, 0));
                break;
            case 3:
                $block = $this->getLevel()->getBlock($this->add(0, 0, -1));
                break;
        }
        return $block;
    }

    public function getLookingBehind(): Block{
        $block = Block::get(Block::AIR);
        switch($this->getDirection()){
            case 0:
                $block = $this->getLevel()->getBlock($this->add(-1, 0, 0));
                break;
            case 1:
                $block = $this->getLevel()->getBlock($this->add(0, 0, -1));
                break;
            case 2:
                $block = $this->getLevel()->getBlock($this->add(1, 0, 0));
                break;
            case 3:
                $block = $this->getLevel()->getBlock($this->add(0, 0, 1));
                break;
        }
        return $block;
    }

    public function checkEverythingElse(): bool{
        $block = $this->getLookingBlock();
        $tile = $this->getLevel()->getTile($this->getLookingBehind());

        if($tile instanceof \pocketmine\tile\Chest){
            $inventory = $tile->getInventory();
            if($inventory->canAddItem(Item::get($block->getId(), $block->getDamage()))) return true;
        }
        return false;
    }

    public function breakBlock(Block $block): void{
        $tile = $this->getLevel()->getTile($this->getLookingBehind());
        if($tile instanceof \pocketmine\tile\Chest){
            $inv = $tile->getInventory();
            foreach($block->getDrops(Item::get(Item::AIR)) as $drop){
                $inv->addItem($drop);
            }
        }
        $this->getLevel()->setBlock($block, Block::get(Block::AIR), true, true);
    }

    public function getMineTime(): int{
        return 20 * $this->namedtag->getInt("Time");
    }

    public function getDrops(): array{
        return [Main::get()->getItem()];
    }
}
