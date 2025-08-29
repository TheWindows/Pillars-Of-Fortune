<?php
namespace TheWindows\Pillars\Tasks;

use pocketmine\scheduler\Task;
use pocketmine\player\Player;
use pocketmine\item\VanillaItems;
use pocketmine\block\VanillaBlocks;
use TheWindows\Pillars\Main;
use xenialdan\apibossbar\BossBar;

class ItemDistributionTask extends Task {
    
    private $plugin;
    private $players;
    private $bossBars = [];
    private $timer;
    private $gameId;
    private $maxPlayers;
    private $itemInterval;
    
    public function __construct(Main $plugin, array $players, string $gameId, int $maxPlayers) {
        $this->plugin = $plugin;
        $this->players = $players;
        $this->gameId = $gameId;
        $this->maxPlayers = $maxPlayers;
        
        
        $this->itemInterval = $this->plugin->getConfigManager()->getMapSetting($gameId, "item-interval", 600);
        $this->timer = $this->itemInterval; 
        
        
        foreach($players as $player) {
            if($player->isOnline()) {
                $secondsLeft = ceil($this->timer / 20);
                $bossBar = new BossBar();
                $bossBar->setTitle("§cNext item in: §6{$secondsLeft}s");
                $bossBar->setSubTitle("§8V1.0.0Beta");
                $bossBar->setPercentage(1.0); 
                $bossBar->setColor(2); 
                $bossBar->addPlayer($player);
                $this->bossBars[$player->getId()] = $bossBar;
            }
        }
    }
    
   public function onRun(): void {
    
    $this->timer--;
    
    
    $onlinePlayers = [];
    foreach($this->players as $player) {
        if($player->isOnline() && !$player->isClosed()) {
            $gameState = $this->plugin->getGameManager()->checkPlayerState($player);
            
            if($gameState === 'playing') {
                $onlinePlayers[] = $player;
            }
        }
    }
    $this->players = $onlinePlayers;
    
    
    if(count($this->players) <= 1) {
        $this->plugin->getGameManager()->endGame($this->gameId);
        $this->cancel();
        return;
    }
    
    
    $secondsLeft = ceil($this->timer / 20);
    $percentage = max(0.0, min(1.0, $this->timer / $this->itemInterval));
    
    
    if($this->timer <= 0) {
        $this->distributeRandomItems();
        $this->timer = $this->itemInterval;
        $secondsLeft = ceil($this->itemInterval / 20);
        $percentage = 1.0;
    }
    
    
    $this->updateBossBars($percentage, "§cNext item in: §6{$secondsLeft}s");
}
    
    private function distributeRandomItems(): void {
        
        $possibleItems = [];
        
        
        foreach (VanillaItems::getAll() as $item) {
            if ($item->getName() !== "Air" && !$item->isNull()) {
                $possibleItems[] = $item;
            }
        }
        
        
        foreach (VanillaBlocks::getAll() as $block) {
            $item = $block->asItem();
            if ($item->getName() !== "Air" && !$item->isNull()) {
                $possibleItems[] = $item;
            }
        }
        
        
        if (empty($possibleItems)) {
            $this->plugin->getLogger()->warning("No valid items or blocks available for distribution in game {$this->gameId}");
            return;
        }
        
        foreach ($this->players as $player) {
            if ($player->isOnline()) {
                
                $randomItem = clone $possibleItems[array_rand($possibleItems)];
                
                
                $randomItem->setCount(1);
                
                
                $player->getInventory()->addItem($randomItem);
                
                
                $itemName = $randomItem->getName();
                $player->sendMessage("§aYou received a §e{$itemName}§a!");
            }
        }
    }
    
    private function updateBossBars(float $percentage, string $title): void {
        foreach ($this->bossBars as $playerId => $bossBar) {
            
            $player = null;
            foreach ($this->players as $p) {
                if ($p->getId() === $playerId && $p->isOnline()) {
                    $player = $p;
                    break;
                }
            }
            
            if ($player !== null) {
                $bossBar->setTitle($title);
                $bossBar->setSubTitle("§8V1.0.0Beta");
                $bossBar->setPercentage($percentage); 
                $bossBar->setColor(2); 
            } else {
                
                $bossBar->removeAllPlayers();
                unset($this->bossBars[$playerId]);
            }
        }
    }
    
    public function onCancel(): void {
        
        foreach ($this->bossBars as $bossBar) {
            $bossBar->removeAllPlayers();
        }
        $this->bossBars = [];
    }

    public function cancel(): void {
        $this->onCancel();
        if ($this->getHandler() !== null) {
            $this->getHandler()->cancel();
        }
    }
}