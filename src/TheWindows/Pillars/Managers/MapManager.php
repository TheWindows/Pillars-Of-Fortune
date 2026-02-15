<?php
namespace TheWindows\Pillars\Managers;

use pocketmine\world\World;
use pocketmine\utils\Filesystem;
use TheWindows\Pillars\Main;

class MapManager {
    
    private $plugin;
    private $pluginPath;
    private $templateWorlds = [];
    private $resettingWorlds = [];
    
    public function __construct(Main $plugin, string $pluginPath) {
        $this->plugin = $plugin;
        $this->pluginPath = $pluginPath;
        $this->setupTemplateWorlds();
    }
    
    public function setupTemplateWorlds(): void {
        $mapsDataPath = $this->plugin->getDataFolder() . "Maps/";
        $worldsPath = $this->plugin->getServer()->getDataPath() . "worlds/";
        
        if (!is_dir($mapsDataPath)) {
            mkdir($mapsDataPath, 0755, true);
            $this->extractMapsFromResources($mapsDataPath);
        }
        
        $maps = scandir($mapsDataPath);
        foreach ($maps as $map) {
            if ($map === '.' || $map === '..') continue;
            
            $mapPath = $mapsDataPath . $map;
            $targetPath = $worldsPath . $map;
            
            if (is_dir($mapPath)) {
                if (!is_dir($targetPath)) {
                    $this->recursiveCopy($mapPath, $targetPath);
                    $this->plugin->getLogger()->info("Loaded template map: " . $map);
                }
                
                $this->templateWorlds[] = $map;
                
                if (!in_array($map, $this->plugin->getConfigManager()->getArenaWorlds())) {
                    $this->plugin->getConfigManager()->addArenaWorld($map);
                }
                
                $this->autoSetupSpawnPoints($map);
            }
        }
        
        $this->autoSetupMapsConfig();
    }
    
    private function extractMapsFromResources(string $targetPath): void {
        if (is_file($this->pluginPath) && pathinfo($this->pluginPath, PATHINFO_EXTENSION) === 'phar') {
            $this->extractFromPhar($this->pluginPath, $targetPath);
        } else {
            $this->extractFromSource($this->pluginPath, $targetPath);
        }
    }
    
    private function extractFromPhar(string $pharPath, string $targetPath): void {
        $phar = new \Phar($pharPath);
        $pharMapsPath = "Pillars/resources/Maps/";
        
        if ($phar->offsetExists($pharMapsPath)) {
            foreach (new \RecursiveIteratorIterator($phar->getIterator()) as $file) {
                if (strpos($file->getPathName(), $pharMapsPath) === 0) {
                    $relativePath = substr($file->getPathName(), strlen($pharMapsPath));
                    $destination = $targetPath . $relativePath;
                    
                    if ($file->isDir()) {
                        if (!is_dir($destination)) {
                            mkdir($destination, 0755, true);
                        }
                    } else {
                        if (!is_file($destination) || filesize($destination) === 0) {
                            file_put_contents($destination, $phar->offsetGet($file->getPathName()));
                        }
                    }
                }
            }
            $this->plugin->getLogger()->info("Extracted maps from phar to: " . $targetPath);
        } else {
            $this->plugin->getLogger()->warning("Default world maps directory not found in source resources its might because you using phar version!");
        }
    }

    private function extractFromSource(string $pluginPath, string $targetPath): void {
        $sourceMapsPath = dirname($pluginPath) . "/Pillars/resources/Maps/";
        
        if (is_dir($sourceMapsPath)) {
            $this->recursiveCopy($sourceMapsPath, $targetPath);
            $this->plugin->getLogger()->info("Copied maps from source to: " . $targetPath);
        } else {
            $this->plugin->getLogger()->warning("Default world maps directory not found in source resources its might because you using phar version!");
        }
    }
    
    public function autoSetupSpawnPoints(string $worldName): void {
        $spawnConfig = $this->plugin->getConfigManager()->getSpawnConfig();
        $existingSpawns = $spawnConfig->get("spawn-points", []);
        
        $hasSpawns = false;
        foreach ($existingSpawns as $spawn) {
            if ($spawn["world"] === $worldName) {
                $hasSpawns = true;
                break;
            }
        }
        
        if (!$hasSpawns && $worldName === "default") {
            $exactSpawns = [
                ["x" => 257.5, "y" => 88, "z" => 253.5, "world" => "default"],
                ["x" => 263.5, "y" => 88, "z" => 253.5, "world" => "default"],
                ["x" => 263.5, "y" => 88, "z" => 259.5, "world" => "default"],
                ["x" => 257.5, "y" => 88, "z" => 259.5, "world" => "default"],
                ["x" => 251.5, "y" => 88, "z" => 259.5, "world" => "default"],
                ["x" => 251.5, "y" => 88, "z" => 253.5, "world" => "default"],
                ["x" => 257.5, "y" => 88, "z" => 247.5, "world" => "default"],
                ["x" => 263.5, "y" => 88, "z" => 247.5, "world" => "default"],
                ["x" => 269.5, "y" => 88, "z" => 253.5, "world" => "default"],
                ["x" => 269.5, "y" => 88, "z" => 259.5, "world" => "default"],
                ["x" => 263.5, "y" => 88, "z" => 265.5, "world" => "default"],
                ["x" => 257.5, "y" => 88, "z" => 265.5, "world" => "default"]
            ];
            
            $allSpawns = array_merge($existingSpawns, $exactSpawns);
            $spawnConfig->set("spawn-points", $allSpawns);
            $spawnConfig->save();
            
            $this->plugin->getLogger()->info("Auto-created 12 exact spawn points for map: " . $worldName);
        } elseif (!$hasSpawns) {
            if (!$this->plugin->getServer()->getWorldManager()->isWorldLoaded($worldName)) {
                $this->plugin->getServer()->getWorldManager()->loadWorld($worldName);
            }
            
            $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($worldName);
            if ($world !== null) {
                $spawnLocation = $world->getSpawnLocation();
                $defaultSpawns = [];
                
                for ($i = 0; $i < 12; $i++) {
                    $angle = ($i / 12) * 2 * M_PI;
                    $x = $spawnLocation->getX() + 5 * cos($angle);
                    $z = $spawnLocation->getZ() + 5 * sin($angle);
                    $y = $spawnLocation->getY();
                    
                    $safeY = $world->getHighestBlockAt((int)$x, (int)$z) + 1;
                    if ($safeY > 0) {
                        $y = $safeY;
                    }
                    
                    $defaultSpawns[] = [
                        "x" => $x + 0.5, 
                        "y" => $y,
                        "z" => $z + 0.5, 
                        "world" => $worldName
                    ];
                }
                
                $allSpawns = array_merge($existingSpawns, $defaultSpawns);
                $spawnConfig->set("spawn-points", $allSpawns);
                $spawnConfig->save();
                
                $this->plugin->getLogger()->info("Auto-created 12 spawn points around spawn for map: " . $worldName);
            }
        }
    }
    
    private function autoSetupMapsConfig(): void {
        $mapsConfig = $this->plugin->getConfigManager()->getMapsConfig();
        $mapSettings = $mapsConfig->get("map-settings", []);
        $arenaWorlds = $mapsConfig->get("arena-worlds", []);
        
        $updated = false;
        
        foreach ($this->templateWorlds as $worldName) {
            if (!in_array($worldName, $arenaWorlds)) {
                $arenaWorlds[] = $worldName;
                $updated = true;
            }
            
            if (!isset($mapSettings[$worldName])) {
                $mapSettings[$worldName] = [
                    "max-players" => 12,
                    "min-players" => 2,
                    "game-time" => 600,
                    "countdown-time" => 30,
                    "item-interval" => 300
                ];
                $updated = true;
            }
        }
        
        if ($updated) {
            $mapsConfig->set("arena-worlds", $arenaWorlds);
            $mapsConfig->set("map-settings", $mapSettings);
            $mapsConfig->save();
            $this->plugin->getLogger()->info("Auto-configured maps.yml with default settings");
        }
    }
    
    public function recursiveCopy(string $source, string $dest): void {
        if (!is_dir($source) && !is_file($source)) {
            throw new \Exception("Source path does not exist: " . $source);
        }
        
        if (is_dir($source)) {
            if (!is_dir($dest)) {
                mkdir($dest, 0755, true);
            }
            
            $files = scandir($source);
            foreach ($files as $file) {
                if ($file != "." && $file != "..") {
                    $this->recursiveCopy("$source/$file", "$dest/$file");
                }
            }
        } else if (is_file($source)) {
            if (strpos($source, '.lock') !== false || strpos($source, '.log') !== false) {
                return;
            }
            
            $maxRetries = 3;
            $retryDelay = 100000; 
            
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    if (copy($source, $dest)) {
                        return;
                    }
                } catch (\Exception $e) {
                    if ($attempt === $maxRetries) {
                        throw new \Exception("Failed to copy file after $maxRetries attempts: " . $source . " -> " . $e->getMessage());
                    }
                    usleep($retryDelay);
                }
            }
        }
    }
    
    public function resetWorld(string $worldName): bool {
        if (isset($this->resettingWorlds[$worldName])) {
            $this->plugin->getLogger()->info("World $worldName is already being reset, skipping duplicate reset");
            return true;
        }
        
        $this->resettingWorlds[$worldName] = true;
        
        $mapsDataPath = $this->plugin->getDataFolder() . "Maps/" . $worldName;
        $worldsPath = $this->plugin->getServer()->getDataPath() . "worlds/" . $worldName;
        
        if (!is_dir($mapsDataPath)) {
            $this->plugin->getLogger()->warning("Template for world '$worldName' not found in plugin data!");
            unset($this->resettingWorlds[$worldName]);
            return false;
        }
        
        $worldManager = $this->plugin->getServer()->getWorldManager();
        if ($worldManager->isWorldLoaded($worldName)) {
            $world = $worldManager->getWorldByName($worldName);
            if ($world !== null) {
                foreach ($world->getPlayers() as $player) {
                    $this->plugin->getGameManager()->teleportToLobby($player);
                }
                $worldManager->unloadWorld($world, true);
                usleep(500000); 
            }
        }
        
        if (is_dir($worldsPath)) {
            try {
                Filesystem::recursiveUnlink($worldsPath);
            } catch (\Exception $e) {
                $this->plugin->getLogger()->error("Failed to delete world: " . $e->getMessage());
                unset($this->resettingWorlds[$worldName]);
                return false;
            }
        }
        
        try {
            $this->recursiveCopy($mapsDataPath, $worldsPath);
        } catch (\Exception $e) {
            $this->plugin->getLogger()->error("Failed to copy template: " . $e->getMessage());
            unset($this->resettingWorlds[$worldName]);
            return false;
        }
        
        if ($worldManager->loadWorld($worldName)) {
            $this->plugin->getLogger()->info("Successfully reset world: " . $worldName);
            $this->autoSetupSpawnPoints($worldName);
            
            $this->plugin->getNPCManager()->spawnNPCsInWorld($worldName);
            
            unset($this->resettingWorlds[$worldName]);
            return true;
        }
        
        $this->plugin->getLogger()->warning("Failed to load world after reset: " . $worldName);
        unset($this->resettingWorlds[$worldName]);
        return false;
    }
    
    public function getTemplateWorlds(): array {
        return $this->templateWorlds;
    }
}
