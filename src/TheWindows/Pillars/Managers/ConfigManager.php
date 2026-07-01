<?php
declare(strict_types=1);
namespace TheWindows\Pillars\Managers;

use pocketmine\utils\Config;
use pocketmine\world\Position;
use TheWindows\Pillars\Main;

class ConfigManager {

    private Main $plugin;
    private Config $spawnConfig;
    private Config $mapsConfig;
    private Config $npcsConfig;

    private array $configData = [];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;

        $this->spawnConfig = new Config(
            $plugin->getDataFolder() . 'spawnpoints.yml',
            Config::YAML,
            ['spawn-points' => []]
        );

        $this->mapsConfig = new Config(
            $plugin->getDataFolder() . 'maps.yml',
            Config::YAML,
            ['arena-worlds' => [], 'map-settings' => []]
        );

        $this->npcsConfig = new Config(
            $plugin->getDataFolder() . 'npcs.yml',
            Config::YAML,
            ['npcs' => []]
        );

        $this->configData = $this->getConfig()->getAll();
    }

    public function getConfig(): Config {
        return $this->plugin->getConfig();
    }

    public function getConfigValue(string $key, mixed $default = null):mixed{
        if(isset($this->configData[$key])) return $this->configData[$key];
        return $default;
    }

    public function getConfigData():array{
        return $this->configData;
    }

    public function getSpawnConfig(): Config {
        return $this->spawnConfig;
    }

    public function getMapsConfig(): Config {
        return $this->mapsConfig;
    }

    public function getNpcsConfig(): Config {
        return $this->npcsConfig;
    }

    public function getMapSetting(string $worldName, string $key, mixed $default = null): mixed {
        $settings = $this->mapsConfig->get('map-settings', []);
        return $settings[$worldName][$key] ?? $default;
    }

    public function setMapSetting(string $worldName, string $setting, mixed $value): void {
        $mapSettings = $this->mapsConfig->get('map-settings', []);
        if (!isset($mapSettings[$worldName])) {
            $mapSettings[$worldName] = [];
        }
        $mapSettings[$worldName][$setting] = $value;
        $this->mapsConfig->set('map-settings', $mapSettings);
        $this->mapsConfig->save();
    }

    public function saveSpawnPoints(array $spawnPoints): void {
        $serialized = [];
        foreach ($spawnPoints as $worldName => $points) {
            foreach ($points as $point) {
                $serialized[] = [
                    'x' => $point->getX(),
                    'y' => $point->getY(),
                    'z' => $point->getZ(),
                    'world' => $worldName,
                ];
            }
        }
        $this->spawnConfig->set('spawn-points', $serialized);
        $this->spawnConfig->save();
    }

    public function loadSpawnPoints(): array {
        $spawnPoints = [];
        $data = $this->spawnConfig->get('spawn-points', []);

        foreach ($data as $spawnData) {
            $worldManager = $this->plugin->getServer()->getWorldManager();
            if (!$worldManager->isWorldLoaded($spawnData['world'])) {
                $worldManager->loadWorld($spawnData['world']);
            }
            $world = $worldManager->getWorldByName($spawnData['world']);
            if ($world !== null) {
                $spawnPoints[$spawnData['world']][] = new Position(
                    $spawnData['x'],
                    $spawnData['y'],
                    $spawnData['z'],
                    $world
                );
            }
        }

        return $spawnPoints;
    }

    public function addArenaWorld(string $worldName): void {
        $arenas = $this->mapsConfig->get('arena-worlds', []);
        if (!in_array($worldName, $arenas, true)) {
            $arenas[] = $worldName;
            $this->mapsConfig->set('arena-worlds', $arenas);
            $this->mapsConfig->save();
        }
    }

    public function removeArenaWorld(string $worldName): void {
        $arenas = $this->mapsConfig->get('arena-worlds', []);
        $key = array_search($worldName, $arenas, true);
        if ($key !== false) {
            unset($arenas[$key]);
            $this->mapsConfig->set('arena-worlds', array_values($arenas));
            $this->mapsConfig->save();
            $this->clearSpawnPointsForWorld($worldName);
        }
    }

    public function getArenaWorlds(): array {
        return $this->mapsConfig->get('arena-worlds', []);
    }

    public function saveNPCs(array $npcs): void {
        $this->npcsConfig->set('npcs', $npcs);
        $this->npcsConfig->save();
    }

    public function loadNPCs(): array {
        $data = $this->npcsConfig->get('npcs', []);
        return is_array($data) ? $data : [];
    }

    public function clearSpawnPointsForWorld(string $worldName): void {
        $spawnPoints = $this->loadSpawnPoints();
        if (isset($spawnPoints[$worldName])) {
            unset($spawnPoints[$worldName]);
            $this->saveSpawnPoints($spawnPoints);
        }
    }

    public function saveAll(): void {
        $this->plugin->getConfig()->save();
        $this->spawnConfig->save();
        $this->mapsConfig->save();
        $this->npcsConfig->save();
    }
}
