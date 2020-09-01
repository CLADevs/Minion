<?php

declare(strict_types=1);

namespace CLADevs\Minion\inventories;

use CLADevs\Minion\entities\MinionEntity;
use CLADevs\Minion\entities\types\MinerMinion;
use CLADevs\Minion\EventListener;
use CLADevs\Minion\utils\Configuration;
use onebone\economyapi\EconomyAPI;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class MinerInventory extends HopperInventory{

    /** @var MinerMinion */
    protected $entity;

    public function __construct(Position $position, MinionEntity $entity){
        parent::__construct($position, $entity);
        $this->setItem(4, $this->getLevelItem());
    }

    public function getLevelItem(): Item{
        $item = Item::get(Item::EMERALD);
        $item->setCustomName(TextFormat::LIGHT_PURPLE . "Level: " . TextFormat::YELLOW . $this->entity->getLevelM());
        $item->setLore([TextFormat::LIGHT_PURPLE . "Cost: " . TextFormat::YELLOW . "$" . $this->entity->getCost()]);
        return $item;
    }

    public function onListener(Player $player, Item $sourceItem, EventListener $listener): void{
        parent::onListener($player, $sourceItem, $listener);
        $entity = $this->entity;
        switch($sourceItem->getId()){
            case Item::EMERALD:
                if($entity->getLevelM() >= Configuration::getMaxLevel()){
                    $player->sendMessage(TextFormat::RED . "You have maxed the level!");
                    return;
                }
                if(class_exists('onebone\economyapi\EconomyAPI')){
                    if(EconomyAPI::getInstance()->myMoney($player) < $entity->getCost()){
                        $player->sendMessage(TextFormat::RED . "You don't have enough money.");
                        return;
                    }
                    $entity->namedtag->setInt("level", $entity->namedtag->getInt("level") + 1);
                    $player->sendMessage(TextFormat::GREEN . "Leveled up to " . $entity->getLevelM());
                    EconomyAPI::getInstance()->reduceMoney($player, $entity->getCost());
                }
                break;
        }
        $this->onClose($player);
    }
}