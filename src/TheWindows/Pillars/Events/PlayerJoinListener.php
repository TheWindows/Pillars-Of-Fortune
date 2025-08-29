<?php
namespace TheWindows\Pillars\Events;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use TheWindows\Pillars\Main;

class PlayerJoinListener implements Listener {
    
    private $plugin;
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
    
    public function onJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        
        
        $player->setGamemode(\pocketmine\player\GameMode::SURVIVAL());
        
        
        $gameState = $this->plugin->getGameManager()->checkPlayerState($player);
        if($gameState !== null) {
            $this->plugin->getGameManager()->removePlayerFromGame($player);
        }
        
        
        $this->plugin->getScoreHUDManager()->setupDefaultTags($player);
        $this->plugin->getScoreHUDManager()->updateLobbyScoreboard($player);
        
        
    }
}