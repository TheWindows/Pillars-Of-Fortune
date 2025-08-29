<?php
namespace TheWindows\Pillars\Managers;

use pocketmine\player\Player;
use Ifera\ScoreHud\event\PlayerTagsUpdateEvent;
use Ifera\ScoreHud\scoreboard\ScoreTag;
use TheWindows\Pillars\Main;

class ScoreHUDManager {
    
    private $plugin;
    private $scoreHudLoaded = false;
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->checkScoreHud();
    }
    
    private function checkScoreHud(): void {
        $scoreHud = $this->plugin->getServer()->getPluginManager()->getPlugin("ScoreHud");
        if($scoreHud !== null && $scoreHud->isEnabled()) {
            $this->scoreHudLoaded = true;
        } else {
        }
    }
    
    public function isScoreHudLoaded(): bool {
        return $this->scoreHudLoaded;
    }
    
    public function updateGameScoreboard(Player $player, string $gameId, string $status, int $countdown = 0, array $alivePlayers = []): void {
        if(!$this->scoreHudLoaded) return;
        
        

    $mapName = $gameId;
    $maxPlayers = $this->plugin->getGameManager()->getMapMaxPlayers($gameId);
    $currentPlayers = count($this->plugin->getGameManager()->getAlivePlayers($gameId));
    
    
    $playerStats = $this->plugin->getGameManager()->getPlayerStats($player);
    $coins = $playerStats['coins'];
    $wins = $playerStats['wins'];
    
    
    $tags = [
        new ScoreTag("pillars.status", $status),
        new ScoreTag("pillars.map", $mapName),
        new ScoreTag("pillars.players", "§a{$currentPlayers}§7/§c{$maxPlayers}"),
        new ScoreTag("pillars.countdown", $countdown > 0 ? "§e{$countdown}s" : ""),
        new ScoreTag("pillars.coins", "§e{$coins}"),
        new ScoreTag("pillars.wins", "§e{$wins}")
    ];
        
        
        $aliveList = "";
        if(!empty($alivePlayers)) {
            $displayPlayers = array_slice($alivePlayers, 0, 5);
            $aliveList = implode("§7, §a", array_map(function($p) { 
                return $p->getName(); 
            }, $displayPlayers));
            
            if(count($alivePlayers) > 5) {
                $aliveList .= "§7...";
            }
        }
        $tags[] = new ScoreTag("pillars.alive_players", $aliveList);
        
        $this->updatePlayerTags($player, $tags);
    }
    
    public function updateLobbyScoreboard(Player $player): void {
        if(!$this->scoreHudLoaded) return;
        
        
        $playerStats = $this->plugin->getGameManager()->getPlayerStats($player);
        $coins = $playerStats['coins'];
        $wins = $playerStats['wins'];
        
        
        $tags = [
            new ScoreTag("pillars.status", "§aLobby"),
            new ScoreTag("pillars.map", ""),
            new ScoreTag("pillars.players", ""),
            new ScoreTag("pillars.countdown", ""),
            new ScoreTag("pillars.coins", "§e{$coins}"),
            new ScoreTag("pillars.wins", "§e{$wins}"),
            new ScoreTag("pillars.alive_players", "")
        ];
        
        $this->updatePlayerTags($player, $tags);
    }
    
    public function updatePlayerStats(Player $player): void {
        if(!$this->scoreHudLoaded) return;
        
        
        $gameId = $this->plugin->getGameManager()->getPlayerGame($player);
        
        if($gameId !== null) {
            
            $game = $this->plugin->getGameManager()->games[$gameId] ?? null;
            if($game !== null) {
                $status = $game['status'];
                $countdown = 0;
                
                if(isset($this->plugin->getGameManager()->countdownTasks[$gameId])) {
                    $countdown = $this->plugin->getGameManager()->countdownTasks[$gameId]->getCountdown() ?? 0;
                }
                
                $alivePlayers = $this->plugin->getGameManager()->getAlivePlayers($gameId);
                $this->updateGameScoreboard($player, $gameId, ucfirst($status), $countdown, $alivePlayers);
            }
        } else {
            
            $this->updateLobbyScoreboard($player);
        }
    }
    
    public function setupDefaultTags(Player $player): void {
        if(!$this->scoreHudLoaded) return;
        
        
        $playerStats = $this->plugin->getGameManager()->getPlayerStats($player);
        $coins = $playerStats['coins'];
        $wins = $playerStats['wins'];
        
        
        $tags = [
            new ScoreTag("pillars.status", "§aLobby"),
            new ScoreTag("pillars.map", ""),
            new ScoreTag("pillars.players", ""),
            new ScoreTag("pillars.countdown", ""),
            new ScoreTag("pillars.coins", "§e{$coins}"),
            new ScoreTag("pillars.wins", "§e{$wins}"),
            new ScoreTag("pillars.alive_players", "")
        ];
        
        $this->updatePlayerTags($player, $tags);
    }
    
    private function updatePlayerTags(Player $player, array $tags): void {
        if(!$this->scoreHudLoaded) return;
        
        $ev = new PlayerTagsUpdateEvent($player, $tags);
        $ev->call();
    }
}