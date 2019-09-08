<?php

namespace CLADevs\Minion;

use CLADevs\Minion\upgrades\EventListener;
use pocketmine\command\Command;
use pocketmine\commadSender;
use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat as C;

class Main extends PluginBase implements Listener{

	private static $instance;

	public function onLoad(): void{
		self::$instance = $this;
	}

	public function onEnable(): void{
		Entity::registerEntity(Minion::class, true);
        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
	}
	
	public static function get(): self{
		return self::$instance;
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
	    if($sender instanceof Player){
            //to remove cmd remove this below than go to plugin.yml remove commands section
            if($command->getName() === "minion"){
                if($sender->isOp() === false) return false;
                $sender->getInventory()->addItem($this->getItem());
            }
        }
        return true;
    }

    public function getItem(): Item{
	    $item = Item::get(Item::NETHER_STAR);
	    $item->setCustomName(C::GREEN . "Miner " . C::GOLD . "Summoner");
	    $item->setLore([C::GRAY . "Automatic Miner"]);
	    $nbt = $item->getNamedTag();
	    $nbt->setString("summon", "miner");
	    $item->setNamedTag($nbt);
	    return $item;
    }
}
