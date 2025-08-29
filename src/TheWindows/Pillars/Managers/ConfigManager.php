<?php
namespace TheWindows\Pillars\Managers;

use pocketmine\world\Position;
use TheWindows\Pillars\Main;

class ConfigManager {
    
    private $plugin;
    private $spawnConfig;
    private $mapsConfig;
    private $npcsConfig;
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        
        $this->spawnConfig = new \pocketmine\utils\Config(
            $plugin->getDataFolder() . "spawnpoints.yml",
            \pocketmine\utils\Config::YAML,
            ["spawn-points" => []]
        );
        
        $this->mapsConfig = new \pocketmine\utils\Config(
            $plugin->getDataFolder() . "maps.yml",
            \pocketmine\utils\Config::YAML,
            ["arena-worlds" => [], "map-settings" => []]
        );
        
        $this->npcsConfig = new \pocketmine\utils\Config(
            $plugin->getDataFolder() . "npcs.yml",
            \pocketmine\utils\Config::YAML,
            ["npcs" => []]
        );
    }
    
    public function getConfig() {
        return $this->plugin->getConfig();
    }
    
    public function getSpawnConfig() {
        return $this->spawnConfig;
    }
    
    public function getMapsConfig() {
        return $this->mapsConfig;
    }
    
    public function getNpcsConfig() {
        return $this->npcsConfig;
    }
    
    public function getMapSetting(string $worldName, string $key, $default = null) {
        $settings = $this->mapsConfig->get("map-settings", []);
        if(isset($settings[$worldName]) && isset($settings[$worldName][$key])) {
            return $settings[$worldName][$key];
        }
        return $default;
    }
    
    public function setMapSetting(string $worldName, string $setting, $value): void {
        $mapSettings = $this->mapsConfig->get("map-settings", []);
        if(!isset($mapSettings[$worldName])) {
            $mapSettings[$worldName] = [];
        }
        $mapSettings[$worldName][$setting] = $value;
        $this->mapsConfig->set("map-settings", $mapSettings);
        $this->mapsConfig->save();
    }
    
    public function saveSpawnPoints(array $spawnPoints): void {
        $serialized = [];
        foreach($spawnPoints as $worldName => $points) {
            foreach($points as $point) {
                $serialized[] = [
                    "x" => $point->getX(),
                    "y" => $point->getY(),
                    "z" => $point->getZ(),
                    "world" => $worldName
                ];
            }
        }
        $this->spawnConfig->set("spawn-points", $serialized);
        $this->spawnConfig->save();
    }
    
    public function loadSpawnPoints(): array {
        $spawnPoints = [];
        $data = $this->spawnConfig->get("spawn-points", []);
        
        foreach($data as $spawnData) {
            $worldManager = $this->plugin->getServer()->getWorldManager();
            if(!$worldManager->isWorldLoaded($spawnData["world"])) {
                $worldManager->loadWorld($spawnData["world"]);
            }
            
            $world = $worldManager->getWorldByName($spawnData["world"]);
            if($world !== null) {
                if(!isset($spawnPoints[$spawnData["world"]])) {
                    $spawnPoints[$spawnData["world"]] = [];
                }
                $spawnPoints[$spawnData["world"]][] = new Position(
                    $spawnData["x"],
                    $spawnData["y"],
                    $spawnData["z"],
                    $world
                );
            }
        }
        
        return $spawnPoints;
    }
    
    public function addArenaWorld(string $worldName): void {
        $arenas = $this->mapsConfig->get("arena-worlds", []);
        if(!in_array($worldName, $arenas)) {
            $arenas[] = $worldName;
            $this->mapsConfig->set("arena-worlds", $arenas);
            $this->mapsConfig->save();
        }
    }
    
    public function removeArenaWorld(string $worldName): void {
        $arenas = $this->mapsConfig->get("arena-worlds", []);
        $key = array_search($worldName, $arenas);
        if($key !== false) {
            unset($arenas[$key]);
            $this->mapsConfig->set("arena-worlds", array_values($arenas));
            $this->mapsConfig->save();
            $this->clearSpawnPointsForWorld($worldName);
        }
    }
    
    public function getArenaWorlds(): array {
        return $this->mapsConfig->get("arena-worlds", []);
    }
    
    public function saveNPCs(array $npcs): void {
        $this->npcsConfig->set("npcs", $npcs);
        $this->npcsConfig->save();
    }
    
    public function loadNPCs(): array {
        $data = $this->npcsConfig->get("npcs", []);
        return is_array($data) ? $data : [];
    }
    
    public function clearSpawnPointsForWorld(string $worldName): void {
        $spawnPoints = $this->loadSpawnPoints();
        if(isset($spawnPoints[$worldName])) {
            unset($spawnPoints[$worldName]);
            $this->saveSpawnPoints($spawnPoints);
        }
    }
    
    public function saveAll(): void {
        $this->saveConfigWithComments();
        $this->spawnConfig->save();
        $this->mapsConfig->save();
        $this->npcsConfig->save();
    }
    
    private function saveConfigWithComments(): void {
        $configFile = $this->plugin->getDataFolder() . "config.yml";
        
        
        $configData = $this->plugin->getConfig()->getAll();
        
        
        if (empty($configData)) {
            $configData = ['settings' => []];
        }
        
        
        $originalContent = file_exists($configFile) ? file_get_contents($configFile) : "";
        $lines = $originalContent ? explode("\n", $originalContent) : [];
        
        
        try {
            $newYaml = yaml_emit($configData, YAML_UTF8_ENCODING);
            $newLines = explode("\n", $newYaml);
        } catch (\Exception $e) {
            $this->plugin->getLogger()->error("Failed to serialize config data to YAML: " . $e->getMessage());
            
            $newLines = ["settings: {}"];
        }
        
        
        $result = [];
        $newLineIndex = 0;
        
        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if ($trimmedLine === '' || $trimmedLine[0] === '#') {
                $result[] = $line;
            } else {
                while ($newLineIndex < count($newLines) && trim($newLines[$newLineIndex]) === '') {
                    $newLineIndex++;
                }
                if ($newLineIndex < count($newLines)) {
                    
                    if (!in_array(trim($newLines[$newLineIndex]), ['---', '...'])) {
                        $result[] = $newLines[$newLineIndex];
                    }
                    $newLineIndex++;
                }
            }
        }
        
        
        while ($newLineIndex < count($newLines)) {
            if (trim($newLines[$newLineIndex]) !== '' && !in_array(trim($newLines[$newLineIndex]), ['---', '...'])) {
                $result[] = $newLines[$newLineIndex];
            }
            $newLineIndex++;
        }
        
        
        try {
            file_put_contents($configFile, implode("\n", $result));
        } catch (\Exception $e) {
            $this->plugin->getLogger()->error("Failed to save config file: " . $e->getMessage());
        }
    }
}