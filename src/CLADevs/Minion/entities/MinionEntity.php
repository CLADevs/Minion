<?php

declare(strict_types=1);

namespace CLADevs\Minion\entities;

use CLADevs\Minion\inventories\HopperInventory;
use CLADevs\Minion\Main;
use CLADevs\Minion\utils\Configuration;
use pocketmine\block\Block;
use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\tile\Chest;
use pocketmine\utils\TextFormat;

class MinionEntity extends Human{

    const NAME = "Unknown";

    /** @var null|string */
    protected $player, $minionName = null;
    /** @var bool */
    protected $openGUI = true;

    public function initEntity(): void{
        parent::initEntity();
        $this->player = $this->namedtag->getString("player");
        $this->initNameTag();
        $this->setHealth(1);
        $this->setMaxHealth(1);
        $this->setScale((float)Configuration::getSize());
        $this->sendSpawnItems();
    }

    public function initNameTag(): void{
        if($this->minionName === null){
            $this->minionName = $this->player . "'s Minion";
        }
        $this->setNameTag($this->minionName);
        $this->setNameTagAlwaysVisible();
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
                if($this->openGUI){
                    if($damager->getName() !== $this->player){
                        if(!$damager->hasPermission("minion.open.others")){
                            $damager->sendMessage(TextFormat::RED . "This is not your minion.");
                            return;
                        }
                    }
                    $pos = new Position(intval($damager->getX()), intval($damager->getY()) + 2, intval($damager->getZ()), $damager->getLevel());
                    $damager->addWindow($this->getWindow($pos));
                }
            }
        }
    }

    public function getWindow(Position $position){
       return new HopperInventory($position, $this);
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
        return $update;
    }

    public function sendSpawnItems(): void{
        $this->getArmorInventory()->setHelmet( Item::get(Item::SKULL, 3));
        $this->getArmorInventory()->setChestplate(Item::get(Item::LEATHER_CHESTPLATE));
        $this->getArmorInventory()->setLeggings(Item::get(Item::LEATHER_LEGGINGS));
        $this->getArmorInventory()->setBoots(Item::get(Item::LEATHER_BOOTS));
    }

    public function breakBlock(Block $block): bool{
        $success = false;
        $b = $this->getLevel()->getBlock(new Vector3(intval($this->getCoord()[0]), intval($this->getCoord()[1]), intval($this->getCoord()[2])));
        $tile = $this->getLevel()->getTile($b);
        if($tile instanceof Chest){
            $inv = $tile->getInventory();
            if(Configuration::isNormalPickaxe()){
                foreach($block->getDropsForCompatibleTool($this->getInventory()->getItemInHand()) as $drop){
                    $inv->addItem($drop);
                }
                $success = true;
            }elseif(!in_array($block->getId(), Configuration::getUnbreakableBlocks())){
                $inv->addItem(Item::get($block->getId(), $block->getDamage()));
                $success = true;
            }
        }
        if($success){
            $pk = new AnimatePacket();
            $pk->entityRuntimeId = $this->id;
            $pk->action = AnimatePacket::ACTION_SWING_ARM;
            foreach (Server::getInstance()->getOnlinePlayers() as $p) $p->dataPacket($pk);
            $this->getLevel()->setBlock($block, Block::get(Block::AIR), true, true);
        }
        return $success;
    }

    public function getLevelM(): int{
        return $this->namedtag->getInt("level");
    }

    public function getLookingBlock(): Block{
        return $this->getLevel()->getBlock($this->add($this->getDirectionVector()->multiply(1)));
    }

    public function getLookingBehind(): Block{
        return $this->getLevel()->getBlock($this->add($this->getDirectionVector()->multiply(-1)));
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
