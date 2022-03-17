<?php

declare(strict_types=1);

namespace CLADevs\Minion;

use CLADevs\Minion\entities\MinionEntity;
use CLADevs\Minion\entities\types\FarmerMinion;
use CLADevs\Minion\entities\types\MinerMinion;
use CLADevs\Minion\utils\Configuration;
use CLADevs\Minion\utils\Utils;
use pocketmine\block\Chest;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;

class MinionListener implements Listener{
    use SingletonTrait;

    /** @var MinionEntity[] */
    public array $linkable = [];

    public function __construct(){
        self::setInstance($this);
    }

    public function isLinkable(Player $player): bool{
        return isset($this->linkable[$player->getName()]);
    }

    public function addLinkable(Player $player, MinionEntity $minion): bool{
        $overwrite = false;
        if($this->isLinkable($player)){
            $overwrite = true;
        }
        $this->linkable[$player->getName()] = $minion;
        return $overwrite;
    }

    public function removeLinkable(Player $player): void{
        if($this->isLinkable($player)) unset($this->linkable[$player->getName()]);
    }

    public function onInteract(PlayerInteractEvent $e): void{
        $player = $e->getPlayer();
        $item = $e->getItem();
        $dnbt = $item->getNamedTag();

        if($dnbt->getTag("summon") and !$e->isCancelled()){
            if(in_array($player->getWorld()->getFolderName(), Configuration::getNotAllowWorlds())){
                $player->sendMessage(TextFormat::RED . "You can't place minions in this world.");
                return;
            }
            $nbt = new CompoundTag();
            $nbt->setInt(MinionEntity::TAG_LEVEL, $dnbt->getInt(MinionEntity::TAG_LEVEL, 1));
            $nbt->setString(MinionEntity::TAG_PLAYER, $player->getName());
            $nbt->setString(MinionEntity::TAG_XYZ, $dnbt->getString(MinionEntity::TAG_XYZ, ""));

            $loc = clone $player->getLocation();
            $loc->yaw = (90 + (Utils::getDirectionFromYaw($player->getLocation()->yaw) * 90)) % 360;
            $entity = match($dnbt->getString("summon")){
                MinerMinion::getMinionType() => new MinerMinion($loc, $player->getSkin(), $nbt),
                FarmerMinion::getMinionType() => new FarmerMinion($loc, $player->getSkin(), $nbt),
                default => null
            };
            if($entity !== null){
                $entity->spawnToAll();
                $item->setCount($item->getCount() - 1);
                $player->getInventory()->setItemInHand($item);
            }
        }

        if($this->isLinkable($player)){
            $block = $e->getBlock();
            if(!$block instanceof Chest){
                $player->sendMessage(TextFormat::RED . "Please tap a chest not a " . $e->getBlock()->getName());
                return;
            }
            $entity = $this->linkable[$player->getName()];
            if($entity instanceof MinionEntity){
                $entity->setChestPosition($block->getPosition());
            }
            $this->removeLinkable($player);
            $player->sendMessage(TextFormat::GREEN . "You have linked a chest!");
            $e->cancel();
        }
    }
}
