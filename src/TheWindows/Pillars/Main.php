<?php
namespace TheWindows\Pillars;

use pocketmine\plugin\PluginBase;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;
use TheWindows\Pillars\Entity\PillarsNPC;
use TheWindows\Pillars\Managers\GameManager;
use TheWindows\Pillars\Managers\SpawnManager;
use TheWindows\Pillars\Managers\NPCManager;
use TheWindows\Pillars\Managers\ConfigManager;
use TheWindows\Pillars\Managers\WorldManager;
use TheWindows\Pillars\Managers\ScoreHUDManager;
use TheWindows\Pillars\Managers\MapManager;
use TheWindows\Pillars\Commands\MainCommand;
use TheWindows\Pillars\Events\PlayerInteractListener;
use TheWindows\Pillars\Events\PlayerJoinListener;
use TheWindows\Pillars\Events\PlayerQuitListener;
use TheWindows\Pillars\Events\PlayerDeathListener;
use TheWindows\Pillars\Events\PlayerDamageListener;

class Main extends PluginBase {
    
    private static $instance;
    private $gameManager;
    private $spawnManager;
    private $npcManager;
    private $configManager;
    private $worldManager;
    private $scoreHUDManager;
    private $playerDeathListener;
    private $mapManager;
    
    public static function getInstance(): Main {
        return self::$instance;
    }
    
    public function onEnable(): void {
        self::$instance = $this;
        
        
        $this->saveDefaultConfig();
        $this->saveResource("spawnpoints.yml");
        $this->saveResource("maps.yml");
        $this->saveResource("npcs.yml");
        
        $this->configManager = new ConfigManager($this);
        $this->mapManager = new MapManager($this, $this->getFile());
        $this->worldManager = new WorldManager($this);
        $this->spawnManager = new SpawnManager($this);
        $this->npcManager = new NPCManager($this);
        $this->gameManager = new GameManager($this);
        $this->scoreHUDManager = new ScoreHUDManager($this);
        
        $this->getMapManager()->setupTemplateWorlds();
        
        
        $arenaWorlds = $this->configManager->getArenaWorlds();
        foreach ($arenaWorlds as $worldName) {
            $this->mapManager->resetWorld($worldName);
            $this->getLogger()->info("Reset arena world: " . $worldName);
        }
        
        $this->getWorldManager()->loadArenaWorlds();
        
        EntityFactory::getInstance()->register(PillarsNPC::class, function(World $world, CompoundTag $nbt): PillarsNPC {
            return new PillarsNPC(EntityDataHelper::parseLocation($nbt, $world), PillarsNPC::parseSkinNBT($nbt), $nbt);
        }, ['PillarsNPC']);
        
        $this->getServer()->getCommandMap()->register("pillars", new MainCommand($this));
        
        $this->playerDeathListener = new PlayerDeathListener($this);
        $this->getServer()->getPluginManager()->registerEvents(new PlayerDamageListener($this), $this);
        $this->getServer()->getPluginManager()->registerEvents($this->playerDeathListener, $this);
        $this->getServer()->getPluginManager()->registerEvents(new PlayerInteractListener($this), $this);
        $this->getServer()->getPluginManager()->registerEvents(new PlayerJoinListener($this), $this);
        $this->getServer()->getPluginManager()->registerEvents(new PlayerQuitListener($this), $this);
        
        $this->getScheduler()->scheduleRepeatingTask(new class($this) extends \pocketmine\scheduler\Task {
            private $plugin;
            
            public function __construct(Main $plugin) {
                $this->plugin = $plugin;
            }
            
            public function onRun(): void {
                foreach($this->plugin->getServer()->getOnlinePlayers() as $player) {
                    if($player->isOnline()) {
                        $device = $this->getPlayerDevice($player);
                        $health = round($player->getHealth());
                        $maxHealth = $player->getMaxHealth();
                        $player->setNameTag("§f{$player->getName()}\n§7{$device} | §c{$health}/{$maxHealth}❤");
                    }
                }
            }
            
            private function getPlayerDevice(\pocketmine\player\Player $player): string {
                $info = $player->getPlayerInfo();
                $extraData = $info->getExtraData();
                
                if(isset($extraData["DeviceOS"])) {
                    $deviceOS = $extraData["DeviceOS"];
                    switch($deviceOS) {
                        case 1: return "Android";
                        case 2: return "iOS";
                        case 3: return "macOS";
                        case 4: return "FireOS";
                        case 5: return "GearVR";
                        case 6: return "HoloLens";
                        case 7: return "Windows 10";
                        case 8: return "Windows";
                        case 9: return "Dedicated";
                        case 10: return "tvOS";
                        case 11: return "PlayStation";
                        case 12: return "Nintendo Switch";
                        case 13: return "Xbox";
                        case 14: return "Windows Phone";
                        default: return "Unknown";
                    }
                }
                return "Unknown";
            }
        }, 20);
        
        $this->worldManager->loadArenaWorlds();
        
        $this->getLogger()->info("Pillars Minigame enabled successfully!");
    }
    
    public function onDisable(): void {
        $this->configManager->saveAll();
        $this->npcManager->cleanup();
        $this->getLogger()->info("Pillars Minigame disabled!");
    }
    
    public function getGameManager(): GameManager {
        return $this->gameManager;
    }
    
    public function getSpawnManager(): SpawnManager {
        return $this->spawnManager;
    }
    
    public function getNPCManager(): NPCManager {
        return $this->npcManager;
    }
    
    public function getConfigManager(): ConfigManager {
        return $this->configManager;
    }
    
    public function getWorldManager(): WorldManager {
        return $this->worldManager;
    }
    
    public function getScoreHUDManager(): ScoreHUDManager {
        return $this->scoreHUDManager;
    }
    
    public function getPlayerDeathListener(): PlayerDeathListener {
        return $this->playerDeathListener;
    }
    
    public function getMapManager(): MapManager {
        return $this->mapManager;
    }  
}