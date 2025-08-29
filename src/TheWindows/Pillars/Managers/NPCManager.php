<?php
namespace TheWindows\Pillars\Managers;

use pocketmine\player\Player;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\scheduler\Task;
use pocketmine\world\particle\DustParticle;
use pocketmine\color\Color;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use TheWindows\Pillars\Main;
use TheWindows\Pillars\Entity\PillarsNPC;
use SQLite3;

class NPCManager {
    
    private $plugin;
    private $npcs = [];
    private $knownNPCs = [];
    private $rotationTask;
    private $database;
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->initDatabase();
        $this->loadNPCs();
        $this->spawnAllNPCs();
        $this->startRotationTask();
    }
    
    private function initDatabase(): void {
        $dbPath = $this->plugin->getDataFolder() . "npcs.db";
        $this->database = new SQLite3($dbPath);
        $this->database->exec("
            CREATE TABLE IF NOT EXISTS npcs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                world TEXT NOT NULL,
                x REAL NOT NULL,
                y REAL NOT NULL,
                z REAL NOT NULL,
                yaw REAL NOT NULL,
                pitch REAL NOT NULL,
                scale REAL NOT NULL,
                skin_id TEXT,
                skin_data BLOB,
                cape_data BLOB,
                geometry_name TEXT,
                geometry_data TEXT
            )
        ");
        
        $columns = ['skin_id', 'skin_data', 'cape_data', 'geometry_name', 'geometry_data'];
        $result = $this->database->query("PRAGMA table_info(npcs)");
        $existingColumns = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $existingColumns[] = $row['name'];
        }
        foreach ($columns as $column) {
            if (!in_array($column, $existingColumns)) {
                $type = ($column === 'skin_data' || $column === 'cape_data') ? 'BLOB' : 'TEXT';
                $this->database->exec("ALTER TABLE npcs ADD COLUMN $column $type");
                $this->plugin->getLogger()->info("Added $column column to npcs table");
            }
        }
    }
    
    public function getKnownNPCs(): array {
        return $this->knownNPCs;
    }
    
    public function cleanKnownNPCs(): void {
        $this->knownNPCs = array_filter($this->knownNPCs, fn($e) => !$e->isClosed() && !$e->isFlaggedForDespawn());
    }
    
    public function startRotationTask(): void {
        $this->rotationTask = $this->plugin->getScheduler()->scheduleRepeatingTask(new class($this->plugin) extends Task {
            private $plugin;
            private array $colors;
            private int $colorIndex = 0;
            private int $tick = 0;
            private int $spiralDirection = 1;
            
            public function __construct(Main $plugin) {
                $this->plugin = $plugin;
                $this->colors = [
                    new Color(200, 200, 200), 
                    new Color(0, 255, 0), 
                    new Color(255, 0, 0), 
                    new Color(255, 255, 0) 
                ];
            }
            
            public function onRun(): void {
                $this->tick++;
                if ($this->tick % 40 === 0) {
                    $this->colorIndex = ($this->colorIndex + 1) % count($this->colors);
                }
                if ($this->tick % 200 === 0) {
                    $this->spiralDirection = -$this->spiralDirection;
                }
                
                $npcManager = $this->plugin->getNPCManager();
                $npcManager->cleanKnownNPCs();
                foreach ($npcManager->getKnownNPCs() as $entity) {
                    $npcManager->rotateNPC($entity);
                    $this->spawnParticles($entity);
                }
            }
            
            private function spawnParticles(Entity $entity): void {
                $pos = $entity->getPosition();
                $world = $entity->getWorld();
                $radius = 0.5;
                $helixHeight = $entity->getScale() * 2.0;
                $steps = 32;
                $angleStep = (2 * M_PI) / ($steps / 4); 
                $heightStep = $helixHeight / $steps;
                $angleOffset = $this->tick * 0.2 * $this->spiralDirection;
                
                for ($i = 0; $i < $steps; $i++) {
                    $frac = $i / $steps;
                    if ($this->spiralDirection === -1) {
                        $frac = 1 - $frac;
                    }
                    $angle = $frac * (4 * 2 * M_PI) + $angleOffset;
                    $x = $radius * cos($angle);
                    $z = $radius * sin($angle);
                    $h = $pos->y + $frac * $helixHeight;
                    $ppos = new Vector3($pos->x + $x, $h, $pos->z + $z);
                    $colorIndex = $i % count($this->colors);
                    $particle = new DustParticle($this->colors[$colorIndex]);
                    $world->addParticle($ppos, $particle);
                }
            }
        }, 20); 
    }
    
    public function rotateNPC(Entity $npc): void {
        $players = $npc->getWorld()->getPlayers();
        $closestPlayer = null;
        $closestDistance = 10.0; 
        
        forEach ($players as $player) {
            $distance = $npc->getPosition()->distance($player->getPosition());
            if ($distance < $closestDistance) {
                $closestDistance = $distance;
                $closestPlayer = $player;
            }
        }
        
        if ($closestPlayer !== null) {
            $xDiff = $closestPlayer->getPosition()->x - $npc->getPosition()->x;
            $zDiff = $closestPlayer->getPosition()->z - $npc->getPosition()->z;
            
            $yaw = atan2($zDiff, $xDiff) * 180 / M_PI - 90;
            $npc->setRotation($yaw, 0);
        }
    }
    
    public function createNPC(Player $player): void {
        try {
            $location = $player->getLocation();
            
            
            $x = $location->getX();
            $y = $location->getY();
            $z = $location->getZ();
            $yaw = $location->getYaw();
            $pitch = $location->getPitch();
            $worldName = $location->getWorld()->getFolderName();
            $skin = $player->getSkin();
            $skinId = $skin->getSkinId();
            $skinData = $skin->getSkinData();
            $capeData = $skin->getCapeData();
            $geometryName = $skin->getGeometryName();
            $geometryData = $skin->getGeometryData();

            
            $this->plugin->getLogger()->debug("Creating NPC with skin_id: $skinId, skin_data size: " . strlen($skinData) . ", cape_data size: " . strlen($capeData) . ", geometry_name: $geometryName");

            
            $exactLocation = new Location($x, $y, $z, $location->getWorld(), $yaw, $pitch);

            $nbt = CompoundTag::create()
                ->setString("CustomName", "§4Pillars Minigame\n§7Click To Join")
                ->setByte("CustomNameVisible", 1)
                ->setByte("NoAI", 1)
                ->setByte("Silent", 1)
                ->setByte("Invulnerable", 1)
                ->setByte("NoGravity", 1)
                ->setByte("Immobile", 1)
                ->setFloat("Scale", 1.5)
                ->setTag("Pos", new ListTag([
                    new FloatTag($x),
                    new FloatTag($y),
                    new FloatTag($z)
                ]))
                ->setTag("Rotation", new ListTag([
                    new FloatTag($yaw),
                    new FloatTag($pitch)
                ]));

            $entity = new PillarsNPC($exactLocation, $skin, $nbt);
            $entity->setNameTag("§4Pillars Minigame\n§7Click To Join");
            $entity->setNameTagAlwaysVisible(true);
            $entity->setScale(1.5);
            $entity->setNoClientPredictions(true);
            $entity->setCanSaveWithChunk(false);
            
            $entity->spawnToAll();

            
            $stmt = $this->database->prepare("
                INSERT INTO npcs (world, x, y, z, yaw, pitch, scale, skin_id, skin_data, cape_data, geometry_name, geometry_data)
                VALUES (:world, :x, :y, :z, :yaw, :pitch, :scale, :skin_id, :skin_data, :cape_data, :geometry_name, :geometry_data)
            ");
            $stmt->bindValue(':world', $worldName, SQLITE3_TEXT);
            $stmt->bindValue(':x', $x, SQLITE3_FLOAT);
            $stmt->bindValue(':y', $y, SQLITE3_FLOAT);
            $stmt->bindValue(':z', $z, SQLITE3_FLOAT);
            $stmt->bindValue(':yaw', $yaw, SQLITE3_FLOAT);
            $stmt->bindValue(':pitch', $pitch, SQLITE3_FLOAT);
            $stmt->bindValue(':scale', 1.5, SQLITE3_FLOAT);
            $stmt->bindValue(':skin_id', $skinId, SQLITE3_TEXT);
            $stmt->bindValue(':skin_data', $skinData, SQLITE3_BLOB);
            $stmt->bindValue(':cape_data', $capeData, SQLITE3_BLOB);
            $stmt->bindValue(':geometry_name', $geometryName, SQLITE3_TEXT);
            $stmt->bindValue(':geometry_data', $geometryData, SQLITE3_TEXT);
            $stmt->execute();
            
            $npcId = $this->database->lastInsertRowID();
            
            
            $npcData = [
                'id' => $npcId,
                'world' => $worldName,
                'x' => $x,
                'y' => $y,
                'z' => $z,
                'yaw' => $yaw,
                'pitch' => $pitch,
                'scale' => 1.5,
                'skin_id' => $skinId,
                'skin_data' => $skinData,
                'cape_data' => $capeData,
                'geometry_name' => $geometryName,
                'geometry_data' => $geometryData
            ];

            $this->npcs[$npcId] = $npcData;
            $this->knownNPCs[] = $entity;

            $player->sendMessage("§aNPC created at x: $x, y: $y, z: $z in world: $worldName with player's skin");

        } catch (\Exception $e) {
            $player->sendMessage("§cError creating NPC: " . $e->getMessage());
            $this->plugin->getLogger()->error("NPC Creation Error: " . $e->getMessage());
        }
    }
    
    public function removeNPC(int $id): bool {
        if (!isset($this->npcs[$id])) {
            return false;
        }
        
        $npcData = $this->npcs[$id];
        $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($npcData['world']);
        if ($world !== null) {
            $entities = $world->getEntities();
            foreach ($entities as $entity) {
                if ($entity instanceof PillarsNPC && 
                    abs($entity->getPosition()->x - $npcData['x']) < 0.01 &&
                    abs($entity->getPosition()->y - $npcData['y']) < 0.01 &&
                    abs($entity->getPosition()->z - $npcData['z']) < 0.01) {
                    $entity->flagForDespawn();
                    foreach ($this->knownNPCs as $k => $e) {
                        if ($e === $entity) {
                            unset($this->knownNPCs[$k]);
                        }
                    }
                    break;
                }
            }
        }
        
        
        $stmt = $this->database->prepare("DELETE FROM npcs WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        
        unset($this->npcs[$id]);
        return true;
    }
    
    public function removeAllNPCs(): void {
        foreach ($this->knownNPCs as $entity) {
            if (!$entity->isClosed()) {
                $entity->flagForDespawn();
            }
        }
        foreach ($this->plugin->getServer()->getWorldManager()->getWorlds() as $world) {
            foreach ($world->getEntities() as $entity) {
                if ($entity instanceof PillarsNPC) {
                    $entity->flagForDespawn();
                }
            }
        }
        
        
        $this->database->exec("DELETE FROM npcs");
        
        $this->npcs = [];
        $this->knownNPCs = [];
    }
    
    public function getNPCs(): array {
        return $this->npcs;
    }
    
    private function loadNPCs(): void {
        $this->npcs = [];
        
        $result = $this->database->query("SELECT * FROM npcs");
        if ($result) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $npcData = [
                    'id' => $row['id'],
                    'world' => $row['world'],
                    'x' => (float)$row['x'],
                    'y' => (float)$row['y'],
                    'z' => (float)$row['z'],
                    'yaw' => (float)$row['yaw'],
                    'pitch' => (float)$row['pitch'],
                    'scale' => (float)$row['scale'],
                    'skin_id' => $row['skin_id'] ?? 'Standard_Custom',
                    'skin_data' => $row['skin_data'] ?? null,
                    'cape_data' => $row['cape_data'] ?? '',
                    'geometry_name' => $row['geometry_name'] ?? 'geometry.humanoid.custom',
                    'geometry_data' => $row['geometry_data'] ?? '{
                        "geometry": {
                            "default": "geometry.humanoid.custom"
                        }
                    }'
                ];
                $this->npcs[$row['id']] = $npcData;
                
                $this->plugin->getLogger()->debug("Loaded NPC ID {$row['id']} with skin_id: {$npcData['skin_id']}, skin_data size: " . ($npcData['skin_data'] ? strlen($npcData['skin_data']) : 'NULL') . ", cape_data size: " . strlen($npcData['cape_data']));
            }
            $this->plugin->getLogger()->info("Loaded " . count($this->npcs) . " NPCs from database");
        } else {
            $this->plugin->getLogger()->info("No NPCs found in database");
        }
    }
    
    public function spawnAllNPCs(): void {
        $count = 0;
        $worldManager = $this->plugin->getServer()->getWorldManager();
        
        
        foreach ($worldManager->getWorlds() as $world) {
            foreach ($world->getEntities() as $entity) {
                if ($entity instanceof PillarsNPC) {
                    $entity->flagForDespawn();
                }
            }
        }
        $this->knownNPCs = [];

        foreach ($this->npcs as $id => $npcData) {
            $worldName = $npcData['world'] ?? '';
            $worldName = trim($worldName);

            if (!$worldManager->isWorldLoaded($worldName)) {
                try {
                    $worldManager->loadWorld($worldName, true);
                    $this->plugin->getLogger()->info("Loaded world '$worldName' for NPC spawning");
                } catch (\Exception $e) {
                    $this->plugin->getLogger()->error("Failed to load world '$worldName': " . $e->getMessage());
                    continue;
                }
            }

            $world = $worldManager->getWorldByName($worldName);
            if ($world === null) {
                $this->plugin->getLogger()->error("World '$worldName' not found for NPC at x: {$npcData['x']}, y: {$npcData['y']}, z: {$npcData['z']}");
                continue;
            }

            $location = new Location(
                (float)$npcData['x'],
                (float)$npcData['y'],
                (float)$npcData['z'],
                $world,
                (float)($npcData['yaw'] ?? 0),
                (float)($npcData['pitch'] ?? 0)
            );

            $nbt = CompoundTag::create()
                ->setString("CustomName", "§4Pillars Minigame\n§7Click To Join")
                ->setByte("CustomNameVisible", 1)
                ->setByte("NoAI", 1)
                ->setByte("Silent", 1)
                ->setByte("Invulnerable", 1)
                ->setByte("NoGravity", 1)
                ->setByte("Immobile", 1)
                ->setFloat("Scale", (float)($npcData['scale'] ?? 1.5))
                ->setTag("Pos", new ListTag([
                    new FloatTag((float)$npcData['x']),
                    new FloatTag((float)$npcData['y']),
                    new FloatTag((float)$npcData['z'])
                ]))
                ->setTag("Rotation", new ListTag([
                    new FloatTag((float)($npcData['yaw'] ?? 0)),
                    new FloatTag((float)($npcData['pitch'] ?? 0))
                ]));

            try {
                
                $skinData = $npcData['skin_data'] ?? str_repeat("\x6D\x4E\x3A\xFF", 64 * 64); 
                $skin = new \pocketmine\entity\Skin(
                    $npcData['skin_id'] ?? 'Standard_Custom',
                    $skinData,
                    $npcData['cape_data'] ?? '',
                    $npcData['geometry_name'] ?? 'geometry.humanoid.custom',
                    $npcData['geometry_data'] ?? '{
                        "geometry": {
                            "default": "geometry.humanoid.custom"
                        }
                    }'
                );
                
                if (strlen($skinData) < 64 * 64 * 4) {
                    $this->plugin->getLogger()->warning("Invalid skin_data size for NPC ID $id: " . strlen($skinData) . " bytes, using fallback skin");
                    $skin = new \pocketmine\entity\Skin(
                        'Standard_Custom',
                        str_repeat("\x6D\x4E\x3A\xFF", 64 * 64),
                        '',
                        'geometry.humanoid.custom',
                        '{
                            "geometry": {
                                "default": "geometry.humanoid.custom"
                            }
                        }'
                    );
                }
                
                $entity = new PillarsNPC($location, $skin, $nbt);
                $entity->setNameTag("§4Pillars Minigame\n§7Click To Join");
                $entity->setNameTagAlwaysVisible(true);
                $entity->setScale((float)($npcData['scale'] ?? 1.5));
                $entity->setNoClientPredictions(true);
                $entity->setCanSaveWithChunk(false);
                
                $entity->spawnToAll();
                $this->knownNPCs[] = $entity;
                $count++;
                
                $this->plugin->getLogger()->info("Spawned NPC at x: {$npcData['x']}, y: {$npcData['y']}, z: {$npcData['z']} in world: $worldName");
                $this->plugin->getLogger()->info("NPC ID: $id, Entity ID: " . $entity->getId());
                
            } catch (\Exception $e) {
                $this->plugin->getLogger()->error("Failed to spawn NPC at x: {$npcData['x']}, y: {$npcData['y']}, z: {$npcData['z']} in world: $worldName: " . $e->getMessage());
            }
        }
        $this->plugin->getLogger()->info("Spawned $count NPCs on server start");
    }
    
    public function cleanup(): void {
        if ($this->rotationTask !== null) {
            $this->rotationTask->cancel();
        }
        if ($this->database !== null) {
            $this->database->close();
        }
    }
}