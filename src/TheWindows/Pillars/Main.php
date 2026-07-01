<?php
declare(strict_types=1);
namespace TheWindows\Pillars;

use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Human;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;
use TheWindows\Pillars\Commands\GameCommand;
use TheWindows\Pillars\Database\DatabaseProvider;
use TheWindows\Pillars\Database\JsonDatabaseProvider;
use TheWindows\Pillars\Database\MySQLDatabaseProvider;
use TheWindows\Pillars\Entity\PillarsNPC;
use TheWindows\Pillars\Listeners\LobbyListener;
use TheWindows\Pillars\Listeners\PlayerDamageListener;
use TheWindows\Pillars\Listeners\PlayerGameListener;
use TheWindows\Pillars\Listeners\PlayerQuitListener;
use TheWindows\Pillars\Lobby\LobbyManager;
use TheWindows\Pillars\Managers\ConfigManager;
use TheWindows\Pillars\Managers\GameManager;
use TheWindows\Pillars\Managers\MapManager;
use TheWindows\Pillars\Managers\NPCManager;
use TheWindows\Pillars\Managers\ScoreboardManager;
use TheWindows\Pillars\Managers\SpawnManager;
use TheWindows\Pillars\Session\SessionManager;
use TheWindows\Pillars\Tasks\ScoreboardTask;

class Main extends PluginBase {

    use SingletonTrait{
        make as private;
        reset as protected;
        setInstance as private;
    }

    private ConfigManager $configManager;
    private DatabaseProvider $databaseProvider;
    private SessionManager $sessionManager;
    private LobbyManager $lobbyManager;
    private MapManager $mapManager;
    private SpawnManager $spawnManager;
    private GameManager $gameManager;
    private NPCManager $npcManager;
    private ScoreboardManager $scoreboardManager;

    protected function onLoad(): void {
        self::setInstance($this);
    }

    protected function onEnable(): void {
        $this->saveDefaultConfig();
        $this->saveResource('config.yml', false);

        $this->initManagers();
        $this->registerEntity();
        $this->registerListeners();
        $this->registerCommands();
        $this->registerTasks();

        $this->getLogger()->info('Pillars of Fortune enabled successfully.');
    }

    protected function onDisable(): void {
        $this->cleanupGames();
        $this->cleanupSessions();
        $this->cleanupNPCs();
        $this->cleanupDatabase();
        $this->cleanupConfig();

        $this->getLogger()->info('Pillars of Fortune disabled.');
    }

    
    private function initManagers(): void {
        $this->configManager = new ConfigManager($this);
        $this->databaseProvider = $this->buildDatabaseProvider();
        $this->sessionManager = new SessionManager($this->databaseProvider);

        $this->lobbyManager = new LobbyManager($this);
        $this->lobbyManager->loadLobbyWorld();

        $this->mapManager = new MapManager($this, $this->getFile());
        $this->mapManager->setupTemplateWorlds();

        $this->spawnManager = new SpawnManager($this);
        $this->gameManager = new GameManager($this);
        $this->scoreboardManager = new ScoreboardManager($this);
        $this->npcManager = new NPCManager($this);
    }

    private function registerEntity(): void {
        EntityFactory::getInstance()->register(
            PillarsNPC::class,
            function (World $world, CompoundTag $nbt): PillarsNPC {
                return new PillarsNPC(
                    EntityDataHelper::parseLocation($nbt, $world),
                    Human::parseSkinNBT($nbt),
                    $nbt
                );
            },
            ['PillarsNPC']
        );
    }

    private function registerListeners(): void {
        $pm = $this->getServer()->getPluginManager();
        $pm->registerEvents(new LobbyListener($this), $this);
        $pm->registerEvents(new PlayerGameListener($this), $this);
        $pm->registerEvents(new PlayerDamageListener($this), $this);
        $pm->registerEvents(new PlayerQuitListener($this), $this);
    }

    private function registerCommands(): void {
        $this->getServer()->getCommandMap()->register('pillars', new GameCommand($this));
    }

    private function registerTasks(): void {
        $sbCfg = $this->getConfig()->get('scoreboard', []);

        if (!(bool) ($sbCfg['enabled'] ?? true)) {
            return;
        }

        $interval = max(1, (int) ($sbCfg['update-interval'] ?? 20));
        $this->getScheduler()->scheduleRepeatingTask(new ScoreboardTask($this), $interval);
    }

    
    private function cleanupGames(): void {
        if (!isset($this->gameManager, $this->mapManager)) return;

        foreach (array_keys($this->gameManager->getAllGames()) as $gameId) {
            $this->mapManager->deleteInstance($gameId);
        }
    }

    private function cleanupSessions(): void {
        if (!isset($this->sessionManager)) return;
        $this->sessionManager->saveAll($this->getServer()->getOnlinePlayers());
    }

    private function cleanupNPCs(): void {
        if (!isset($this->npcManager)) return;
        $this->npcManager->cleanup();
    }

    private function cleanupDatabase(): void {
        if (!isset($this->databaseProvider)) return;
        $this->databaseProvider->close();
    }

    private function cleanupConfig(): void {
        if (!isset($this->configManager)) return;
        $this->configManager->saveAll();
    }

    private function buildDatabaseProvider(): DatabaseProvider {
        $dbConfig = $this->getConfig()->get('database', []);
        $provider = strtolower((string) ($dbConfig['provider'] ?? 'json'));

        if ($provider === 'mysql') {
            $mysql = $dbConfig['mysql'] ?? [];
            try {
                return new MySQLDatabaseProvider(
                    (string) ($mysql['host']     ?? '127.0.0.1'),
                    (int)    ($mysql['port']     ?? 3306),
                    (string) ($mysql['user']     ?? 'root'),
                    (string) ($mysql['password'] ?? ''),
                    (string) ($mysql['database'] ?? 'pillars')
                );
            } catch (\Exception $e) {
                $this->getLogger()->warning(
                    'MySQL connection failed, falling back to JSON: ' . $e->getMessage()
                );
            }
        }

        return new JsonDatabaseProvider($this->getDataFolder());
    }

    public function getConfigManager(): ConfigManager { return $this->configManager; }
    public function getSessionManager(): SessionManager { return $this->sessionManager; }
    public function getLobbyManager(): LobbyManager { return $this->lobbyManager; }
    public function getMapManager(): MapManager { return $this->mapManager; }
    public function getSpawnManager(): SpawnManager { return $this->spawnManager; }
    public function getGameManager(): GameManager { return $this->gameManager; }
    public function getNPCManager(): NPCManager { return $this->npcManager; }
    public function getScoreboardManager(): ScoreboardManager { return $this->scoreboardManager; }
}