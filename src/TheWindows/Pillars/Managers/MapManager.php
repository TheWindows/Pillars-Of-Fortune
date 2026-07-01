<?php
declare(strict_types=1);
namespace TheWindows\Pillars\Managers;

use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Filesystem;
use pocketmine\world\WorldManager;
use TheWindows\Pillars\Main;

class MapManager {

    private Main $plugin;
    private string $pluginPath;
    private array $templateWorlds = [];
    private array $resettingWorlds = [];

    public function __construct(Main $plugin, string $pluginPath) {
        $this->plugin = $plugin;
        $this->pluginPath = $pluginPath;
    }

    public function setupTemplateWorlds(): void {
        $mapsDataPath = $this->plugin->getDataFolder() . 'Maps/';

        if (!is_dir($mapsDataPath)) {
            mkdir($mapsDataPath, 0755, true);
            $this->extractMapsFromResources($mapsDataPath);
        }

        foreach (scandir($mapsDataPath) as $map) {
            if ($map === '.' || $map === '..') {
                continue;
            }
            $mapPath = $mapsDataPath . $map;
            if (!is_dir($mapPath)) {
                continue;
            }
            $this->templateWorlds[] = $map;
        }

        $this->plugin->getLogger()->info('Found ' . count($this->templateWorlds) . ' map template(s): ' . implode(', ', $this->templateWorlds));
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
        $pharMapsPath = 'Pillars/resources/Maps/';

        if ($phar->offsetExists($pharMapsPath)) {
            foreach (new \RecursiveIteratorIterator($phar->getIterator()) as $file) {
                if (!str_starts_with($file->getPathName(), $pharMapsPath)) {
                    continue;
                }
                $relativePath = substr($file->getPathName(), strlen($pharMapsPath));
                $destination = $targetPath . $relativePath;
                if ($file->isDir()) {
                    if (!is_dir($destination)) {
                        mkdir($destination, 0755, true);
                    }
                } elseif (!is_file($destination) || filesize($destination) === 0) {
                    file_put_contents($destination, $phar->offsetGet($file->getPathName()));
                }
            }
        }
    }

    private function extractFromSource(string $pluginPath, string $targetPath): void {
        $sourceMapsPath = dirname($pluginPath) . '/Pillars/resources/Maps/';
        if (is_dir($sourceMapsPath)) {
            $this->recursiveCopy($sourceMapsPath, $targetPath);
        }
    }

    public function getTemplateWorlds(): array {
        return $this->templateWorlds;
    }

    public function getRandomTemplateName(): ?string {
        if (empty($this->templateWorlds)) {
            return null;
        }
        return $this->templateWorlds[array_rand($this->templateWorlds)];
    }

    public function generateInstanceName(string $templateName): string {
        $worldsPath = $this->plugin->getServer()->getDataPath() . 'worlds/';
        do {
            $id = mt_rand(1000, 9999);
            $name = $templateName . '_' . $id;
        } while (is_dir($worldsPath . $name));
        return $name;
    }

    public function createInstance(string $instanceName, string $templateName): bool {
        $mapsDataPath = $this->plugin->getDataFolder() . 'Maps/' . $templateName;
        $worldsPath = $this->plugin->getServer()->getDataPath() . 'worlds/' . $instanceName;

        if (!is_dir($mapsDataPath)) {
            $this->plugin->getLogger()->error("Template '$templateName' not found in plugin data.");
            return false;
        }

        try {
            $this->recursiveCopy($mapsDataPath, $worldsPath);
        } catch (\Exception $e) {
            $this->plugin->getLogger()->error("Failed to copy template '$templateName' to '$instanceName': " . $e->getMessage());
            return false;
        }

        $wm = $this->plugin->getServer()->getWorldManager();
        if (!$wm->loadWorld($instanceName)) {
            $this->plugin->getLogger()->error("Failed to load world '$instanceName' after copy.");
            if (is_dir($worldsPath)) {
                try { Filesystem::recursiveUnlink($worldsPath); } catch (\Exception) {}
            }
            return false;
        }

        $this->plugin->getLogger()->info("Created game instance '$instanceName' from template '$templateName'.");
        return true;
    }

    public function saveWorldAsTemplate(string $worldName): bool {
        $wm = $this->plugin->getServer()->getWorldManager();

        if (!$wm->isWorldLoaded($worldName)) {
            if (!$wm->loadWorld($worldName)) {
                $this->plugin->getLogger()->error("Cannot save template: world '$worldName' could not be loaded.");
                return false;
            }
        }

        $world = $wm->getWorldByName($worldName);
        if ($world === null) {
            return false;
        }

        $world->save(true);

        $worldsPath = $this->plugin->getServer()->getDataPath() . 'worlds/' . $worldName;
        $templatePath = $this->plugin->getDataFolder() . 'Maps/' . $worldName;

        if (!is_dir($this->plugin->getDataFolder() . 'Maps/')) {
            mkdir($this->plugin->getDataFolder() . 'Maps/', 0755, true);
        }

        if (is_dir($templatePath)) {
            try {
                Filesystem::recursiveUnlink($templatePath);
            } catch (\Exception $e) {
                $this->plugin->getLogger()->error("Failed to remove old template '$worldName': " . $e->getMessage());
                return false;
            }
        }

        try {
            $this->recursiveCopy($worldsPath, $templatePath);
        } catch (\Exception $e) {
            $this->plugin->getLogger()->error("Failed to save world '$worldName' as template: " . $e->getMessage());
            return false;
        }

        if (!in_array($worldName, $this->templateWorlds, true)) {
            $this->templateWorlds[] = $worldName;
        }

        $this->plugin->getLogger()->info("World '$worldName' saved as map template.");
        return true;
    }

    public function autoSetupSpawnPoints(string $worldName, string $templateName): void {
        $spawnConfig = $this->plugin->getConfigManager()->getSpawnConfig();
        $existingSpawns = $spawnConfig->get('spawn-points', []);

        $hasSpawns = false;
        foreach ($existingSpawns as $spawn) {
            if ($spawn['world'] === $worldName) {
                $hasSpawns = true;
                break;
            }
        }

        if ($hasSpawns) {
            return;
        }

        $templateSpawns = [];
        foreach ($existingSpawns as $spawn) {
            if ($spawn['world'] === $templateName) {
                $templateSpawns[] = array_merge($spawn, ['world' => $worldName]);
            }
        }

        if (!empty($templateSpawns)) {
            $spawnConfig->set('spawn-points', array_merge($existingSpawns, $templateSpawns));
            $spawnConfig->save();
            return;
        }

        if ($templateName === 'default' || $worldName === 'default') {
            $exactSpawns = [
                ['x' => 257.5, 'y' => 88, 'z' => 253.5, 'world' => $worldName],
                ['x' => 263.5, 'y' => 88, 'z' => 253.5, 'world' => $worldName],
                ['x' => 263.5, 'y' => 88, 'z' => 259.5, 'world' => $worldName],
                ['x' => 257.5, 'y' => 88, 'z' => 259.5, 'world' => $worldName],
                ['x' => 251.5, 'y' => 88, 'z' => 259.5, 'world' => $worldName],
                ['x' => 251.5, 'y' => 88, 'z' => 253.5, 'world' => $worldName],
                ['x' => 257.5, 'y' => 88, 'z' => 247.5, 'world' => $worldName],
                ['x' => 263.5, 'y' => 88, 'z' => 247.5, 'world' => $worldName],
                ['x' => 269.5, 'y' => 88, 'z' => 253.5, 'world' => $worldName],
                ['x' => 269.5, 'y' => 88, 'z' => 259.5, 'world' => $worldName],
                ['x' => 263.5, 'y' => 88, 'z' => 265.5, 'world' => $worldName],
                ['x' => 257.5, 'y' => 88, 'z' => 265.5, 'world' => $worldName],
            ];
            $spawnConfig->set('spawn-points', array_merge($existingSpawns, $exactSpawns));
            $spawnConfig->save();
            return;
        }

        $wm = $this->plugin->getServer()->getWorldManager();
        $world = $wm->getWorldByName($worldName);
        if ($world === null) {
            return;
        }
        $spawnLocation = $world->getSpawnLocation();
        $defaultSpawns = [];
        for ($i = 0; $i < 12; $i++) {
            $angle = ($i / 12) * 2 * M_PI;
            $x = $spawnLocation->getX() + 5 * cos($angle);
            $z = $spawnLocation->getZ() + 5 * sin($angle);
            $safeY = $world->getHighestBlockAt((int) $x, (int) $z) + 1;
            $defaultSpawns[] = [
                'x' => $x + 0.5,
                'y' => $safeY > 0 ? $safeY : $spawnLocation->getY(),
                'z' => $z + 0.5,
                'world' => $worldName,
            ];
        }
        $spawnConfig->set('spawn-points', array_merge($existingSpawns, $defaultSpawns));
        $spawnConfig->save();
    }

    public function resetWorld(string $instanceName): bool {
        if (isset($this->resettingWorlds[$instanceName])) {
            return true;
        }

        $this->resettingWorlds[$instanceName] = true;

        $templateName = $this->plugin->getGameManager()->getInstanceTemplateName($instanceName);
        $mapsDataPath = $this->plugin->getDataFolder() . 'Maps/' . $templateName;
        $worldsPath = $this->plugin->getServer()->getDataPath() . 'worlds/' . $instanceName;

        if (!is_dir($mapsDataPath)) {
            $this->plugin->getLogger()->warning("Template '$templateName' not found for instance '$instanceName'.");
            unset($this->resettingWorlds[$instanceName]);
            return false;
        }

        $wm = $this->plugin->getServer()->getWorldManager();
        if ($wm->isWorldLoaded($instanceName)) {
            $world = $wm->getWorldByName($instanceName);
            if ($world !== null) {
                foreach ($world->getPlayers() as $player) {
                    $this->plugin->getGameManager()->teleportToLobby($player);
                }
                $wm->unloadWorld($world, true);

                $this->plugin->getScheduler()->scheduleDelayedTask(
                    new ClosureTask(fn() => $this->doReset($instanceName, $templateName, $mapsDataPath, $worldsPath, $wm)),
                    10
                );
                return true;
            }
        }

        $this->doReset($instanceName, $templateName, $mapsDataPath, $worldsPath, $wm);
        return true;
    }

    private function doReset(string $instanceName, string $templateName, string $mapsDataPath, string $worldsPath, WorldManager $wm): void {
        if (is_dir($worldsPath)) {
            try {
                Filesystem::recursiveUnlink($worldsPath);
            } catch (\Exception $e) {
                $this->plugin->getLogger()->error('Failed to delete world: ' . $e->getMessage());
                unset($this->resettingWorlds[$instanceName]);
                return;
            }
        }
        try {
            $this->recursiveCopy($mapsDataPath, $worldsPath);
        } catch (\Exception $e) {
            $this->plugin->getLogger()->error('Failed to copy template: ' . $e->getMessage());
            unset($this->resettingWorlds[$instanceName]);
            return;
        }
        if ($wm->loadWorld($instanceName)) {
            $this->autoSetupSpawnPoints($instanceName, $templateName);
            $this->plugin->getNPCManager()->spawnNPCsInWorld($instanceName);
        }
        unset($this->resettingWorlds[$instanceName]);
    }

    public function deleteInstance(string $instanceName): void {
        $worldsPath = $this->plugin->getServer()->getDataPath() . 'worlds/' . $instanceName;
        $wm = $this->plugin->getServer()->getWorldManager();

        if ($wm->isWorldLoaded($instanceName)) {
            $world = $wm->getWorldByName($instanceName);
            if ($world !== null) {
                $lobbyName = $this->plugin->getLobbyManager()->getLobbyWorldName();
                $lobbyWorld = $wm->getWorldByName($lobbyName) ?? $wm->getDefaultWorld();
                foreach ($world->getPlayers() as $player) {
                    if ($lobbyWorld !== null) {
                        $player->teleport($lobbyWorld->getSpawnLocation());
                    }
                }
                $wm->unloadWorld($world, true);
            }
        }

        if (is_dir($worldsPath)) {
            try {
                \pocketmine\utils\Filesystem::recursiveUnlink($worldsPath);
            } catch (\Exception $e) {
                $this->plugin->getLogger()->error("Failed to delete instance '$instanceName': " . $e->getMessage());
            }
        }
    }

    public function isTemplate(string $worldName): bool {
        return in_array($worldName, $this->templateWorlds, true);
    }

    public function recursiveCopy(string $source, string $dest): void {
        if (!is_dir($source) && !is_file($source)) {
            throw new \Exception('Source path does not exist: ' . $source);
        }
        if (is_dir($source)) {
            if (!is_dir($dest)) {
                mkdir($dest, 0755, true);
            }
            foreach (scandir($source) as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $this->recursiveCopy("$source/$file", "$dest/$file");
            }
        } elseif (is_file($source)) {
            if (str_contains($source, '.lock') || str_contains($source, '.log')) {
                return;
            }
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                try {
                    if (copy($source, $dest)) {
                        return;
                    }
                } catch (\Exception $e) {
                    if ($attempt === 3) {
                        throw new \Exception("Failed to copy $source after 3 attempts: " . $e->getMessage());
                    }
                    usleep(100000);
                }
            }
        }
    }
}
