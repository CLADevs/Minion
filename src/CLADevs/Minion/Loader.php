<?php

namespace CLADevs\Minion;

use CLADevs\Minion\entities\MinionEntity;
use CLADevs\Minion\entities\types\FarmerMinion;
use CLADevs\Minion\entities\types\MinerMinion;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;

class Loader extends PluginBase{
    use SingletonTrait;

    private array $removeTap = [];

    public function onLoad(): void{
        self::setInstance($this);
        foreach(array_keys($this->getResources()) as $path){
            $this->saveResource($path);
        }
    }

    public function onEnable(): void{
        if(!InvMenuHandler::isRegistered()) InvMenuHandler::register($this);
        EntityFactory::getInstance()->register(MinerMinion::class, function(World $world, CompoundTag $nbt): MinerMinion{
            return new MinerMinion(EntityDataHelper::parseLocation($nbt, $world), MinerMinion::parseSkinNBT($nbt), $nbt);
        }, ["MinerMinion"]);
        EntityFactory::getInstance()->register(FarmerMinion::class, function(World $world, CompoundTag $nbt): FarmerMinion{
            return new FarmerMinion(EntityDataHelper::parseLocation($nbt, $world), FarmerMinion::parseSkinNBT($nbt), $nbt);
        }, ["FarmerMinion"]);
        $this->getServer()->getPluginManager()->registerEvents(new MinionListener(), $this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
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
        if(isset($args[1]) && (!$player = $this->getServer()->getPlayerByPrefix($args[1]))){
            $sender->sendMessage(TextFormat::RED . "That player could not be found.");
            return false;
        }
        $type = $args[0];
        $type = match (strtolower($type)){
            "miner", "m" => MinerMinion::getMinionType(),
            "farmer", "f" => FarmerMinion::getMinionType(),
            default => null,
        };
        if($type === null){
            $sender->sendMessage(TextFormat::RED . "Unknown type: " . $args[0]);
            return false;
        }
        if($player instanceof Player){
            $player->getInventory()->addItem($this->asMinionItem($type, $player));
            $sender->sendMessage(TextFormat::GREEN . "Given " . $player->getName() . " $type minion spawner.");
        }
        return true;
    }

    public function asMinionItem(string $type, Player $sender, int $level = 1, string $xyz = "n"): Item{
        $path = $this->getDataFolder() . "/minions/$type.yml";

        if(file_exists($path)){
            $config = new Config($path, Config::YAML);
            $data = $config->getNested("item");
            $item = StringToItemParser::getInstance()->parse($data["item"]);
            $item->setCustomName(TextFormat::colorize($data["name"]));
            $item->setLore(array_map(fn(string $value) => TextFormat::colorize($value), $data["lore"]));
            $nbt = $item->getNamedTag();
            $nbt->setString("summon", $type);
            $nbt->setString(MinionEntity::TAG_PLAYER, $sender->getName());
            $nbt->setString(MinionEntity::TAG_XYZ, $xyz);
            $nbt->setInt(MinionEntity::TAG_LEVEL, $level);
            $item->setNamedTag($nbt);
            return $item;
        }
        return VanillaItems::AIR();
    }

    public function isInRemove(Player $player): bool{
        return isset($this->removeTap[$player->getName()]);
    }
}
