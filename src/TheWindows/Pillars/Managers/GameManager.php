<?php
namespace TheWindows\Pillars\Managers;

use pocketmine\player\Player;
use pocketmine\block\utils\DyeColor;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\utils\TextFormat;
use pocketmine\item\VanillaItems;
use pocketmine\block\VanillaBlocks;
use TheWindows\Pillars\Main;
use TheWindows\Pillars\Tasks\CountdownTask;
use TheWindows\Pillars\Tasks\ItemDistributionTask;
use TheWindows\Pillars\Forms\SpectatorForm;
use TheWindows\Pillars\Forms\MarketForm;



class GameManager {
    
    private $plugin;
    private $games = [];
    private $waitingPlayers = [];
    private $activeGames = [];
    private $playerCheckpoints = [];
    private $countdownTasks = [];
    private $persistentActionBars = [];
    private $spectators = [];
    private $playerStats = []; 
    private $gameStats = []; 
    private $marketItems = []; 
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->loadGames();
        $this->loadPlayerStats();
        
        
        $this->plugin->getScheduler()->scheduleRepeatingTask(new class($this) extends \pocketmine\scheduler\Task {
            private $gameManager;
            
            public function __construct(GameManager $gameManager) {
                $this->gameManager = $gameManager;
            }
            
            public function onRun(): void {
                $this->gameManager->updatePersistentActionBars();
                $this->gameManager->ensureMarketItem();
            }
        }, 20);
    }
    
   private function loadPlayerStats(): void {
    $path = $this->plugin->getDataFolder() . "player_stats.json";
    if(file_exists($path)) {
        $data = json_decode(file_get_contents($path), true);
        if(is_array($data)) {
            $this->playerStats = $data;
        }
    }
}

public function getPlayerStats(Player $player): array {
    $username = strtolower($player->getName());
    if(!isset($this->playerStats[$username])) {
        $this->playerStats[$username] = [
            'wins' => 0,
            'coins' => 0
        ];
    }
    return $this->playerStats[$username];
}
    
    private function savePlayerStats(): void {
        $path = $this->plugin->getDataFolder() . "player_stats.json";
        file_put_contents($path, json_encode($this->playerStats, JSON_PRETTY_PRINT));
    }
    
    
    public function addCoins(Player $player, int $amount): void {
        $username = strtolower($player->getName());
        if(!isset($this->playerStats[$username])) {
            $this->playerStats[$username] = [
                'kills' => 0,
                'assists' => 0,
                'wins' => 0,
                'coins' => 0
            ];
        }
        $this->playerStats[$username]['coins'] += $amount;
        $this->savePlayerStats();
        
        
        if($player->isOnline()) {
            $this->plugin->getScoreHUDManager()->updatePlayerStats($player);
        }
    }
    
public function addWin(Player $player): void {
    $username = strtolower($player->getName());
    if(!isset($this->playerStats[$username])) {
        $this->playerStats[$username] = [
            'wins' => 0,
            'coins' => 0
        ];
    }
    
    
    $oldWins = $this->playerStats[$username]['wins'];
    
    $this->playerStats[$username]['wins']++;
    $this->addCoins($player, 20); 
    
    
    $this->savePlayerStats();

    
    if($player->isOnline()) {
        $player->sendMessage("§6+20 coins for winning!");
        $player->sendMessage("§a+1 win!");
    }
}
   public function addPlayTimeCoins(Player $player, string $gameId): void {
    
    $coinsEarned = 5;
    $this->addCoins($player, $coinsEarned);
    
    if($player->isOnline()) {
        $player->sendMessage("§a+{$coinsEarned} coins for participating!");
    }
    
}
    private function loadGames(): void {
    $arenaWorlds = $this->plugin->getConfigManager()->getArenaWorlds();
    $loadedCount = 0;
    
    foreach($arenaWorlds as $worldName) {
        
        if(!$this->plugin->getServer()->getWorldManager()->isWorldLoaded($worldName)) {
            
            if(!$this->plugin->getServer()->getWorldManager()->loadWorld($worldName)) {
                $this->plugin->getMapManager()->resetWorld($worldName);
            }
        }
        
        
        $maxPlayers = $this->plugin->getConfigManager()->getMapSetting($worldName, "max-players", 12);
        $minPlayers = $this->plugin->getConfigManager()->getMapSetting($worldName, "min-players", 2);
        $gameTime = 1200; 
        $countdownTime = $this->plugin->getConfigManager()->getMapSetting($worldName, "countdown-time", 30);
        $itemInterval = $this->plugin->getConfigManager()->getMapSetting($worldName, "item-interval", 600);
        
        $this->games[$worldName] = [
            'world' => $worldName,
            'players' => [],
            'status' => 'waiting',
            'max_players' => $maxPlayers,
            'min_players' => $minPlayers,
            'game_time' => $gameTime,
            'countdown_time' => $countdownTime,
            'item_interval' => $itemInterval
        ];
        $loadedCount++;
    }
    
    $this->plugin->getLogger()->info("Successfully loaded " . $loadedCount . "/" . count($arenaWorlds) . " games");
}
    
   public function createGame(string $templateWorld, int $maxPlayers, int $minPlayers, int $gameTime, int $countdownTime, int $itemInterval): bool {
    if(isset($this->games[$templateWorld])) {
        return false;
    }
    
    if(!$this->plugin->getServer()->getWorldManager()->isWorldLoaded($templateWorld)) {
        $this->plugin->getServer()->getWorldManager()->loadWorld($templateWorld);
    }
    
    $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($templateWorld);
    if($world === null) {
        return false;
    }
    
    $maxPlayers = max(2, min(24, $maxPlayers));
    $minPlayers = max(2, min($maxPlayers, $minPlayers));
    $gameTime = 1200; 
    $countdownTime = max(5, min(60, $countdownTime));
    $itemInterval = max(10, min(300, $itemInterval)) * 20;
    
    $this->games[$templateWorld] = [
        'world' => $templateWorld,
        'players' => [],
        'status' => 'waiting',
        'max_players' => $maxPlayers,
        'min_players' => $minPlayers,
        'game_time' => $gameTime,
        'countdown_time' => $countdownTime,
        'item_interval' => $itemInterval
    ];
    
    $configManager = $this->plugin->getConfigManager();
    $configManager->addArenaWorld($templateWorld);
    $configManager->setMapSetting($templateWorld, "max-players", $maxPlayers);
    $configManager->setMapSetting($templateWorld, "min-players", $minPlayers);
    $configManager->setMapSetting($templateWorld, "game-time", $gameTime);
    $configManager->setMapSetting($templateWorld, "countdown-time", $countdownTime);
    $configManager->setMapSetting($templateWorld, "item-interval", $itemInterval);
    
    $this->plugin->getLogger()->info("Created new game for world: " . $templateWorld . " (Max players: " . $maxPlayers . ")");
    return true;
}
    
    public function gameExists(string $worldName): bool {
        return isset($this->games[$worldName]);
    }
    
    public function removeGame(string $gameId): bool {
        if(isset($this->games[$gameId])) {
            if(isset($this->activeGames[$gameId])) {
                $this->endGame($gameId);
            }
            
            unset($this->games[$gameId]);
            
            $this->plugin->getConfigManager()->removeArenaWorld($gameId);
            $this->plugin->getSpawnManager()->clearSpawnPointsForWorld($gameId);
            
            if(isset($this->persistentActionBars[$gameId])) {
                unset($this->persistentActionBars[$gameId]);
            }
            
            return true;
        }
        return false;
    }

private function startGameTimer(string $gameId): void {
    $gameTime = 1200; 
    $remainingTime = $gameTime;
    
    $task = new class($this, $gameId, $remainingTime) extends \pocketmine\scheduler\Task {
        private $gameManager;
        private $gameId;
        private $remainingTime;
        private $announcedTimes = [];
        
        public function __construct(GameManager $gameManager, string $gameId, int $remainingTime) {
            $this->gameManager = $gameManager;
            $this->gameId = $gameId;
            $this->remainingTime = $remainingTime;
        }
        
        public function onRun(): void {
            if($this->remainingTime <= 0) {
                $this->gameManager->endGameDueToTime($this->gameId);
                $this->getHandler()?->cancel();
                return;
            }
            
            
            $announceTimes = [60, 30, 15, 10, 5, 3, 2, 1];
            
            if(in_array($this->remainingTime, $announceTimes) && !isset($this->announcedTimes[$this->remainingTime])) {
                $timeUnit = $this->remainingTime >= 60 ? "MINUTES" : "SECONDS";
                $timeValue = $this->remainingTime >= 60 ? floor($this->remainingTime / 60) : $this->remainingTime;
                
                $message = "§c§l" . $timeValue . " " . $timeUnit . ($timeValue > 1 ? "" : "") . " LEFT! §r§cHURRY UP!";
                $this->gameManager->broadcastToGame($this->gameId, $message);
                
                $this->announcedTimes[$this->remainingTime] = true;
            }
            
            $this->remainingTime--;
        }
    };
    
    $this->plugin->getScheduler()->scheduleRepeatingTask($task, 20);
}

    public function endGameDueToTime(string $gameId): void {
    if(!isset($this->activeGames[$gameId])) return;
    
    $players = $this->activeGames[$gameId]['players'];
    
    foreach($players as $player) {
        if($player->isOnline()) {
            $player->sendTitle("§6TIME'S UP!", "§7No one won this round", 10, 40, 10);
        }
    }
    
    $allPlayers = array_merge($players, $this->spectators[$gameId] ?? []);
    foreach($allPlayers as $player) {
        if($player->isOnline()) {
            $this->plugin->getScoreHUDManager()->updateLobbyScoreboard($player);
        }
    }
    
    $this->endGame($gameId);
}

   public function setPlayerSpectator(Player $player, string $gameId): void {
        $player->setGamemode(\pocketmine\player\GameMode::SPECTATOR());
        $player->setInvisible(true);
        $player->setSilent(true);
        $player->setAllowFlight(true);
        $player->setFlying(true);
        $player->setNoClientPredictions(false);
        $player->getEffects()->clear();
        
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
        
        $leaveGameItem = VanillaBlocks::BED()->setColor(DyeColor::RED())->asItem()->setCustomName("§cLeave Game");
        $spectateItem = VanillaItems::COMPASS()->setCustomName("§bSpectate Players");
        
        $inventory = $player->getInventory();
        $inventory->setItem(0, $leaveGameItem);
        $inventory->setItem(8, $spectateItem);
        
        $player->setHealth($player->getMaxHealth());
        $player->getHungerManager()->setEnabled(false);
        $player->getHungerManager()->setFood(20);
        $player->getHungerManager()->setSaturation(20);
        
        if (!isset($this->spectators[$gameId])) {
            $this->spectators[$gameId] = [];
        }
        $this->spectators[$gameId][] = $player;
        
        $player->sendMessage("§7You are now spectating. Fly around to watch the game. Use the compass to teleport to players or bed to leave.");
        
        
        $this->plugin->getScheduler()->scheduleRepeatingTask(new class($player, $this->plugin, $leaveGameItem, $spectateItem) extends \pocketmine\scheduler\Task {
            private $player;
            private $plugin;
            private $leaveGameItem;
            private $spectateItem;
            
            public function __construct(Player $player, Main $plugin, \pocketmine\item\Item $leaveGameItem, \pocketmine\item\Item $spectateItem) {
                $this->player = $player;
                $this->plugin = $plugin;
                $this->leaveGameItem = $leaveGameItem;
                $this->spectateItem = $spectateItem;
            }
            
            public function onRun(): void {
                if (!$this->player->isOnline() || $this->plugin->getGameManager()->checkPlayerState($this->player) !== 'spectating') {
                    $this->getHandler()->cancel();
                    return;
                }
                
                $inventory = $this->player->getInventory();
                $itemInSlot0 = $inventory->getItem(0);
                $itemInSlot8 = $inventory->getItem(8);
                
                if (!$itemInSlot0->equals($this->leaveGameItem, true, true) || $itemInSlot0->getCustomName() !== "§cLeave Game") {
                    $inventory->setItem(0, $this->leaveGameItem);

                }
                if (!$itemInSlot8->equals($this->spectateItem, true, true) || $itemInSlot8->getCustomName() !== "§bSpectate Players") {
                    $inventory->setItem(8, $this->spectateItem);
                }
            }
        }, 20); 
    }

public function addPlayerToQueue(Player $player, string $gameId): void {
    if (!isset($this->waitingPlayers[$gameId])) {
        $this->waitingPlayers[$gameId] = [];
    }

    
    if (isset($this->countdownTasks[$gameId])) {
        $player->sendMessage("§eGame is already starting! You'll join as spectator if you die.");
        return;
    }

    $this->preparePlayerForQueue($player);

    $playerName = strtolower($player->getName());
    $spawnPoints = $this->plugin->getSpawnManager()->getSpawnPointsForWorld($gameId);
    
    
    if (!isset($this->playerCheckpoints[$gameId])) {
        $this->playerCheckpoints[$gameId] = [];
    }

    $assignedSpawnPoint = null;
    $assignedIndex = null;

    
    if (isset($this->playerCheckpoints[$gameId][$playerName])) {
        $reservedIndex = $this->playerCheckpoints[$gameId][$playerName];
        
        if (isset($spawnPoints[$reservedIndex])) {
            $isOccupied = false;
            foreach ($this->games[$gameId]['players'] ?? [] as $p) {
                $pName = strtolower($p->getName());
                if (isset($this->playerCheckpoints[$gameId][$pName]) && 
                    $this->playerCheckpoints[$gameId][$pName] === $reservedIndex) {
                    $isOccupied = true;
                    break;
                }
            }
            
            if (!$isOccupied) {
                $assignedSpawnPoint = $spawnPoints[$reservedIndex];
                $assignedIndex = $reservedIndex;
            }
        }
    }

    
    if ($assignedSpawnPoint === null) {
        $usedSpawnIndices = [];
        foreach ($this->games[$gameId]['players'] ?? [] as $p) {
            $pName = strtolower($p->getName());
            if (isset($this->playerCheckpoints[$gameId][$pName])) {
                $usedSpawnIndices[] = $this->playerCheckpoints[$gameId][$pName];
            }
        }
        
        
        for ($i = 0; $i < count($spawnPoints); $i++) {
            if (!in_array($i, $usedSpawnIndices, true)) {
                $assignedSpawnPoint = $spawnPoints[$i];
                $assignedIndex = $i;
                break;
            }
        }
    }

    
    if ($assignedSpawnPoint === null) {
        $player->sendMessage("§cGame is full! You'll join as spectator.");
        $this->setPlayerSpectator($player, $gameId);
        return;
    }

    
    $this->playerCheckpoints[$gameId][$playerName] = $assignedIndex;
    $this->waitingPlayers[$gameId][] = $player;

    
    $player->teleport($assignedSpawnPoint);

    
        $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($gameId);
    $maxPlayers = $this->getMapSetting($gameId, "max_players", 12);

    if ($world !== null) {
        $worldPlayers = array_filter($world->getPlayers(), function ($p) {
            return $p->isOnline() && $p->getGamemode()->equals(\pocketmine\player\GameMode::ADVENTURE());
        });
        $currentPlayerCount = count($worldPlayers);

        
        $minPlayers = $this->getMapSetting($gameId, "min_players", 2);
        if ($currentPlayerCount < $minPlayers) {
            $this->setPersistentActionBar($gameId, "§cWaiting for players... (§6{$currentPlayerCount}/{$maxPlayers}§c)");
        } else {
            $this->clearPersistentActionBar($gameId);
        }

        
        if (isset($this->persistentActionBars[$gameId])) {
            $player->sendActionBarMessage($this->persistentActionBars[$gameId]);
            $this->sendActionBarToGame($gameId, $this->persistentActionBars[$gameId]);
        }

        
        $this->plugin->getScheduler()->scheduleDelayedTask(new class($player, $this->persistentActionBars[$gameId] ?? "") extends \pocketmine\scheduler\Task {
            private $player;
            private $message;

            public function __construct(Player $player, string $message) {
                $this->player = $player;
                $this->message = $message;
            }

            public function onRun(): void {
                if ($this->player->isOnline()) {
                    $this->player->sendActionBarMessage($this->message);
                }
            }
        }, 1);
    }

    
    $currentCount = $world !== null ? count(array_filter($world->getPlayers(), function ($p) {
        return $p->isOnline() && $p->getGamemode()->equals(\pocketmine\player\GameMode::ADVENTURE());
    })) : count($this->waitingPlayers[$gameId]);

    $this->broadcastToGame($gameId, "§a{$player->getName()} joined the queue! (§6{$currentCount}/{$maxPlayers}§a)");

    foreach ($this->waitingPlayers[$gameId] as $waitingPlayer) {
        if ($waitingPlayer->isOnline()) {
            $waitingPlayer->sendTitle("§8Waiting...", "§7{$currentCount}/{$maxPlayers} players", 0, 40, 0);
        }
    }

    $minPlayers = $this->getMapSetting($gameId, "min_players", 2);
    if ($currentCount >= $minPlayers && !isset($this->countdownTasks[$gameId])) {
        $this->startCountdown($gameId);
    }
}
    
    public function sendActionBarToGame(string $gameId, string $message): void {
        $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($gameId);
        if($world !== null) {
            foreach($world->getPlayers() as $player) {
                if($player->isOnline()) {
                    $player->sendActionBarMessage($message);
                }
            }
        }
    }
private function getWorldSpawnLocation(string $gameId): ?\pocketmine\world\Position {
    $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($gameId);
    if ($world !== null) {
        return $world->getSpawnLocation();
    }
    return null;
}
    public function setPersistentActionBar(string $gameId, string $message): void {
        
        $this->sendActionBarToGame($gameId, "");
        $this->persistentActionBars[$gameId] = $message;
        
        $this->sendActionBarToGame($gameId, $message);
    }
    
    public function clearPersistentActionBar(string $gameId): void {
        if(isset($this->persistentActionBars[$gameId])) {
            $this->sendActionBarToGame($gameId, "");
            unset($this->persistentActionBars[$gameId]);
        }
    }

    public function updatePersistentActionBars(): void {
        foreach($this->persistentActionBars as $gameId => $message) {
            $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($gameId);
            if($world !== null) {
                
                $minPlayers = $this->getMapSetting($gameId, "min_players", 2);
                $currentPlayers = array_filter($world->getPlayers(), function($p) {
                    return $p->isOnline() && $p->getGamemode()->equals(\pocketmine\player\GameMode::ADVENTURE());
                });
                if(count($currentPlayers) < $minPlayers) {
                    foreach($world->getPlayers() as $player) {
                        if($player->isOnline()) {
                            $player->sendActionBarMessage($this->persistentActionBars[$gameId]);
                        }
                    }
                }
            }
        }
    }

    private function preparePlayerForQueue(Player $player): void {
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
        
        $player->setGamemode(\pocketmine\player\GameMode::ADVENTURE());
        $player->setNoClientPredictions(true);
        
        
        $player->getHungerManager()->setFood(20);
        $player->getHungerManager()->setSaturation(20);
        $player->getHungerManager()->setEnabled(false);
        
        $player->sendMessage("§8You are now in queue. Please wait...");
    }

        private function resetPlayer(Player $player): void {
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
        $player->setGamemode(\pocketmine\player\GameMode::SURVIVAL());
        $player->setNoClientPredictions(false);
        $player->setHealth($player->getMaxHealth());
        $player->getEffects()->clear();
        $player->setInvisible(false);
        $player->setSilent(false);
        
        $player->setAllowFlight(false);
        $player->setFlying(false);
        
        $player->getHungerManager()->setEnabled(true);
        $player->getHungerManager()->setFood(20);
        $player->getHungerManager()->setSaturation(20);
        
        if ($player->getWorld()->getFolderName() === "world" && $this->checkPlayerState($player) === null) {
            $this->giveMarketItem($player);
        }
    }
    
public function getAvailableGames(): array {
    $availableGames = [];
    
    foreach($this->games as $gameId => $gameData) {
        $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($gameId);
        $playerCount = $world !== null ? count($world->getPlayers()) : 0;
        $maxPlayers = $gameData['max_players']; 
        
        $status = $gameData['status'];
        $statusColor = "§e";
        if ($status === 'playing') {
            $statusColor = "§c";
        }
        
        $availableGames[] = [
            'id' => $gameId,
            'world' => $gameId,
            'players' => $playerCount,
            'max_players' => $maxPlayers, 
            'status' => $statusColor . ucfirst($status)
        ];
    }
    
    return $availableGames;
}
    

public function addPlayerToGame(Player $player, string $gameId): void {
    if (isset($this->games[$gameId]) && $this->games[$gameId]['status'] === 'playing') {
        $player->sendMessage("§cThis game is already in progress! You cannot join now.");
        return;
    }
    
    
    if (!$this->plugin->getServer()->getWorldManager()->isWorldLoaded($gameId)) {
        if (!$this->plugin->getServer()->getWorldManager()->loadWorld($gameId)) {
            $player->sendMessage("§cGame world not found or could not be loaded!");
            return;
        }
    }
    
    $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($gameId);
    if ($world === null) {
        $player->sendMessage("§cGame world not found!");
        return;
    }
    
    $maxPlayers = $this->getMapSetting($gameId, "max_players", 12);
    
    
    $currentPlayers = array_filter($world->getPlayers(), function($p) {
        return $p->isOnline() && $p->getGamemode()->equals(\pocketmine\player\GameMode::ADVENTURE());
    });
    $currentPlayerCount = count($currentPlayers);
    
    if ($currentPlayerCount >= $maxPlayers) {
        $player->sendMessage("§cThis game is full!");
        return;
    }
    
    $player->setGamemode(\pocketmine\player\GameMode::ADVENTURE());
    $player->setNoClientPredictions(true);
    $player->getEffects()->clear();
    
    
    $player->getInventory()->clearAll();
    $player->getArmorInventory()->clearAll();
    $player->getCursorInventory()->clearAll();
    
    $leaveGameItem = VanillaBlocks::BED()->setColor(DyeColor::RED())->asItem()->setCustomName("§cLeave Game");
    $player->getInventory()->setItem(0, $leaveGameItem);
    
    
    $this->plugin->getServer()->getPluginManager()->registerEvents(new class($player, $leaveGameItem, $this->plugin, $gameId) implements \pocketmine\event\Listener {
        private $player;
        private $leaveGameItem;
        private $plugin;
        private $gameId;
        
        public function __construct(Player $player, \pocketmine\item\Item $leaveGameItem, Main $plugin, string $gameId) {
            $this->player = $player;
            $this->leaveGameItem = $leaveGameItem;
            $this->plugin = $plugin;
            $this->gameId = $gameId;
        }
        
        public function onPlayerDropItem(\pocketmine\event\player\PlayerDropItemEvent $event): void {
            if ($event->getPlayer()->getId() !== $this->player->getId()) {
                return;
            }
            $item = $event->getItem();
            if ($item->equals($this->leaveGameItem, false, false) && $item->getCustomName() === "§cLeave Game") {
                $event->cancel();
            }
        }
        
        public function onInventoryTransaction(\pocketmine\event\inventory\InventoryTransactionEvent $event): void {
            $player = $event->getTransaction()->getSource();
            if ($player->getId() !== $this->player->getId()) {
                return;
            }
            
            $inventory = $player->getInventory();
            $actions = $event->getTransaction()->getActions();
            
            foreach ($actions as $action) {
                if ($action instanceof \pocketmine\inventory\transaction\action\SlotChangeAction) {
                    $item = $action->getTargetItem();
                    $sourceItem = $action->getSourceItem();
                    if ($action->getSlot() === 0 && $sourceItem->equals($this->leaveGameItem, false, false) && $sourceItem->getCustomName() === "§cLeave Game") {
                        if ($item->isNull() || $item->equals($this->leaveGameItem, false, false)) {
                            continue;
                        }
                        $event->cancel();
                        $inventory->setItem(0, $this->leaveGameItem);
                    } elseif ($item->equals($this->leaveGameItem, false, false) && $item->getCustomName() === "§cLeave Game" && $action->getSlot() !== 0) {
                        $event->cancel();
                        $inventory->setItem(0, $this->leaveGameItem);
                    }
                }
            }
        }
        
        public function onPlayerInteract(\pocketmine\event\player\PlayerInteractEvent $event): void {
            $player = $event->getPlayer();
            if ($player->getId() !== $this->player->getId()) {
                return;
            }
            
            $item = $event->getItem();
            if ($item->equals($this->leaveGameItem, false, false) && $item->getCustomName() === "§cLeave Game") {
                $lobbyWorld = $this->plugin->getServer()->getWorldManager()->getWorldByName("world");
                if ($lobbyWorld !== null) {
                    $player->teleport($lobbyWorld->getSpawnLocation());
                    $player->getInventory()->clearAll();
                    $player->getArmorInventory()->clearAll();
                    $player->getCursorInventory()->clearAll();
                    $player->setGamemode(\pocketmine\player\GameMode::SURVIVAL());
                    $player->getHungerManager()->setEnabled(true);
                    $player->getHungerManager()->setFood(20);
                    $player->getHungerManager()->setSaturation(20);
                    $player->setNoClientPredictions(false);
                    $player->sendMessage("§aYou have left the game!");
                } else {
                    $player->sendMessage("§cLobby world not found!");
                }
                
                if (isset($this->plugin->getGameManager()->games[$this->gameId]['players'])) {
                    $this->plugin->getGameManager()->games[$this->gameId]['players'] = array_filter(
                        $this->plugin->getGameManager()->games[$this->gameId]['players'],
                        fn($p) => $p->getId() !== $player->getId()
                    );
                }
                
                $event->cancel();
            }
        }
        
        public function onPlayerQuit(\pocketmine\event\player\PlayerQuitEvent $event): void {
            if ($event->getPlayer()->getId() === $this->player->getId()) {
                
            }
        }
    }, $this->plugin);
    
    
    $this->plugin->getScheduler()->scheduleRepeatingTask(new class($player, $this->plugin, $gameId, $leaveGameItem) extends \pocketmine\scheduler\Task {
        private $player;
        private $plugin;
        private $gameId;
        private $leaveGameItem;
        
        public function __construct(Player $player, Main $plugin, string $gameId, \pocketmine\item\Item $leaveGameItem) {
            $this->player = $player;
            $this->plugin = $plugin;
            $this->gameId = $gameId;
            $this->leaveGameItem = $leaveGameItem;
        }
        
        public function onRun(): void {
            if (!$this->player->isOnline() || $this->plugin->getGameManager()->checkPlayerState($this->player) !== 'playing') {
                $this->getHandler()->cancel();
                return;
            }
            
            $inventory = $this->player->getInventory();
            $itemInSlot = $inventory->getItem(0);
            if (!$itemInSlot->equals($this->leaveGameItem, false, false) || $itemInSlot->getCustomName() !== "§cLeave Game") {
                $inventory->setItem(0, $this->leaveGameItem);
            }
            
            $world = $this->player->getWorld();
            $nearbyEntities = $world->getNearbyEntities($this->player->getBoundingBox()->expandedCopy(5, 5, 5));
            foreach ($nearbyEntities as $entity) {
                if ($entity instanceof \pocketmine\entity\object\ItemEntity) {
                    $item = $entity->getItem();
                    if ($item->equals($this->leaveGameItem, false, false) && $item->getCustomName() === "§cLeave Game") {
                        $entity->flagForDespawn();
                    }
                }
            }
        }
    }, 20);
    
    $player->getHungerManager()->setFood(20);
    $player->getHungerManager()->setSaturation(20);
    $player->getHungerManager()->setEnabled(false);
    
    $spawnPoints = $this->plugin->getSpawnManager()->getSpawnPointsForWorld($gameId);

    
    if (!$this->plugin->getServer()->getWorldManager()->isWorldLoaded($gameId)) {
        $this->plugin->getServer()->getWorldManager()->loadWorld($gameId);
    }

    $playerName = strtolower($player->getName());
    $assignedSpawnPoint = null;
    $assignedIndex = null;

    
    $usedSpawnIndices = [];
    foreach ($this->games[$gameId]['players'] ?? [] as $p) {
        if ($p->isOnline()) { 
            $pName = strtolower($p->getName());
            
            if (isset($this->playerCheckpoints[$gameId][$pName])) {
                $spawnIndex = $this->playerCheckpoints[$gameId][$pName];
                
                if ($p->getWorld()->getFolderName() === $gameId) {
                    $usedSpawnIndices[] = $spawnIndex;
                }
            }
        }
    }

    
    for ($i = 0; $i < count($spawnPoints); $i++) {
        if (!in_array($i, $usedSpawnIndices, true)) {
            if (isset($spawnPoints[$i])) {
                
                $spawnPoint = $spawnPoints[$i];
                
                
                $spawnWorld = $this->plugin->getServer()->getWorldManager()->getWorldByName($gameId);
                
                if ($spawnWorld !== null) {
                    $assignedSpawnPoint = new Position(
                        $spawnPoint->getX(),
                        $spawnPoint->getY(),
                        $spawnPoint->getZ(),
                        $spawnWorld
                    );
                    $assignedIndex = $i;
                    break;
                }
            }
        }
    }

    
    if ($assignedSpawnPoint === null) {
        $worldSpawn = $this->getWorldSpawnLocation($gameId);
        if ($worldSpawn !== null) {
            
            $freshWorld = $this->plugin->getServer()->getWorldManager()->getWorldByName($gameId);
            if ($freshWorld !== null) {
                $freshSpawn = new Position(
                    $worldSpawn->getX(),
                    $worldSpawn->getY(),
                    $worldSpawn->getZ(),
                    $freshWorld
                );
                $player->teleport($freshSpawn);
            }
        }
        $assignedIndex = -1; 
    } else {
        
        $freshWorld = $this->plugin->getServer()->getWorldManager()->getWorldByName($gameId);
        if ($freshWorld !== null) {
            $freshSpawn = new Position(
                $assignedSpawnPoint->getX(),
                $assignedSpawnPoint->getY(),
                $assignedSpawnPoint->getZ(),
                $freshWorld
            );
            $player->teleport($freshSpawn);
        }
    }

    
    if (!isset($this->playerCheckpoints[$gameId])) {
        $this->playerCheckpoints[$gameId] = [];
    }
    $this->playerCheckpoints[$gameId][$playerName] = $assignedIndex;
    
    $player->sendMessage("§aJoined game: $gameId");
    
    if ($this->games[$gameId]['status'] === 'waiting') {
        $currentPlayerCount++;
        $this->broadcastToGame($gameId, "§a{$player->getName()} joined the queue! (§6{$currentPlayerCount}/{$maxPlayers}§a)");
    }
    
    if (!isset($this->games[$gameId]['players'])) {
        $this->games[$gameId]['players'] = [];
    }
    $this->games[$gameId]['players'][] = $player;
    
    if ($this->games[$gameId]['status'] === 'waiting') {
        $minPlayers = $this->getMapSetting($gameId, "min_players", 2);
        if ($currentPlayerCount < $minPlayers) {
            $this->setPersistentActionBar($gameId, "§cWaiting for players... (§6{$currentPlayerCount}/{$maxPlayers}§c)");
        } else {
            $this->clearPersistentActionBar($gameId);
        }
        
        if (isset($this->persistentActionBars[$gameId])) {
            $player->sendActionBarMessage($this->persistentActionBars[$gameId]);
            $this->sendActionBarToGame($gameId, $this->persistentActionBars[$gameId]);
        }
        
        $this->plugin->getScheduler()->scheduleDelayedTask(new class($player, $this->persistentActionBars[$gameId] ?? "") extends \pocketmine\scheduler\Task {
            private $player;
            private $message;
            
            public function __construct(Player $player, string $message) {
                $this->player = $player;
                $this->message = $message;
            }
            
            public function onRun(): void {
                if ($this->player->isOnline()) {
                    $this->player->sendActionBarMessage($this->message);
                }
            }
        }, 1);
    }
    $this->plugin->getScoreHUDManager()->setupDefaultTags($player);
    $this->plugin->getScoreHUDManager()->updateGameScoreboard($player, $gameId, "Waiting", 0);
    $this->checkGameStart($gameId);
}
    
    public function broadcastToGame(string $gameId, string $message): void {
        $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($gameId);
        if($world !== null) {
            foreach($world->getPlayers() as $player) {
                if($player->isOnline()) {
                    $player->sendMessage($message);
                }
            }
        }
    }
    
   private function checkGameStart(string $gameId): void {
    $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($gameId);
    if($world === null) return;
    
    
    $currentPlayers = array_filter($world->getPlayers(), function($p) {
        return $p->isOnline() && $p->getGamemode()->equals(\pocketmine\player\GameMode::ADVENTURE());
    });
    $playerCount = count($currentPlayers);
    
    $minPlayers = $this->getMapSetting($gameId, "min_players", 2);
    $maxPlayers = $this->getMapSetting($gameId, "max_players", 12); 
    
    
    if($playerCount < $minPlayers) {
        $this->setPersistentActionBar($gameId, "§cWaiting for players... (§6{$playerCount}/{$maxPlayers}§c)");
    } else {
        $this->clearPersistentActionBar($gameId);
        if(!isset($this->countdownTasks[$gameId])) {
            $this->startCountdown($gameId);
        }
    }
}
    
    private function startCountdown(string $gameId): void {
        $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($gameId);
        if($world === null) return;
        
        
        $players = array_filter($world->getPlayers(), function($p) {
            return $p->isOnline() && $p->getGamemode()->equals(\pocketmine\player\GameMode::ADVENTURE());
        });
        
        $countdownTime = $this->getMapSetting($gameId, "countdown_time", 30);
        
        
        $this->clearPersistentActionBar($gameId);
        
        $this->broadcastToGame($gameId, "§eEnough players! Countdown starting...");
        
        $task = new CountdownTask($this->plugin, $players, $gameId, $countdownTime);
        $handler = $this->plugin->getScheduler()->scheduleRepeatingTask($task, 20);
        
        $this->countdownTasks[$gameId] = $handler;
    }
    
    public function safeCancelCountdown(string $gameId): void {
        if(isset($this->countdownTasks[$gameId])) {
            $handler = $this->countdownTasks[$gameId];
            if($handler !== null) {
                $handler->cancel();
            }
            unset($this->countdownTasks[$gameId]);
            
            $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($gameId);
            if($world !== null) {
                
                $currentPlayers = array_filter($world->getPlayers(), function($player) {
                    return $player->isOnline() && $player->getGamemode()->equals(\pocketmine\player\GameMode::ADVENTURE());
                });
                $playerCount = count($currentPlayers);
                $maxPlayers = $this->getMapSetting($gameId, "max_players", 12);
                
                foreach($currentPlayers as $player) {
                    if($player->isOnline()) {
                        $player->sendMessage("§cCountdown cancelled! Waiting for more players...");
                        $player->sendTitle("", "§cNeed more players!", 0, 40, 0);
                        $player->sendActionBarMessage(""); 
                    }
                }
                
                $this->setPersistentActionBar($gameId, "§cWaiting for players... (§6{$playerCount}/{$maxPlayers}§c)");
            }
        }
    }
    
    public function cancelCountdown(string $gameId): void {
        $this->safeCancelCountdown($gameId);
    }
    
   public function startGame(array $players, string $gameId): void {
    $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($gameId);
    if($world === null) return;
    
    if(isset($this->countdownTasks[$gameId])) {
        unset($this->countdownTasks[$gameId]);
    }
    
    
    $this->clearPersistentActionBar($gameId);
    
    $this->games[$gameId]['status'] = 'playing';
    
    $spawnPoints = $this->plugin->getSpawnManager()->getSpawnPointsForWorld($gameId);
    
    foreach($players as $player) {
        if($player->isOnline()) {
            
            if(isset($this->playerCheckpoints[$player->getName()]) && !empty($spawnPoints)) {
                $spawnIndex = $this->playerCheckpoints[$player->getName()];
                if(isset($spawnPoints[$spawnIndex])) {
                    $player->teleport($spawnPoints[$spawnIndex]);
                }
            }
            
            $player->setGamemode(\pocketmine\player\GameMode::SURVIVAL());
            $player->setNoClientPredictions(false);
            
            $player->getInventory()->clearAll();
            
            
            $player->getHungerManager()->setFood(20);
            $player->getHungerManager()->setSaturation(20);
            $player->getHungerManager()->setEnabled(false);
            
            $player->sendActionBarMessage("");
        }
    }
    
    $itemInterval = $this->getMapSetting($gameId, "item_interval", 600);
    $maxPlayers = $this->getMapSetting($gameId, "max_players", 12);
    $itemTask = new ItemDistributionTask($this->plugin, $players, $gameId, $maxPlayers);
    $handler = $this->plugin->getScheduler()->scheduleRepeatingTask($itemTask, 1);
    
    $this->activeGames[$gameId] = [
        'status' => 'Running',
        'players' => $players,
        'item_task' => $handler
    ];
    
    $this->waitingPlayers[$gameId] = [];
    
    
    $this->broadcastToGame($gameId, "§a§lGAME STARTED! §r§aThe battle begins!");
    
    foreach($players as $player) {
        if($player->isOnline()) {
            $player->sendTitle(
                TextFormat::GREEN . "GAME STARTED!",
                TextFormat::YELLOW . "Last player standing wins!",
                10, 40, 10
            );
            
            
            $this->plugin->getScoreHUDManager()->updateGameScoreboard(
                $player, 
                $gameId, 
                "Playing", 
                0,
                $players
            );
        }
    }
    
    $this->startGameTimer($gameId);
}
          
    public function handlePlayerDeath(Player $player, string $gameId): void {
    if(!isset($this->activeGames[$gameId])) return;
    
    
    if(isset($this->bossBars[$player->getId()])) {
        $this->bossBars[$player->getId()]->removeAllPlayers();
        unset($this->bossBars[$player->getId()]);
    }
    
    
    $this->setPlayerSpectator($player, $gameId);
    
    
    $this->plugin->getScoreHUDManager()->updateGameScoreboard(
        $player, 
        $gameId, 
        "Spectating", 
        0,
        $this->activeGames[$gameId]['players']
    );
    
    
    $key = array_search($player, $this->activeGames[$gameId]['players'], true);
    if($key !== false) {
        unset($this->activeGames[$gameId]['players'][$key]);
        $this->activeGames[$gameId]['players'] = array_values($this->activeGames[$gameId]['players']);
    }
    
    
    if(isset($this->games[$gameId]['players'])) {
        $key = array_search($player, $this->games[$gameId]['players'], true);
        if($key !== false) {
            unset($this->games[$gameId]['players'][$key]);
            $this->games[$gameId]['players'] = array_values($this->games[$gameId]['players']);
        }
    }
    
    
    foreach($this->activeGames[$gameId]['players'] as $alivePlayer) {
        if($alivePlayer->isOnline()) {
            $this->plugin->getScoreHUDManager()->updateGameScoreboard(
                $alivePlayer, 
                $gameId, 
                "Playing", 
                0,
                $this->activeGames[$gameId]['players']
            );
        }
    }
    
    
    $this->broadcastToGame($gameId, "§cPlayer {$player->getName()} Died!");
    
    
    $this->checkGameEnd($gameId);
}
    
       public function checkGameEnd(string $gameId): void {
        if(!isset($this->activeGames[$gameId])) return;
        
        $players = $this->activeGames[$gameId]['players'];
        
        
        if(count($players) === 1) {
            $winner = reset($players);
            if($winner->isOnline()) {
                
                $winner->sendTitle("§6§lVICTORY!", "§aYou won the game!", 10, 60, 10);
                
            }
            
            
            $this->plugin->getScheduler()->scheduleDelayedTask(new class($this->plugin, $gameId) extends \pocketmine\scheduler\Task {
                private $plugin;
                private $gameId;
                
                public function __construct($plugin, $gameId) {
                    $this->plugin = $plugin;
                    $this->gameId = $gameId;
                }
                
                public function onRun(): void {
                    $this->plugin->getGameManager()->endGame($this->gameId);
                }
            }, 20); 
        }
        
        else if(count($players) === 0) {
            $this->broadcastToGame($gameId, "§cGame ended with no winner!");
            $this->endGame($gameId);
        }
    }
    
    public function showSpectatorMenu(Player $spectator): void {
        $gameId = $this->getPlayerGame($spectator);
        if($gameId === null) return;
        
        $alivePlayers = $this->getAlivePlayers($gameId);
        if(empty($alivePlayers)) {
            $spectator->sendMessage("§cNo alive players to spectate!");
            return;
        }
        
        $form = SpectatorForm::createForm($this->plugin, $spectator, $alivePlayers);
        $spectator->sendForm($form);
    }
    
    public function teleportSpectatorToPlayer(Player $spectator, Player $target): void {
        if($spectator->isOnline() && $target->isOnline()) {
            $spectator->teleport($target->getPosition());
            $spectator->sendMessage("§aTeleported to §6{$target->getName()}");
        }
    }
    
    public function getAlivePlayers(string $gameId): array {
        return $this->activeGames[$gameId]['players'] ?? [];
    }
    
public function removePlayerFromGame(Player $player): void {
    $playerName = $player->getName();
    $gameId = $this->getPlayerGame($player);
    
    
    if(isset($this->bossBars[$player->getId()])) {
        $this->bossBars[$player->getId()]->removeAllPlayers();
        unset($this->bossBars[$player->getId()]);
    }
    
    
    if ($gameId !== null) {
        $playerNameLower = strtolower($playerName);
        if (isset($this->playerCheckpoints[$gameId][$playerNameLower])) {
            unset($this->playerCheckpoints[$gameId][$playerNameLower]);
        }
    }
    
    
    foreach ($this->waitingPlayers as $gameId => $waitingList) {
        $key = array_search($player, $waitingList, true);
        if ($key !== false) {
            unset($this->waitingPlayers[$gameId][$key]);
            
            $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($gameId);
            if ($world !== null) {
                $currentPlayers = array_filter($world->getPlayers(), function($p) {
                    return $p->isOnline() && $p->getGamemode()->equals(\pocketmine\player\GameMode::ADVENTURE());
                });
                $playerCount = count($currentPlayers);
            } else {
                $playerCount = count($this->waitingPlayers[$gameId]);
            }
            
            $maxPlayers = $this->getMapSetting($gameId, "max_players", 12);
            $minPlayers = $this->getMapSetting($gameId, "min_players", 2);
            
            if ($this->games[$gameId]['status'] === 'waiting') {
                $this->broadcastToGame($gameId, "§c{$playerName} left the queue! (§6{$playerCount}/{$maxPlayers}§c)");
            }
            
            if ($playerCount > 0) {
                $this->setPersistentActionBar($gameId, "§cWaiting for players... (§6{$playerCount}/{$maxPlayers}§c)");
            } else {
                $this->setPersistentActionBar($gameId, "§cNeed more players! (§6{$playerCount}/{$maxPlayers}§c)");
            }
            
            if ($playerCount < $minPlayers && isset($this->countdownTasks[$gameId])) {
                $this->safeCancelCountdown($gameId);
            }
        }
    }
    
    foreach ($this->countdownTasks as $gameId => $handler) {
        $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($gameId);
        if ($world !== null) {
            $currentPlayers = array_filter($world->getPlayers(), function($p) {
                return $p->isOnline() && $p->getGamemode()->equals(\pocketmine\player\GameMode::ADVENTURE());
            });
            $playerCount = count($currentPlayers);
            $minPlayers = $this->getMapSetting($gameId, "min_players", 2);
            
            if ($playerCount < $minPlayers) {
                $this->safeCancelCountdown($gameId);
            }
        }
    }
    
    foreach ($this->activeGames as $gameId => $gameData) {
        $key = array_search($player, $gameData['players'], true);
        if ($key !== false) {
            unset($this->activeGames[$gameId]['players'][$key]);
            
            $remainingPlayers = count($this->activeGames[$gameId]['players']);
            if ($remainingPlayers <= 1) {
                $this->endGame($gameId);
            }
        }
    }
    
    foreach ($this->spectators as $gameId => $spectators) {
        $key = array_search($player, $spectators, true);
        if ($key !== false) {
            unset($this->spectators[$gameId][$key]);
            $this->spectators[$gameId] = array_values($this->spectators[$gameId]);
        }
    }
    
    foreach ($this->games as $gameId => $gameData) {
        $key = array_search($player, $gameData['players'], true);
        if ($key !== false) {
            unset($this->games[$gameId]['players'][$key]);
        }
    }
    
    $this->resetPlayer($player);
    $this->teleportToLobby($player);
    $this->plugin->getScoreHUDManager()->updateLobbyScoreboard($player);
}
    
     public function teleportToLobby(Player $player): void {
    $lobbyWorld = "world";
    $lobby = $this->plugin->getServer()->getWorldManager()->getWorldByName($lobbyWorld);
    
    if ($lobby !== null) {
        $player->teleport($lobby->getSpawnLocation());
        if ($lobby->getFolderName() === "world" && $this->checkPlayerState($player) === null) {
            $this->giveMarketItem($player);
        }
    } else {
        $defaultWorld = $this->plugin->getServer()->getWorldManager()->getDefaultWorld();
        if ($defaultWorld !== null) {
            $player->teleport($defaultWorld->getSpawnLocation());
            if ($defaultWorld->getFolderName() === "world" && $this->checkPlayerState($player) === null) {
                $this->giveMarketItem($player);
            }
        }
    }
}
    
    public function endGame(string $gameId): void {
    if(!isset($this->activeGames[$gameId])) return;
    
    $gameData = $this->activeGames[$gameId];
    $players = $gameData['players'];
    
    
    if(isset($gameData['item_task'])) {
        $gameData['item_task']->cancel();
    }
    
    
    $allPlayersInGame = [];
    $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($gameId);
    if($world !== null) {
        foreach($world->getPlayers() as $player) {
            if($player->isOnline()) {
                $allPlayersInGame[] = $player;
                $this->addPlayTimeCoins($player, $gameId);
            }
        }
    }
    
    
    $winner = null;
    if(count($players) === 1) {
        $winner = reset($players);
    }
    $this->showGameStatistics($gameId, $winner);
    
    
    $this->plugin->getScheduler()->scheduleDelayedTask(new class($this->plugin, $gameId) extends \pocketmine\scheduler\Task {
        private $plugin;
        private $gameId;
        
        public function __construct($plugin, $gameId) {
            $this->plugin = $plugin;
            $this->gameId = $gameId;
        }
        
        public function onRun(): void {
            $this->plugin->getGameManager()->teleportAllToLobby($this->gameId);
            
            
            $this->plugin->getScheduler()->scheduleDelayedTask(new class($this->plugin, $this->gameId) extends \pocketmine\scheduler\Task {
                private $plugin;
                private $gameId;
                
                public function __construct($plugin, $gameId) {
                    $this->plugin = $plugin;
                    $this->gameId = $gameId;
                }
                
                public function onRun(): void {
                    
                    $this->plugin->getMapManager()->resetWorld($this->gameId);
                }
            }, 20); 
        }
    }, 5 * 20); 
    
    
    $this->games[$gameId]['status'] = 'ending';
    unset($this->activeGames[$gameId]);
    
    
    if(isset($this->spectators[$gameId])) {
        unset($this->spectators[$gameId]);
    }
    
    
    $this->clearPersistentActionBar($gameId);
}
    
public function teleportAllToLobby(string $gameId): void {
    
    $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($gameId);
    if($world === null) return;
    
    $allPlayers = $world->getPlayers();
    
    foreach($allPlayers as $player) {
        if($player->isOnline()) {
            $this->teleportToLobby($player);
            $this->resetPlayer($player);
            $this->plugin->getScoreHUDManager()->updateLobbyScoreboard($player);
        }
    }
    
    
    
    if(isset($this->games[$gameId])) {
        $this->games[$gameId]['status'] = 'waiting';
        $this->games[$gameId]['players'] = [];
    }
    
    
    if(isset($this->gameStats[$gameId])) {
        unset($this->gameStats[$gameId]);
    }
    
    
    if(isset($this->playerCheckpoints[$gameId])) {
        unset($this->playerCheckpoints[$gameId]);
    }
    
    $this->savePlayerStats(); 
    
    
    $this->plugin->getScheduler()->scheduleDelayedTask(new class($this->plugin, $gameId) extends \pocketmine\scheduler\Task {
        private $plugin;
        private $gameId;
        
        public function __construct($plugin, $gameId) {
            $this->plugin = $plugin;
            $this->gameId = $gameId;
        }
        
        public function onRun(): void {
            $this->plugin->getMapManager()->resetWorld($this->gameId);
        }
    }, 40); 
}
    
      private function showGameStatistics(string $gameId, ?Player $winner = null): void {
        
        $allGamePlayers = [];
        
        
        $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($gameId);
        if($world !== null) {
            foreach($world->getPlayers() as $player) {
                if($player->isOnline()) {
                    $username = strtolower($player->getName());
                    $allGamePlayers[$username] = [
                        'name' => $player->getName(),
                        'player' => $player
                    ];
                }
            }
        }
        
        
        $this->broadcastToGame($gameId, "§6§l--- Game Ended ---");
        
        if($winner !== null && $winner->isOnline()) {
            
            $this->broadcastToGame($gameId, "§6§l" . $winner->getName() . " §6won the game!");
            $this->addWin($winner);
        } else {
            $this->broadcastToGame($gameId, "§cNo winner this round!");
        }
        
        $this->broadcastToGame($gameId, "§6§l-----------------------");
        
        
        $this->broadcastToGame($gameId, "§aPersonal Stats:");
        
        foreach($allGamePlayers as $username => $playerData) {
            $player = $playerData['player'];
            
            if($player !== null && $player->isOnline()) {
                
                $playerStats = $this->getPlayerStats($player);
                $coins = $playerStats['coins'];
                $wins = $playerStats['wins'];
                
                
                $player->sendMessage("§eYour Stats:");
                
                
                $isWinner = ($winner !== null && $winner->getId() === $player->getId());
                
                if($isWinner) {
                    $player->sendMessage("§7Victory: §6+20 coins");
                }
                
                $player->sendMessage("§7Participation: §6+5 coins");
                $player->sendMessage("§7Total Coins: §e" . $coins . " coins");
                $player->sendMessage("§7Total Wins: §a" . $wins);
                
                $player->sendMessage("§6§l-----------------------");
            }
        }
    }
    
    public function getPlayerStatsByName(string $playerName): array {
        $username = strtolower($playerName);
        if(!isset($this->playerStats[$username])) {
            return [
                'kills' => 0,
                'assists' => 0,
                'wins' => 0,
                'coins' => 0
            ];
        }
        return $this->playerStats[$username];
    }
    
    public function saveOfflinePlayerStats(string $playerName, array $stats): void {
        $username = strtolower($playerName);
        $this->playerStats[$username] = $stats;
        $this->savePlayerStats();
    }
    
    public function checkPlayerState(Player $player): ?string {
        foreach($this->waitingPlayers as $gameId => $waitingList) {
            if(in_array($player, $waitingList, true)) {
                return 'waiting';
            }
        }
        
        foreach($this->activeGames as $gameId => $gameData) {
            if(in_array($player, $gameData['players'], true)) {
                return 'playing';
            }
        }
        
        
        foreach($this->spectators as $gameId => $spectators) {
            if(in_array($player, $spectators, true)) {
                return 'spectating';
            }
        }
        
        return null;
    }
    
    public function getPlayerGame(Player $player): ?string {
        foreach($this->games as $gameId => $gameData) {
            if(in_array($player, $gameData['players'], true)) {
                return $gameId;
            }
        }
        
        
        foreach($this->spectators as $gameId => $spectators) {
            if(in_array($player, $spectators, true)) {
                return $gameId;
            }
        }
        
        return null;
    }
    
public function getMapSetting(string $gameId, string $key, $default = null) {
    
    if(isset($this->games[$gameId][$key])) {
        return $this->games[$gameId][$key];
    }
    
    
    return $this->plugin->getConfigManager()->getMapSetting($gameId, $key, $default);
}
    
    public function getMapMaxPlayers(string $gameId): int {
        return $this->getMapSetting($gameId, "max_players", 12);
    }
 public function giveMarketItem(Player $player): void {
        if ($player->getWorld()->getFolderName() !== "world" || $this->checkPlayerState($player) !== null) {
            return;
        }

        $marketItem = VanillaItems::NETHER_STAR()->setCustomName("§d§lMarket");
        $this->marketItems[$player->getId()] = $marketItem;
        
        $inventory = $player->getInventory();
        $inventory->setItem(8, $marketItem);

        $this->registerMarketItemListeners($player, $marketItem);
    }

    public function removeMarketItem(Player $player): void {
        $playerId = $player->getId();
        if (isset($this->marketItems[$playerId])) {
            unset($this->marketItems[$playerId]);
        }
        $inventory = $player->getInventory();
        $itemInSlot = $inventory->getItem(8);
        if ($itemInSlot->getCustomName() === "§d§lMarket" && $itemInSlot instanceof \pocketmine\item\VanillaItems\NETHER_STAR) {
            $inventory->setItem(8, VanillaItems::AIR());
        }
    }

    private function registerMarketItemListeners(Player $player, \pocketmine\item\Item $marketItem): void {
        $listener = new class($player, $marketItem, $this->plugin, $this) implements \pocketmine\event\Listener {
            private $player;
            private $marketItem;
            private $plugin;
            private $gameManager;
            
            public function __construct(Player $player, \pocketmine\item\Item $marketItem, Main $plugin, GameManager $gameManager) {
                $this->player = $player;
                $this->marketItem = $marketItem;
                $this->plugin = $plugin;
                $this->gameManager = $gameManager;
            }
            
            public function onPlayerDropItem(\pocketmine\event\player\PlayerDropItemEvent $event): void {
                if ($event->getPlayer()->getId() !== $this->player->getId()) {
                    return;
                }
                $item = $event->getItem();
                if ($item->equals($this->marketItem, true, false) && $item->getCustomName() === "§d§lMarket") {
                    $event->cancel();
                }
            }
            
            public function onInventoryTransaction(\pocketmine\event\inventory\InventoryTransactionEvent $event): void {
                $player = $event->getTransaction()->getSource();
                if ($player->getId() !== $this->player->getId() || $player->getWorld()->getFolderName() !== "world") {
                    return;
                }
                
                $inventory = $player->getInventory();
                $actions = $event->getTransaction()->getActions();
                
                foreach ($actions as $action) {
                    if ($action instanceof \pocketmine\inventory\transaction\action\SlotChangeAction) {
                        $item = $action->getTargetItem();
                        $sourceItem = $action->getSourceItem();
                        if ($action->getSlot() === 8 && $sourceItem->equals($this->marketItem, true, false) && $sourceItem->getCustomName() === "§d§lMarket") {
                            if ($item->isNull() || $item->equals($this->marketItem, true, false)) {
                                continue;
                            }
                            $event->cancel();
                            $inventory->setItem(8, $this->marketItem);
                        } elseif ($item->equals($this->marketItem, true, false) && $item->getCustomName() === "§d§lMarket" && $action->getSlot() !== 8) {
                            $event->cancel();
                            $inventory->setItem(8, $this->marketItem);
                        }
                    }
                }
            }
            
            public function onPlayerInteract(\pocketmine\event\player\PlayerInteractEvent $event): void {
                $player = $event->getPlayer();
                if ($player->getId() !== $this->player->getId() || $player->getWorld()->getFolderName() !== "world") {
                    return;
                }
                
                $item = $event->getItem();
                if ($item->equals($this->marketItem, true, false) && $item->getCustomName() === "§d§lMarket") {
                    $player->sendForm(MarketForm::createForm());
                    $event->cancel();
                }
            }
            
            public function onPlayerQuit(\pocketmine\event\player\PlayerQuitEvent $event): void {
                if ($event->getPlayer()->getId() === $this->player->getId()) {
                    $this->gameManager->removeMarketItem($this->player);
                }
            }
        };

        $this->plugin->getServer()->getPluginManager()->registerEvents($listener, $this->plugin);
        
        $this->plugin->getScheduler()->scheduleRepeatingTask(new class($player, $this->plugin, $marketItem) extends \pocketmine\scheduler\Task {
            private $player;
            private $plugin;
            private $marketItem;
            
            public function __construct(Player $player, Main $plugin, \pocketmine\item\Item $marketItem) {
                $this->player = $player;
                $this->plugin = $plugin;
                $this->marketItem = $marketItem;
            }
            
            public function onRun(): void {
                if (!$this->player->isOnline() || $this->player->getWorld()->getFolderName() !== "world" || $this->plugin->getGameManager()->checkPlayerState($this->player) !== null) {
                    $this->getHandler()->cancel();
                    return;
                }
                
                $inventory = $this->player->getInventory();
                $itemInSlot = $inventory->getItem(8);
                if (!$itemInSlot->equals($this->marketItem, true, false) || $itemInSlot->getCustomName() !== "§d§lMarket") {
                    $inventory->setItem(8, $this->marketItem);
                }
                
                $world = $this->player->getWorld();
                $nearbyEntities = $world->getNearbyEntities($this->player->getBoundingBox()->expandedCopy(5, 5, 5));
                foreach ($nearbyEntities as $entity) {
                    if ($entity instanceof \pocketmine\entity\object\ItemEntity) {
                        $item = $entity->getItem();
                        if ($item->equals($this->marketItem, true, false) && $item->getCustomName() === "§d§lMarket") {
                            $entity->flagForDespawn();
                        }
                    }
                }
            }
        }, 20);
    }

    public function ensureMarketItem(): void {
        $lobbyWorld = $this->plugin->getServer()->getWorldManager()->getWorldByName("world");
        if ($lobbyWorld === null) {
            return;
        }
        
        foreach ($lobbyWorld->getPlayers() as $player) {
            if ($player->isOnline() && $this->checkPlayerState($player) === null) {
                $inventory = $player->getInventory();
                $marketItem = isset($this->marketItems[$player->getId()]) ? $this->marketItems[$player->getId()] : VanillaItems::NETHER_STAR()->setCustomName("§d§lMarket");
                
                $itemInSlot = $inventory->getItem(8);
                if (!$itemInSlot->equals($marketItem, true, false) || $itemInSlot->getCustomName() !== "§d§lMarket") {
                    $inventory->setItem(8, $marketItem);
                    $this->marketItems[$player->getId()] = $marketItem;
                    $this->registerMarketItemListeners($player, $marketItem);
                }
            }
        }
    }
}
