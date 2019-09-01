<?php

namespace CLADevs\Minion;

use CLADevs\Minion\upgrades\HopperInventory;
use pocketmine\block\Block;
use pocketmine\block\Chest;
use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\AnimatePacket;
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
        $this->sendSpawnItems();
    }

    public function attack(EntityDamageEvent $source): void{
        $source->setCancelled();
        if($source instanceof EntityDamageByEntityEvent){
            $damager = $source->getDamager();

            if($damager instanceof Player){
                if($damager->getInventory()->getItemInHand()->getId() !== Item::AIR){
                    $this->flagForDespawn();
                    return;
                }
                $pos = new Position(intval($damager->getX()), intval($damager->getY()) + 2, intval($damager->getZ()), $damager->getLevel());
                $damager->addWindow(new HopperInventory($pos, $this));
            }
        }
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

    public function sendSpawnItems(): void{
        $this->getInventory()->setItemInHand(Item::get(Item::DIAMOND_PICKAXE));
        $this->getArmorInventory()->setHelmet( Item::get(Item::SKULL, 3));
        $this->getArmorInventory()->setChestplate(Item::get(Item::LEATHER_CHESTPLATE));
        $this->getArmorInventory()->setLeggings(Item::get(Item::LEATHER_LEGGINGS));
        $this->getArmorInventory()->setBoots(Item::get(Item::LEATHER_BOOTS));
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
            $inv->addItem(Item::get($block->getId(), $block->getDamage()));
        }
        $this->getLevel()->setBlock($block, Block::get(Block::AIR), true, true);
    }

    public function getMineTime(): int{
        return 20 * $this->namedtag->getInt("Time");
    }

    public function flagForDespawn(): void{
        parent::flagForDespawn();
        foreach($this->getDrops() as $drop){
            $this->getLevel()->dropItem($this->add(0.5, 0.5, 0.5), $drop);
        }
    }

    public function getCost(): int{
        return $this->getTime() + 2;
    }

    public function getTime(): int{
        return $this->namedtag->getInt("Time");
    }

    public function getDrops(): array{
        return [Main::get()->getItem()];
    }
}
