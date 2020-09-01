<?php

declare(strict_types=1);

namespace CLADevs\Minion;

use CLADevs\Minion\entities\types\FarmerMinion;
use CLADevs\Minion\entities\types\MinerMinion;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

class Main extends PluginBase{

    /** @var Main */
	private static $instance;
	/** @var array  */
	private $removeTap = [];

	public function onLoad(): void{
		self::$instance = $this;
        $this->saveDefaultConfig();
	}

	public function onEnable(): void{
	    Entity::registerEntity(MinerMinion::class, true);
	    $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
	    if(!class_exists('onebone\economyapi\EconomyAPI')){
	        $this->getLogger()->error(TextFormat::RED . "EconomyAPI is required for this plugin to work.");
	        $this->getServer()->getPluginManager()->disablePlugin($this);
	        return;
        }
	    if(!class_exists('muqsit\invcrashfix\Loader')){
	        $this->getLogger()->error(TextFormat::RED . "InvCrashFix By Muqsit is required to fix inventory crash.");
	        $this->getServer()->getPluginManager()->disablePlugin($this);
	        return;
        }
    }

	public static function get(): self{
		return self::$instance;
	}

	public function isInRemove(Player $player): bool{
	    return isset($this->removeTap[$player->getName()]);
    }

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
	    if($command->getName() === "minion"){
	        if(!$sender->hasPermission("minion.commands")){
	            $sender->sendMessage(TextFormat::RED . "You don't have permission to run this command.");
	            return false;
            }
	        if(!isset($args[1]) && (!$sender instanceof Player || !isset($args[0]))){
                $sender->sendMessage("Usage: /minion (miner|farmer) (player)");
                return false;
            }
	        if($sender instanceof Player && isset($args[0]) && in_array(strtolower($args[0]), ["rm", "remove", "forceremove"])){
	            if($this->isInRemove($sender)){
	                $sender->sendMessage(TextFormat::GREEN . "You have left the minion removable mode.");
	                unset($this->removeTap[$sender->getName()]);
	                return true;
                }
	            $sender->sendMessage(TextFormat::GREEN . "You have entered minion removable mode.");
	            $sender->sendMessage(TextFormat::YELLOW . "To disable repeat same command.");
	            $this->removeTap[$sender->getName()] = $sender;
	            return true;
            }
	        $player = $sender;
	        if(isset($args[1]) && (!$player = $this->getServer()->getPlayer($args[1]))){
                $sender->sendMessage(TextFormat::RED . "That player could not be found.");
                return false;
            }
	        $type = $args[0];
	        switch(strtolower($type)){
                case "miner":
                case "m":
                    $type = MinerMinion::NAME;
                    break;
                case "farmer":
                case "f":
                    $type = FarmerMinion::NAME;
                    break;
                default:
                    $type = null;
                    break;
            }
            if($type === null){
                $sender->sendMessage(TextFormat::RED . "Unknown type: " . $args[0]);
                return false;
            }
	        $player->getInventory()->addItem(self::asItem($type, $player));
	        $sender->sendMessage(TextFormat::GREEN . "Given " . $player->getName() . " $type minion spawner.");
        }
        return true;
    }

    public static function asItem(string $type, Player $sender, int $level = 1, string $xyz = "n"): Item{
        $minionType = "Miner";
	    $msgByType = "I will mine block in front me";
	    if($type === FarmerMinion::NAME){
	        $minionType = "Farmer";
	        $msgByType = "I will harvest crops around me";
        }
	    $lore = [];
	    foreach(
	        [
                "Tap the ground to place me",
                $msgByType,
                "Please place chest behind me",
                "These steps help me get started"

        ] as $l){
	        $lore[] = TextFormat::RESET . TextFormat::GRAY . "* " . TextFormat::YELLOW . $l;
        }
        $item = Item::get(Item::NETHER_STAR);
        $item->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::AQUA . "* " . TextFormat::GOLD . "Minion " . TextFormat::AQUA . "$minionType *");
        $item->setLore($lore);
        $nbt = $item->getNamedTag();
        $nbt->setString("summon", $type);
        $nbt->setString("player", $sender->getName());
        $nbt->setString("xyz", $xyz);
        $nbt->setInt("level", $level);
        $item->setNamedTag($nbt);
        return $item;
    }
}
