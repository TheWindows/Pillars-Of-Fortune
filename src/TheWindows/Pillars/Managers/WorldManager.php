<?php
namespace TheWindows\Pillars\Managers;

use pocketmine\world\World;
use pocketmine\world\WorldCreationOptions;
use pocketmine\utils\Filesystem;
use TheWindows\Pillars\Main;

class WorldManager {
    
    private $plugin;
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
    
    public function loadArenaWorlds(): void {
        $arenaWorlds = $this->plugin->getConfigManager()->getArenaWorlds();
        $loadedCount = 0;
        
        foreach($arenaWorlds as $worldName) {
            if(!$this->plugin->getServer()->getWorldManager()->isWorldLoaded($worldName)) {
                if($this->plugin->getServer()->getWorldManager()->loadWorld($worldName)) {
                    $this->plugin->getLogger()->info("Loaded arena world: " . $worldName);
                    $loadedCount++;
                } else {
                    $this->plugin->getLogger()->warning("Failed to load arena world: " . $worldName);
                }
            } else {
                $this->plugin->getLogger()->info("Arena world already loaded: " . $worldName);
                $loadedCount++;
            }
        }
        
        $this->plugin->getLogger()->info("Successfully loaded " . $loadedCount . "/" . count($arenaWorlds) . " arena worlds");
    }
    
    public function copyWorld(string $sourceWorld, string $targetWorld): bool {
        $sourcePath = $this->plugin->getServer()->getDataPath() . "worlds/" . $sourceWorld;
        $targetPath = $this->plugin->getServer()->getDataPath() . "worlds/" . $targetWorld;
        
        if(!is_dir($sourcePath)) {
            return false;
        }
        
        
        if($this->plugin->getServer()->getWorldManager()->isWorldLoaded($sourceWorld)) {
            $this->plugin->getServer()->getWorldManager()->unloadWorld(
                $this->plugin->getServer()->getWorldManager()->getWorldByName($sourceWorld)
            );
        }
        
        
        try {
            $this->recursiveCopySkipLocked($sourcePath, $targetPath);
            return true;
        } catch (\Exception $e) {
            $this->plugin->getLogger()->error("Failed to copy world: " . $e->getMessage());
            return false;
        }
    }
    
    private function recursiveCopySkipLocked(string $source, string $dest): void {
        if(is_dir($source)) {
            if(!is_dir($dest)) {
                mkdir($dest, 0755, true);
            }
            
            $files = scandir($source);
            foreach($files as $file) {
                if($file != "." && $file != "..") {
                    $this->recursiveCopySkipLocked("$source/$file", "$dest/$file");
                }
            }
        } else if(is_file($source)) {
            
            if(strpos($source, '.log') === false && strpos($source, '.lock') === false) {
                copy($source, $dest);
            }
        }
    }
    
    public function deleteWorld(string $worldName): bool {
        $worldPath = $this->plugin->getServer()->getDataPath() . "worlds/" . $worldName;
        
        if($this->plugin->getServer()->getWorldManager()->isWorldLoaded($worldName)) {
            $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($worldName);
            if($world !== null) {
                
                foreach($world->getPlayers() as $player) {
                    $lobby = $this->plugin->getServer()->getWorldManager()->getDefaultWorld();
                    if($lobby !== null) {
                        $player->teleport($lobby->getSpawnLocation());
                    }
                }
                $this->plugin->getServer()->getWorldManager()->unloadWorld($world);
            }
        }
        
        try {
            if(is_dir($worldPath)) {
                Filesystem::recursiveUnlink($worldPath);
            }
            return true;
        } catch (\Exception $e) {
            $this->plugin->getLogger()->error("Failed to delete world: " . $e->getMessage());
            return false;
        }
    }
    
    public function createGameWorld(string $templateWorld): ?string {
        $gameId = "game_" . uniqid();
        
        if($this->copyWorld($templateWorld, $gameId)) {
            if($this->plugin->getServer()->getWorldManager()->loadWorld($gameId)) {
                return $gameId;
            }
        }
        
        return null;
    }
    
    public function cleanupGameWorld(string $worldName): void {
        $this->plugin->getScheduler()->scheduleDelayedTask(
            new class($this, $worldName) extends \pocketmine\scheduler\Task {
                private $worldManager;
                private $worldName;
                
                public function __construct($worldManager, $worldName) {
                    $this->worldManager = $worldManager;
                    $this->worldName = $worldName;
                }
                
                public function onRun(): void {
                    $this->worldManager->deleteWorld($this->worldName);
                }
            },
            200 
        );
    }
}