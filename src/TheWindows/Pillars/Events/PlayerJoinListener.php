<?php
namespace TheWindows\Pillars\Events;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use pocketmine\item\VanillaItems;
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
        
        $this->giveLobbyItems($player);
    }
    
    private function giveLobbyItems(Player $player): void {
        $config = $this->plugin->getConfig();
        $settings = $config->get("settings", []);
        
        $gameMenuSettings = $settings["game_menu"] ?? ["slot" => 0, "name" => "§4Game Menu §7(Right Click)"];
        $marketSettings = $settings["market"] ?? ["slot" => 8, "name" => "§d§lMarket §7(Right Click)"];
        
        $player->getInventory()->clearAll();
        
        $gameMenuItem = VanillaItems::COMPASS();
        $gameMenuItem->setCustomName($gameMenuSettings["name"]);
        $player->getInventory()->setItem((int)$gameMenuSettings["slot"], $gameMenuItem);
        
        $marketItem = VanillaItems::NETHER_STAR();
        $marketItem->setCustomName($marketSettings["name"]);
        $player->getInventory()->setItem((int)$marketSettings["slot"], $marketItem);
    }
}
