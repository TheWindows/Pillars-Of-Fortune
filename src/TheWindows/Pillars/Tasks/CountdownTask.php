<?php
namespace TheWindows\Pillars\Tasks;

use pocketmine\scheduler\Task;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\sound\ClickSound;
use TheWindows\Pillars\Main;

class CountdownTask extends Task {
    
    private $plugin;
    private $players;
    private $gameId;
    private $countdown;
    
    public function __construct(Main $plugin, array $players, string $gameId, int $countdown) {
        $this->plugin = $plugin;
        $this->players = $players;
        $this->gameId = $gameId;
        $this->countdown = $countdown;
        
        $this->plugin->getGameManager()->clearPersistentActionBar($this->gameId);
    }
    
    public function getCountdown(): int {
        return $this->countdown;
    }
    
    public function onRun(): void {
        
        $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($this->gameId);
        if($world === null) {
            $this->plugin->getGameManager()->cancelCountdown($this->gameId);
            $this->getHandler()?->cancel();
            return;
        }
        
        
        $currentPlayers = array_filter($world->getPlayers(), function($player) {
            return $player->isOnline() && 
                   !$player->isClosed() && 
                   $player->getGamemode()->equals(\pocketmine\player\GameMode::ADVENTURE());
        });
        
        
        $this->players = array_values($currentPlayers);
        
        
        $minPlayers = $this->plugin->getGameManager()->getMapSetting($this->gameId, "min_players", 2);
        $maxPlayers = $this->plugin->getGameManager()->getMapSetting($this->gameId, "max_players", 12);
        $currentPlayerCount = count($this->players);
        
        if($currentPlayerCount < $minPlayers) {
            $this->plugin->getGameManager()->safeCancelCountdown($this->gameId);
            $this->getHandler()?->cancel();
            return;
        }
        
        if($this->countdown <= 0) {
            $this->plugin->getGameManager()->startGame($this->players, $this->gameId);
            $this->getHandler()?->cancel();
            return;
        }
        
        
        $color = TextFormat::GREEN;
        $chatColor = "§a"; 
        if($this->countdown <= 5) {
            $color = TextFormat::RED;
            $chatColor = "§c"; 
        } elseif($this->countdown <= 10) {
            $color = TextFormat::YELLOW;
            $chatColor = "§e"; 
        }
        
        
        $actionBarMessage = "{$color}Starting in {$this->countdown} seconds... (§6{$currentPlayerCount}/{$maxPlayers}§r{$color})";
        foreach($world->getPlayers() as $player) {
            if($player->isOnline() && $player->getGamemode()->equals(\pocketmine\player\GameMode::ADVENTURE())) {
                $player->sendActionBarMessage($actionBarMessage);
            }
        }
        
        
        foreach($this->players as $player) {
            if($player->isOnline()) {
                $this->plugin->getScoreHUDManager()->updateGameScoreboard(
                    $player, 
                    $this->gameId, 
                    "Starting", 
                    $this->countdown,
                    $this->players
                );
            }
        }
        
        
        if($this->countdown <= 10) {
            foreach($world->getPlayers() as $player) {
                if($player->isOnline() && $player->getGamemode()->equals(\pocketmine\player\GameMode::ADVENTURE())) {
                    $player->sendMessage("§eGame starting in {$chatColor}{$this->countdown}§e seconds...");
                }
            }
        }
        
        
        if($this->countdown <= 10) {
            foreach($this->players as $player) {
                if($player->isOnline()) {
                    $player->getWorld()->addSound($player->getPosition(), new ClickSound(), [$player]);
                }
            }
        }
        
        
        if($this->countdown <= 10) {
            foreach($this->players as $player) {
                if($player->isOnline()) {
                    $titleColor = TextFormat::GREEN;
                    if($this->countdown <= 5) {
                        $titleColor = TextFormat::RED;
                    } elseif($this->countdown <= 8) {
                        $titleColor = TextFormat::YELLOW;
                    }
                    
                    $player->sendTitle(
                        $titleColor . $this->countdown,
                        TextFormat::GOLD . "Game starting...",
                        0, 20, 0
                    );
                }
            }
        }
        
        $this->countdown--;
    }
}