<?php
namespace TheWindows\Pillars\Managers;

use pocketmine\world\Position;
use pocketmine\player\Player;
use TheWindows\Pillars\Main;

class SpawnManager {
    
    private $plugin;
    private $spawnPoints = [];
    
   public function __construct(Main $plugin) {
    $this->plugin = $plugin;
    
    $this->loadWorldsFromConfig();
    $this->spawnPoints = $this->plugin->getConfigManager()->loadSpawnPoints();
}

private function loadWorldsFromConfig(): void {
    $spawnConfig = $this->plugin->getConfigManager()->getSpawnConfig();
    $data = $spawnConfig->get("spawn-points", []);
    
    $worldsToLoad = [];
    foreach($data as $spawnData) {
        $worldName = $spawnData["world"];
        if(!in_array($worldName, $worldsToLoad)) {
            $worldsToLoad[] = $worldName;
        }
    }
    
    foreach($worldsToLoad as $worldName) {
        if(!$this->plugin->getServer()->getWorldManager()->isWorldLoaded($worldName)) {
            $this->plugin->getServer()->getWorldManager()->loadWorld($worldName);
        }
    }
}
    
    public function getSpawnPoints(): array {
        return $this->spawnPoints;
    }
    
    public function getSpawnPointsForWorld(string $worldName): array {
        return $this->spawnPoints[$worldName] ?? [];
    }
    
    public function addSpawnPoint(string $worldName, Position $position): bool {
        if(!isset($this->spawnPoints[$worldName])) {
            $this->spawnPoints[$worldName] = [];
        }
        
        
        $maxPlayers = $this->plugin->getGameManager()->getMapMaxPlayers($worldName);
        
        if(count($this->spawnPoints[$worldName]) >= $maxPlayers) {
            return false;
        }
        
        $this->spawnPoints[$worldName][] = $position;
        $this->plugin->getConfigManager()->saveSpawnPoints($this->spawnPoints);
        return true;
    }
    
    public function removeSpawnPoint(string $worldName, Position $position): bool {
        if(!isset($this->spawnPoints[$worldName])) {
            return false;
        }
        
        $tolerance = 0.1;
        
        foreach($this->spawnPoints[$worldName] as $key => $spawnPoint) {
            if(abs($spawnPoint->getX() - $position->getX()) < $tolerance &&
               abs($spawnPoint->getY() - $position->getY()) < $tolerance &&
               abs($spawnPoint->getZ() - $position->getZ()) < $tolerance) {
                unset($this->spawnPoints[$worldName][$key]);
                $this->spawnPoints[$worldName] = array_values($this->spawnPoints[$worldName]);
                $this->plugin->getConfigManager()->saveSpawnPoints($this->spawnPoints);
                return true;
            }
        }
        return false;
    }
    
    public function clearSpawnPoints(): void {
        $this->spawnPoints = [];
        $this->plugin->getConfigManager()->saveSpawnPoints($this->spawnPoints);
    }
    
    public function clearSpawnPointsForWorld(string $worldName): void {
        if(isset($this->spawnPoints[$worldName])) {
            unset($this->spawnPoints[$worldName]);
            $this->plugin->getConfigManager()->saveSpawnPoints($this->spawnPoints);
        }
    }
    
    public function teleportToLobby(Player $player): void {
    $lobbyWorld = "world";
    $lobby = $this->plugin->getServer()->getWorldManager()->getWorldByName($lobbyWorld);
    
    if($lobby !== null) {
        $player->teleport($lobby->getSpawnLocation());
    } else {
        $defaultWorld = $this->plugin->getServer()->getWorldManager()->getDefaultWorld();
        if($defaultWorld !== null) {
            $player->teleport($defaultWorld->getSpawnLocation());
        }
    }
}
}