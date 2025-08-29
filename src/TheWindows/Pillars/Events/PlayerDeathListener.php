<?php

namespace TheWindows\Pillars\Events;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\player\Player;
use TheWindows\Pillars\Main;

class PlayerDeathListener implements Listener {
    private $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onPlayerDeath(PlayerDeathEvent $event): void {
        $player = $event->getPlayer();
        $gameId = $this->plugin->getGameManager()->getPlayerGame($player);
        
        if ($gameId === null) return;
        
        $this->plugin->getGameManager()->handlePlayerDeath($player, $gameId);
        
        $event->setCancelled();
    }
}