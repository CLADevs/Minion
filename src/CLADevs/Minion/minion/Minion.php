<?php

declare(strict_types=1);

namespace CLADevs\Minion\minion;

use CLADevs\Minion\Main;
use pocketmine\block\Block;
use pocketmine\block\Chest;
use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;

class Minion extends Human{

    protected $player;

    public function initEntity(): void{
        parent::initEntity();
        $this->player = $this->namedtag->getString("player");
        $this->setHealth(1);
        $this->setMaxHealth(1);
        $this->setNameTagAlwaysVisible();
        $this->setNameTag($this->player . "'s Miner");
        $this->setScale((float)Main::get()->getConfig()->get("size"));
        $this->sendSpawnItems();
    }

    public function attack(EntityDamageEvent $source): void{
        $source->setCancelled();
        if($source instanceof EntityDamageByEntityEvent){
            $damager = $source->getDamager();
            if($damager instanceof Player){
                $pos = new Position(intval($damager->getX()), intval($damager->getY()) + 2, intval($damager->getZ()), $damager->getLevel());
                $damager->addWindow(new HopperInventory($pos, $this));
            }
        }
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
        $block = $this->getLevel()->getBlock(new Vector3(intval($this->getCoord()[0]), intval($this->getCoord()[1]), intval($this->getCoord()[2])));
        $tile = $this->getLevel()->getTile($block);

        if($tile instanceof \pocketmine\tile\Chest){
            $inventory = $tile->getInventory();

            if(Main::get()->getConfig()->getNested("blocks.normal")){
                foreach($block->getDropsForCompatibleTool(Item::get(Item::DIAMOND_PICKAXE)) as $drop){
                    if($inventory->canAddItem($drop)) return true;
                }
            }elseif(!in_array($block->getId(), Main::get()->getConfig()->getNested("blocks.cannot"))){
                if($inventory->canAddItem(Item::get($block->getId(), $block->getDamage()))) return true;
            }
            return false;
        }
        return false;
    }

    public function breakBlock(Block $block): void{
        $b = $this->getLevel()->getBlock(new Vector3(intval($this->getCoord()[0]), intval($this->getCoord()[1]), intval($this->getCoord()[2])));
        $tile = $this->getLevel()->getTile($b);
        if($tile instanceof \pocketmine\tile\Chest){
            $inv = $tile->getInventory();
            if(Main::get()->getConfig()->getNested("blocks.normal")){
                foreach($block->getDropsForCompatibleTool(Item::get(Item::DIAMOND_PICKAXE)) as $drop){
                    $inv->addItem($drop);
                }
            }else{
                if(in_array($block->getId(), Main::get()->getConfig()->getNested("blocks.cannot"))) return;
                $inv->addItem(Item::get($block->getId(), $block->getDamage()));
            }
        }
        $this->getLevel()->setBlock($block, Block::get(Block::AIR), true, true);
    }

    public function getMaxTime(): int{
        return (20 * Main::get()->getConfig()->getNested("level.max")) + 20;
    }

    public function getMineTime(): int{
        return $this->getMaxTime() - (20 * $this->namedtag->getInt("level"));
    }

    public function getCost(): int{
        return Main::get()->getConfig()->getNested("level.cost") * $this->getLevelM();
    }

    public function getLevelM(): int{
        return $this->namedtag->getInt("level");
    }

    public function isChestLinked(): bool{
        return $this->namedtag->getString("xyz") === "n" ? false : true;
    }

    public function getChestCoordinates(): string{
        if(!isset($this->getCoord()[1])){
            return C::RED . "Not found";
        }
        $coord = C::YELLOW . "X: " . C::WHITE . $this->getCoord()[0] . " ";
        $coord .= C::YELLOW . "Y: " . C::WHITE . $this->getCoord()[1] . " ";
        $coord .= C::YELLOW . "Z: " . C::WHITE . $this->getCoord()[2] . " ";
        return $coord;
    }

    public function getCoord(): array{
        $coord = explode(":", $this->namedtag->getString("xyz"));
        return $coord;
    }
}