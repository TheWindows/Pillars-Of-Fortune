<?php
namespace TheWindows\Pillars\Events;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use TheWindows\Pillars\Main;

class PlayerQuitListener implements Listener {
    
    private $plugin;
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
    
    public function onQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $this->plugin->getGameManager()->removePlayerFromGame($player);
        
        
        $this->plugin->getScoreHUDManager()->updateLobbyScoreboard($player);
    }
}