<?php

declare(strict_types=1);

namespace CLADevs\Minion\entities;

use CLADevs\Minion\Loader;
use CLADevs\Minion\MinionListener;
use CLADevs\Minion\utils\Configuration;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\block\Block;
use pocketmine\block\tile\Chest;
use pocketmine\block\utils\MobHeadType;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

abstract class MinionEntity extends Human{

    const TAG_PLAYER = "player";
    const TAG_LEVEL = "level";
    const TAG_XYZ = "xyz";

    protected ?string $customName = null;
    protected string $player;

    protected int $level = 1;

    protected ?Vector3 $chestPosition = null;

    protected bool $openGUI = true;

    abstract protected function handleInventory(Player $attacker): void;

    abstract public static function getMinionType(): string;

    protected function initEntity(CompoundTag $nbt): void{
        parent::initEntity($nbt);
        $this->player = $nbt->getString(self::TAG_PLAYER, "");
        $this->level = $nbt->getInt(self::TAG_LEVEL, 1);
        $xyz = explode(":", $nbt->getString(self::TAG_XYZ, ""));

        if(count($xyz) === 3 && is_numeric($xyz[0]) && is_numeric($xyz[1]) && is_numeric($xyz[2])){
            $this->chestPosition = new Vector3(floatval($xyz[0]), floatval($xyz[1]), floatval($xyz[2]));
        }
        $this->initNameTag();
        $this->setHealth(1);
        $this->setMaxHealth(1);
        $this->setScale((float)Configuration::getSize());
        $this->sendSpawnItems();
    }

    public function saveNBT(): CompoundTag{
        $nbt = parent::saveNBT();
        $nbt->setString(self::TAG_PLAYER, $this->player);
        $nbt->setString(self::TAG_XYZ, $this->chestPosition === null ? "" : ($this->chestPosition->x . ":" . $this->chestPosition->y . ":" . $this->chestPosition->z));
        $nbt->setInt(self::TAG_LEVEL, $this->level);
        return $nbt;
    }

    public function initNameTag(): void{
        if($this->customName === null){
            $this->customName = $this->player . "'s Minion";
        }
        $this->setNameTag($this->customName);
        $this->setNameTagAlwaysVisible();
    }

    public function attack(EntityDamageEvent $source): void{
        if($source instanceof EntityDamageByEntityEvent){
            $attacker = $source->getDamager();

            if($attacker instanceof Player){
                if(Loader::getInstance()->isInRemove($attacker)){
                    $this->flagForDespawn();
                    $attacker->sendMessage(TextFormat::GREEN . "Removed " . $this->player . " minion.");
                    return;
                }
                if($this->openGUI){
                    if($attacker->getName() !== $this->player){
                        if(!$attacker->hasPermission("minion.open.others")){
                            $attacker->sendMessage(TextFormat::RED . "This is not your minion.");
                            return;
                        }
                    }
                    $this->handleInventory($attacker);
                }
            }
        }
    }

    public function entityBaseTick(int $tickDiff = 1): bool{
        $update = parent::entityBaseTick($tickDiff);
        //random names
        if($this->getWorld()->getServer()->getTick() % 30 == 0){
            if(Configuration::allowRandomNames()){
                $names = Configuration::getNames();
                $this->setNameTag($this->customName . TextFormat::EOL . $names[array_rand($names)]);
            }
        }
        return $update;
    }

    public function sendSpawnItems(): void{
        $this->getArmorInventory()->setHelmet(VanillaBlocks::MOB_HEAD()->setMobHeadType(MobHeadType::PLAYER())->asItem());
        $this->getArmorInventory()->setChestplate(VanillaItems::LEATHER_TUNIC());
        $this->getArmorInventory()->setLeggings(VanillaItems::LEATHER_PANTS());
        $this->getArmorInventory()->setBoots(VanillaItems::LEATHER_BOOTS());
    }

    public function breakBlock(Block $block): bool{
        if($this->chestPosition === null){
            return false;
        }
        $success = false;
        $tile = $this->getWorld()->getTile($this->chestPosition);

        if($tile instanceof Chest){
            $inv = $tile->getInventory();
            $smeltLevel = Configuration::getSmeltLevel();

            if(Configuration::isNormalPickaxe() || (strtolower((string)$smeltLevel) !== "n" && $this->getLevel() >= $smeltLevel)){
                foreach($block->getDropsForCompatibleTool($this->getInventory()->getItemInHand()) as $drop){
                    $inv->addItem($drop);
                }
                $success = true;
            }
            if(!in_array($block->getName(), Configuration::getUnbreakableBlocks()) && !$success){
                $inv->addItem($block->asItem());
                $success = true;
            }
        }
        if($success){
            $this->getWorld()->broadcastPacketToViewers($this->getPosition(), AnimatePacket::create($this->id, AnimatePacket::ACTION_SWING_ARM));
            $this->getWorld()->setBlock($block->getPosition(), VanillaBlocks::AIR(), true);
        }
        return $success;
    }

    public function getPlayer(): ?string{
        return $this->player;
    }

    public function getLevel(): int{
        return $this->level;
    }

    public function getLookingBlock(): Block{
        return $this->getWorld()->getBlock(clone $this->getPosition()->addVector($this->getDirectionVector()->multiply(1)));
    }

    public function getLookingBehind(): Block{
        return $this->getWorld()->getBlock(clone $this->getPosition()->addVector($this->getDirectionVector()->multiply(-1))->add(0, 1, 0));
    }

    public function isChestLinked(): bool{
        return $this->chestPosition !== null;
    }

    public function setChestPosition(?Vector3 $chestPosition): void{
        $this->chestPosition = $chestPosition;
    }

    public function getChestCoordinates(): string{
        if(!$this->isChestLinked()){
            return TextFormat::RED . "Not found";
        }
        $coord = TextFormat::YELLOW . "X: " . TextFormat::WHITE . $this->chestPosition->x . " ";
        $coord .= TextFormat::YELLOW . "Y: " . TextFormat::WHITE . $this->chestPosition->y . " ";
        $coord .= TextFormat::YELLOW . "Z: " . TextFormat::WHITE . $this->chestPosition->z . " ";
        return $coord;
    }

    public function getMainInventory(?callable $callable = null): InvMenu{
        $menu = InvMenu::create(InvMenu::TYPE_HOPPER);
        $inventory = $menu->getInventory();
        $inventory->setItem(0, VanillaItems::REDSTONE_DUST()->setCustomName(TextFormat::RED . "Destroys the miner"));

        $item = VanillaBlocks::CHEST()->asItem();
        $item->setCustomName(TextFormat::DARK_GREEN . "Link a chest");
        $item->setLore([" ",  TextFormat::YELLOW . "Linked: " . TextFormat::WHITE . ($this->isChestLinked() ? "Yes" : "Nope"), TextFormat::YELLOW . "Coordinates: " . TextFormat::WHITE . $this->getChestCoordinates()]);
        $inventory->setItem(1, $item);

        $menu->setListener(function (InvMenuTransaction $tr)use($callable): InvMenuTransactionResult{
            $player = $tr->getPlayer();
            $item = $tr->getItemClicked();

            switch($item->getTypeId()){
                case ItemTypeIds::REDSTONE_DUST:
                    if($this->isFlaggedForDespawn()){
                        return $tr->discard();
                    }
                    MinionListener::getInstance()->removeLinkable($player);
                    $this->flagForDespawn();
                    $player->getInventory()->addItem(Loader::getInstance()->asMinionItem(static::getMinionType(), $player, $this->getLevel()));
                    break;
                case VanillaBlocks::CHEST()->asItem()->getTypeId():
                    if($this->getLookingBehind() instanceof \pocketmine\block\Chest){
                        $player->sendMessage(TextFormat::RED . "Please remove the chest behind the miner, to set new linkable chest.");
                        return $tr->discard();
                    }
                    if(MinionListener::getInstance()->isLinkable($player)){
                        $player->sendMessage(TextFormat::RED . "You are already on linking mode.");
                        return $tr->discard();
                    }
                    MinionListener::getInstance()->addLinkable($player, $this);
                    $player->sendMessage(TextFormat::LIGHT_PURPLE . "Please tap the chest that you want to link with.");
                    break;
            }
            if($callable !== null){
                return $callable($tr);
            }
            return $tr->discard();
        });
        return $menu;
    }
}
