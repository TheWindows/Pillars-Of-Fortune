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
use TheWindows\Pillars\Main;
use TheWindows\Pillars\Entity\PillarsNPC;
use SQLite3;

class NPCManager {
    
    private $plugin;
    private $npcs = [];
    private $knownNPCs = [];
    private $rotationTask;
    private $database;
    private $lookAtPlayers = [];
    private $particlesEnabled = true;
    private $particleStyle = 'rotating_ring';
    private $particleColor = 7;
    private $particleSpeed = 5;
    private $particleDensity = 6;
    private $colors = [];
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->initColors();
        $this->loadConfig();
        $this->initDatabase();
        $this->loadNPCs();
        $this->spawnAllNPCs();
        $this->startRotationTask();
    }
    
    private function initColors(): void {
        $this->colors = [
            new Color(255, 0, 0),
            new Color(0, 255, 0),
            new Color(0, 0, 255),
            new Color(255, 255, 0),
            new Color(255, 0, 255),
            new Color(255, 165, 0),
            new Color(255, 255, 255),
        ];
    }
    
    private function loadConfig(): void {
        $config = $this->plugin->getConfig();
        $settings = $config->get("settings", []);
        $particleSettings = $settings["npc_particles"] ?? [];
        $this->particlesEnabled = $particleSettings["enabled"] ?? true;
        $this->particleStyle = $particleSettings["style"] ?? 'rotating_ring';
        $this->particleColor = $particleSettings["color"] ?? 7;
        $this->particleSpeed = $particleSettings["speed"] ?? 5;
        $this->particleDensity = $particleSettings["density"] ?? 6;
    }
    
    public function saveConfig(): void {
        $config = $this->plugin->getConfig();
        $settings = $config->get("settings", []);
    
        if (!isset($settings["npc_particles"])) {
            $settings["npc_particles"] = [];
        }
    
        $settings["npc_particles"]["enabled"] = $this->particlesEnabled;
        $settings["npc_particles"]["style"] = $this->particleStyle;
        $settings["npc_particles"]["color"] = $this->particleColor;
        $settings["npc_particles"]["speed"] = $this->particleSpeed;
        $settings["npc_particles"]["density"] = $this->particleDensity;
    
        $config->set("settings", $settings);
        $config->save();
    }
    
    public function setParticlesEnabled(bool $enabled): void {
        $this->particlesEnabled = $enabled;
        $this->saveConfig();
    }
    
    public function isParticlesEnabled(): bool {
        return $this->particlesEnabled;
    }
    
    public function setParticleStyle(string $style): void {
        $this->particleStyle = $style;
    }
    
    public function getParticleStyle(): string {
        return $this->particleStyle;
    }
    
    public function getParticleStyleIndex(): int {
        $styles = ['rotating_ring', 'spiral', 'double_helix', 'pulse', 'rain', 'crown'];
        return array_search($this->particleStyle, $styles) ?: 0;
    }
    
    public function setParticleColor(int $color): void {
        $this->particleColor = $color;
    }
    
    public function getParticleColorIndex(): int {
        return $this->particleColor;
    }
    
    public function setParticleSpeed(float $speed): void {
        $this->particleSpeed = (int)$speed;
    }
    
    public function getParticleSpeed(): int {
        return $this->particleSpeed;
    }
    
    public function setParticleDensity(int $density): void {
        $this->particleDensity = $density;
    }
    
    public function getParticleDensity(): int {
        return $this->particleDensity;
    }
    
    public function getParticleColor(int $index = -1): Color {
        if ($this->particleColor === 7) {
            $rainbowColors = [
                new Color(255, 0, 0),
                new Color(255, 127, 0),
                new Color(255, 255, 0),
                new Color(0, 255, 0),
                new Color(0, 0, 255),
                new Color(75, 0, 130),
                new Color(148, 0, 211)
            ];
            return $rainbowColors[$index % count($rainbowColors)];
        }
        return $this->colors[$this->particleColor] ?? $this->colors[0];
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
        $this->rotationTask = $this->plugin->getScheduler()->scheduleRepeatingTask(new class($this) extends Task {
            private $npcManager;
            private int $tick = 0;
            
            public function __construct(NPCManager $npcManager) {
                $this->npcManager = $npcManager;
            }
            
            public function onRun(): void {
                $this->tick++;
                
                $this->npcManager->cleanKnownNPCs();
                foreach ($this->npcManager->getKnownNPCs() as $entity) {
                    $this->npcManager->updateNPCRotations($entity);
                    if ($this->tick % 2 === 0 && $this->npcManager->isParticlesEnabled()) {
                        $this->spawnParticles($entity);
                    }
                }
            }
            
            private function spawnParticles(Entity $entity): void {
                $pos = $entity->getPosition();
                $world = $entity->getWorld();
                $style = $this->npcManager->getParticleStyle();
                $speed = $this->npcManager->getParticleSpeed() / 2;
                $density = $this->npcManager->getParticleDensity();
                
                switch($style) {
                    case 'rotating_ring':
                        $this->spawnRotatingRing($pos, $world, $density, $speed);
                        break;
                    case 'spiral':
                        $this->spawnSpiral($pos, $world, $density, $speed);
                        break;
                    case 'double_helix':
                        $this->spawnDoubleHelix($pos, $world, $density, $speed);
                        break;
                    case 'pulse':
                        $this->spawnPulse($pos, $world, $density, $speed);
                        break;
                    case 'rain':
                        $this->spawnRain($pos, $world, $density, $speed);
                        break;
                    case 'crown':
                        $this->spawnCrown($pos, $world, $density, $speed);
                        break;
                }
            }
            
            private function spawnRotatingRing(Vector3 $pos, $world, int $density, float $speed): void {
                for ($i = 0; $i < $density; $i++) {
                    $angle = ($i * (360 / $density)) + ($this->tick * $speed);
                    $radians = deg2rad($angle);
                    $x = 0.8 * cos($radians);
                    $z = 0.8 * sin($radians);
                    $yOffset = sin(deg2rad($this->tick * 6)) * 0.5;
                    
                    $ppos = new Vector3($pos->x + $x, $pos->y + 1.2 + $yOffset, $pos->z + $z);
                    $color = $this->npcManager->getParticleColor($i);
                    $world->addParticle($ppos, new DustParticle($color));
                }
            }
            
            private function spawnSpiral(Vector3 $pos, $world, int $density, float $speed): void {
                for ($i = 0; $i < $density; $i++) {
                    $progress = $i / $density;
                    $angle = ($progress * 360 * 2) + ($this->tick * $speed);
                    $radians = deg2rad($angle);
                    $x = 0.8 * cos($radians);
                    $z = 0.8 * sin($radians);
                    $yOffset = $progress * 1.5;
                    
                    $ppos = new Vector3($pos->x + $x, $pos->y + 0.5 + $yOffset, $pos->z + $z);
                    $color = $this->npcManager->getParticleColor($i);
                    $world->addParticle($ppos, new DustParticle($color));
                }
            }
            
            private function spawnDoubleHelix(Vector3 $pos, $world, int $density, float $speed): void {
                for ($i = 0; $i < $density; $i++) {
                    $progress = $i / $density;
                    $angle1 = ($progress * 360 * 2) + ($this->tick * $speed);
                    $angle2 = $angle1 + 180;
                    
                    $radians1 = deg2rad($angle1);
                    $radians2 = deg2rad($angle2);
                    
                    $x1 = 0.7 * cos($radians1);
                    $z1 = 0.7 * sin($radians1);
                    $x2 = 0.7 * cos($radians2);
                    $z2 = 0.7 * sin($radians2);
                    
                    $yOffset = $progress * 1.5;
                    
                    $ppos1 = new Vector3($pos->x + $x1, $pos->y + 0.5 + $yOffset, $pos->z + $z1);
                    $ppos2 = new Vector3($pos->x + $x2, $pos->y + 0.5 + $yOffset, $pos->z + $z2);
                    
                    $color1 = $this->npcManager->getParticleColor($i * 2);
                    $color2 = $this->npcManager->getParticleColor($i * 2 + 1);
                    
                    $world->addParticle($ppos1, new DustParticle($color1));
                    $world->addParticle($ppos2, new DustParticle($color2));
                }
            }
            
            private function spawnPulse(Vector3 $pos, $world, int $density, float $speed): void {
                $pulseSize = 0.5 + (sin(deg2rad($this->tick * $speed * 2)) * 0.3);
                
                for ($i = 0; $i < $density; $i++) {
                    $angle = ($i * (360 / $density));
                    $radians = deg2rad($angle);
                    $x = $pulseSize * cos($radians);
                    $z = $pulseSize * sin($radians);
                    
                    $ppos = new Vector3($pos->x + $x, $pos->y + 1.2, $pos->z + $z);
                    $color = $this->npcManager->getParticleColor($i);
                    $world->addParticle($ppos, new DustParticle($color));
                }
            }
            
            private function spawnRain(Vector3 $pos, $world, int $density, float $speed): void {
                for ($i = 0; $i < $density; $i++) {
                    $offset = ($i / $density) * 360;
                    $x = 1.2 * sin(deg2rad($this->tick * $speed + $offset));
                    $z = 1.2 * cos(deg2rad($this->tick * $speed + $offset));
                    $yOffset = ($this->tick % 40) / 20;
                    
                    $ppos = new Vector3($pos->x + $x, $pos->y + 2.0 - $yOffset, $pos->z + $z);
                    $color = $this->npcManager->getParticleColor($i);
                    $world->addParticle($ppos, new DustParticle($color));
                }
            }
            
            private function spawnCrown(Vector3 $pos, $world, int $density, float $speed): void {
                for ($i = 0; $i < $density; $i++) {
                    $angle = ($i * (360 / $density)) + ($this->tick * $speed);
                    $radians = deg2rad($angle);
                    
                    if ($i % 2 === 0) {
                        $x = 0.9 * cos($radians);
                        $z = 0.9 * sin($radians);
                        $yOffset = 1.5;
                    } else {
                        $x = 0.5 * cos($radians);
                        $z = 0.5 * sin($radians);
                        $yOffset = 1.0;
                    }
                    
                    $ppos = new Vector3($pos->x + $x, $pos->y + $yOffset, $pos->z + $z);
                    $color = $this->npcManager->getParticleColor($i);
                    $world->addParticle($ppos, new DustParticle($color));
                }
            }
        }, 1);
    }
    
    public function updateNPCRotations(Entity $npc): void {
        $world = $npc->getWorld();
        $players = $world->getPlayers();
        $lookRange = 15.0;
        
        foreach ($players as $player) {
            $distance = $npc->getPosition()->distance($player->getPosition());
            
            if ($distance <= $lookRange) {
                $xDiff = $player->getPosition()->x - $npc->getPosition()->x;
                $zDiff = $player->getPosition()->z - $npc->getPosition()->z;
                $yaw = rad2deg(atan2($zDiff, $xDiff)) - 90;
                
                $this->lookAtPlayers[$player->getId()][$npc->getId()] = $yaw;
            } else {
                if (isset($this->lookAtPlayers[$player->getId()][$npc->getId()])) {
                    unset($this->lookAtPlayers[$player->getId()][$npc->getId()]);
                }
            }
        }
        
        foreach ($players as $player) {
            if (isset($this->lookAtPlayers[$player->getId()][$npc->getId()])) {
                $yaw = $this->lookAtPlayers[$player->getId()][$npc->getId()];
                $npc->setRotation($yaw, 0);
            }
        }
    }
    
    public function spawnNPCsInWorld(string $worldName): void {
    $this->plugin->getLogger()->info("Attempting to spawn NPCs in world: " . $worldName);
    
    $worldManager = $this->plugin->getServer()->getWorldManager();
    $world = $worldManager->getWorldByName($worldName);
    
    if ($world === null) {
        $this->plugin->getLogger()->warning("Cannot spawn NPCs in world $worldName: world not loaded");
        return;
    }
    
    $count = 0;
    foreach ($this->npcs as $id => $npcData) {
        if ($npcData['world'] === $worldName) {
            $this->plugin->getLogger()->info("Found NPC #$id for world $worldName at {$npcData['x']}, {$npcData['y']}, {$npcData['z']}");
            
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
                $skinData = $npcData['skin_data'] ?? str_repeat("\x00\x00\x00\x00", 64 * 64);
                $skin = new \pocketmine\entity\Skin(
                    $npcData['skin_id'] ?? 'Standard_Custom',
                    $skinData,
                    $npcData['cape_data'] ?? '',
                    $npcData['geometry_name'] ?? 'geometry.humanoid.custom',
                    $npcData['geometry_data'] ?? '{"geometry":{"default":"geometry.humanoid.custom"}}'
                );
                
                $entity = new PillarsNPC($location, $skin, $nbt);
                $entity->setNameTag("§4Pillars Minigame\n§7Click To Join");
                $entity->setNameTagAlwaysVisible(true);
                $entity->setScale((float)($npcData['scale'] ?? 1.5));
                $entity->setNoClientPredictions(true);
                $entity->setCanSaveWithChunk(false);
                
                $entity->spawnToAll();
                $this->knownNPCs[] = $entity;
                $count++;
                
            } catch (\Exception $e) {
                $this->plugin->getLogger()->error("Failed to spawn NPC in world $worldName: " . $e->getMessage());
            }
        }
    }
    $this->plugin->getLogger()->info("Spawned $count NPCs in world $worldName");
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
        $this->lookAtPlayers = [];
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
                    'skin_data' => $row['skin_data'] ?? str_repeat("\x00\x00\x00\x00", 64 * 64),
                    'cape_data' => $row['cape_data'] ?? '',
                    'geometry_name' => $row['geometry_name'] ?? 'geometry.humanoid.custom',
                    'geometry_data' => $row['geometry_data'] ?? '{"geometry":{"default":"geometry.humanoid.custom"}}'
                ];
                $this->npcs[$row['id']] = $npcData;
            }
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
                } catch (\Exception $e) {
                    continue;
                }
            }

            $world = $worldManager->getWorldByName($worldName);
            if ($world === null) {
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
                $skinData = $npcData['skin_data'] ?? str_repeat("\x00\x00\x00\x00", 64 * 64);
                $skin = new \pocketmine\entity\Skin(
                    $npcData['skin_id'] ?? 'Standard_Custom',
                    $skinData,
                    $npcData['cape_data'] ?? '',
                    $npcData['geometry_name'] ?? 'geometry.humanoid.custom',
                    $npcData['geometry_data'] ?? '{"geometry":{"default":"geometry.humanoid.custom"}}'
                );
                
                $entity = new PillarsNPC($location, $skin, $nbt);
                $entity->setNameTag("§4Pillars Minigame\n§7Click To Join");
                $entity->setNameTagAlwaysVisible(true);
                $entity->setScale((float)($npcData['scale'] ?? 1.5));
                $entity->setNoClientPredictions(true);
                $entity->setCanSaveWithChunk(false);
                
                $entity->spawnToAll();
                $this->knownNPCs[] = $entity;
                $count++;
                
            } catch (\Exception $e) {
                $this->plugin->getLogger()->error("Failed to spawn NPC: " . $e->getMessage());
            }
        }
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
