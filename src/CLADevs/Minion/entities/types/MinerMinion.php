<?php

declare(strict_types=1);

namespace CLADevs\Minion\entities\types;

use CLADevs\Minion\entities\MinionEntity;
use CLADevs\Minion\utils\Configuration;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use onebone\economyapi\EconomyAPI;
use pocketmine\block\Air;
use pocketmine\block\Chest;
use pocketmine\item\ItemIds;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class MinerMinion extends MinionEntity{

    public static function getMinionType(): string{
        return "Miner";
    }

    public function initNameTag(): void{
        $this->customName = $this->player . "'s Miner";
        parent::initNameTag();
    }

    public function getMaxTime(): int{
        return (20 * Configuration::getMaxLevel()) + 20;
    }

    public function getMineTime(): int{
        return $this->getMaxTime() - (20 * $this->getLevel());
    }

    public function getCost(int $level = 1): int{
        return Configuration::getLevelCost($level);
    }

    public function sendSpawnItems(): void{
        $this->getInventory()->setItemInHand(VanillaItems::DIAMOND_PICKAXE());
        parent::sendSpawnItems();
    }

    public function entityBaseTick(int $tickDiff = 1): bool{
        $update = parent::entityBaseTick($tickDiff);

        if(Server::getInstance()->getTick() % $this->getMineTime() == 0){
            //Checks if theres a chest behind him
            if(($block = $this->getLookingBehind()) instanceof Chest){
                $this->chestPosition = $block->getPosition();
            }
            //Update the coordinates
            if($this->chestPosition !== null){
                $block = $this->getWorld()->getBlock($this->chestPosition);

                if(!$block instanceof Chest){
                    $this->chestPosition = null;
                }
            }
            //Breaks
            if (!$this->getLookingBlock() instanceof Air && $this->isChestLinked()){
                $this->breakBlock($this->getLookingBlock());
            }
        }
        return $update;
    }

    protected function handleInventory(Player $attacker): void{
        $menu = $this->getMainInventory(function (InvMenuTransaction $tr): InvMenuTransactionResult{
            $player = $tr->getPlayer();
            $item = $tr->getItemClicked();

            if($item->getId() === ItemIds::EMERALD){
                if(($lvl = $this->getLevel()) >= Configuration::getMaxLevel()){
                    $player->sendMessage(TextFormat::RED . "You have maxed the level!");
                    return $tr->discard();
                }
                if(class_exists('onebone\economyapi\EconomyAPI')){
                    if(EconomyAPI::getInstance()->myMoney($player) < $this->getCost($lvl)){
                        $player->sendMessage(TextFormat::RED . "You don't have enough money.");
                        return $tr->discard();
                    }
                    $this->level++;
                    $player->sendMessage(TextFormat::GREEN . "Leveled up to " . $this->getLevel());
                    EconomyAPI::getInstance()->reduceMoney($player, $this->getCost($lvl));
                }
            }
            InvMenuHandler::getPlayerManager()->get($player)->removeCurrentMenu();
            return $tr->discard();
        });
        $inventory = $menu->getInventory();
        $item = VanillaItems::EMERALD();
        $item->setCustomName(TextFormat::LIGHT_PURPLE . "Level: " . TextFormat::YELLOW . ($lvl = $this->getLevel()));
        $item->setLore([TextFormat::LIGHT_PURPLE . "Cost: " . TextFormat::YELLOW . "$" . $this->getCost($lvl)]);
        $inventory->setItem(4, $item);
        $menu->send($attacker);
    }
}