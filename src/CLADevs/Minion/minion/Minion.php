<?php

declare(strict_types=1);

namespace CLADevs\Minion\minion;

use CLADevs\Minion\Main;
use CLADevs\Minion\utils\Configuration;
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
use pocketmine\utils\TextFormat;

class Minion extends Human{

    /** @var string */
    protected $player, $minionName;

    public function initEntity(): void{
        parent::initEntity();
        $this->player = $this->namedtag->getString("player");
        $this->minionName = $this->player . "'s Miner";
        $this->setHealth(1);
        $this->setMaxHealth(1);
        $this->setNameTagAlwaysVisible();
        $this->setNameTag($this->minionName);
        $this->setScale((float)Configuration::getSize());
        $this->sendSpawnItems();
    }

    public function attack(EntityDamageEvent $source): void{
        $source->setCancelled();
        if($source instanceof EntityDamageByEntityEvent){
            $damager = $source->getDamager();
            if($damager instanceof Player){
                if(Main::get()->isInRemove($damager)){
                    $this->flagForDespawn();
                    $damager->sendMessage(TextFormat::GREEN . "Removed " . $this->player . " minion.");
                    return;
                }
                if($damager->getName() !== $this->player){
                    if(!$damager->hasPermission("minion.open.others")){
                        $damager->sendMessage(TextFormat::RED . "This is not your minion.");
                        return;
                    }
                }
                $pos = new Position(intval($damager->getX()), intval($damager->getY()) + 2, intval($damager->getZ()), $damager->getLevel());
                $damager->addWindow(new HopperInventory($pos, $this));
            }
        }
    }

    public function entityBaseTick(int $tickDiff = 1): bool{
        $update = parent::entityBaseTick($tickDiff);
        //random names
        if($this->getLevel()->getServer()->getTick() % 30 == 0){
            if(Configuration::allowRandomNames()){
                $names = Configuration::getNames();
                $this->setNameTag($this->minionName . TextFormat::EOL . $names[array_rand($names)]);
            }
        }
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
        return $this->getLevel()->getBlock($this->add($this->getDirectionVector()->multiply(1)));
    }

    public function getLookingBehind(): Block{
        return $this->getLevel()->getBlock($this->add($this->getDirectionVector()->multiply(-1)));
    }

    public function checkEverythingElse(): bool{
        $block = $this->getLevel()->getBlock(new Vector3(intval($this->getCoord()[0]), intval($this->getCoord()[1]), intval($this->getCoord()[2])));
        $tile = $this->getLevel()->getTile($block);

        if($tile instanceof \pocketmine\tile\Chest){
            $inventory = $tile->getInventory();

            if(Configuration::isNormalPickaxe()){
                foreach($block->getDropsForCompatibleTool(Item::get(Item::DIAMOND_PICKAXE)) as $drop){
                    if($inventory->canAddItem($drop)) return true;
                }
            }elseif(!in_array($block->getId(), Configuration::getUnbreakableBlocks())){
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
            if(Configuration::isNormalPickaxe()){
                foreach($block->getDropsForCompatibleTool(Item::get(Item::DIAMOND_PICKAXE)) as $drop){
                    $inv->addItem($drop);
                }
            }else{
                if(in_array($block->getId(), Configuration::getUnbreakableBlocks())) return;
                $inv->addItem(Item::get($block->getId(), $block->getDamage()));
            }
        }
        $this->getLevel()->setBlock($block, Block::get(Block::AIR), true, true);
    }

    public function getMaxTime(): int{
        return (20 * Configuration::getMaxLevel()) + 20;
    }

    public function getMineTime(): int{
        return $this->getMaxTime() - (20 * $this->namedtag->getInt("level"));
    }

    public function getCost(): int{
        return Configuration::getLevelCost() * $this->getLevelM();
    }

    public function getLevelM(): int{
        return $this->namedtag->getInt("level");
    }

    public function isChestLinked(): bool{
        return $this->namedtag->getString("xyz") === "n" ? false : true;
    }

    public function getChestCoordinates(): string{
        if(!isset($this->getCoord()[1])){
            return TextFormat::RED . "Not found";
        }
        $coord = TextFormat::YELLOW . "X: " . TextFormat::WHITE . $this->getCoord()[0] . " ";
        $coord .= TextFormat::YELLOW . "Y: " . TextFormat::WHITE . $this->getCoord()[1] . " ";
        $coord .= TextFormat::YELLOW . "Z: " . TextFormat::WHITE . $this->getCoord()[2] . " ";
        return $coord;
    }

    public function getCoord(): array{
        $coord = explode(":", $this->namedtag->getString("xyz"));
        return $coord;
    }
}
