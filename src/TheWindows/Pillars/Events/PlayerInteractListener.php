<?php
namespace TheWindows\Pillars\Events;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use pocketmine\item\VanillaItems;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\player\Player;
use pocketmine\world\Position;
use TheWindows\Pillars\Main;
use pocketmine\block\utils\DyeColor;
use TheWindows\Pillars\Forms\GameMenuForm;

class PlayerInteractListener implements Listener {
    
    private $plugin;
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
    
    public function onInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $action = $event->getAction();
        
        
    
        
        
        if (in_array($action, [0, 1, 2, 3])) {
            $isSpectatorItem = $this->handleSpectatorItems($player, $item);
            if ($isSpectatorItem) {
                $event->cancel();
                return;
            }
        }
        
        
        $playerState = $this->plugin->getGameManager()->checkPlayerState($player);
        if ($playerState === 'spectating') {
            $event->cancel();
            return;
        }
        
        
    $lobbyWorld = "world";
    if ($player->getWorld()->getFolderName() === $lobbyWorld) {
        return;
    }
        
        
        if ($action === 1 && $player->hasPermission("pillars.admin")) {
            $block = $event->getBlock();
            
            if ($item->equals(VanillaItems::BLAZE_ROD(), true, false)) {
                $worldName = $player->getWorld()->getFolderName();
                
                $maxPlayers = $this->plugin->getGameManager()->getMapMaxPlayers($worldName);
                
                $position = new Position(
                    $block->getPosition()->getX() + 0.5,
                    $block->getPosition()->getY() + 1,
                    $block->getPosition()->getZ() + 0.5,
                    $block->getPosition()->getWorld()
                );
                
                if ($this->plugin->getSpawnManager()->addSpawnPoint($worldName, $position)) {
                    $currentSpawns = count($this->plugin->getSpawnManager()->getSpawnPointsForWorld($worldName));
                    $player->sendMessage("§aSpawn point added! (§6" . $currentSpawns . "/{$maxPlayers}§a)");
                    
                    if ($currentSpawns >= $maxPlayers) {
                        $player->sendMessage("§a§lSUCCESS! §r§aAll {$maxPlayers} spawn points have been set.");
                        $this->teleportToLobbyAndCleanup($player);
                    }
                } else {
                    $player->sendMessage("§cMaximum of {$maxPlayers} spawn points reached!");
                }
                
                $event->cancel();
                
            } elseif ($item->equals(VanillaItems::REDSTONE_DUST(), true, false)) {
                $worldName = $player->getWorld()->getFolderName();
                
                $position = new Position(
                    $block->getPosition()->getX() + 0.5,
                    $block->getPosition()->getY() + 1,
                    $block->getPosition()->getZ() + 0.5,
                    $block->getPosition()->getWorld()
                );
                
                if ($this->plugin->getSpawnManager()->removeSpawnPoint($worldName, $position)) {
                    $currentSpawns = count($this->plugin->getSpawnManager()->getSpawnPointsForWorld($worldName));
                    $maxPlayers = $this->plugin->getGameManager()->getMapMaxPlayers($worldName);
                    $player->sendMessage("§cSpawn point removed! (§6" . $currentSpawns . "/{$maxPlayers}§c)");
                } else {
                    $player->sendMessage("§cNo spawn point found at this location!");
                }
                $event->cancel();
            }
        }
    }
    
    public function onPacketReceive(DataPacketReceiveEvent $event): void {
        $packet = $event->getPacket();
        $player = $event->getOrigin()->getPlayer();
        
        if ($player === null) {
            return;
        }
        
        $playerState = $this->plugin->getGameManager()->checkPlayerState($player);
        if ($playerState === 'spectating') {
            if ($packet instanceof InventoryTransactionPacket && $packet->trData instanceof UseItemTransactionData) {
                $item = $player->getInventory()->getItemInHand();
                if ($this->handleSpectatorItems($player, $item)) {
                    $event->cancel();
                }
            }
        }
    }
    
    private function handleSpectatorItems(Player $player, \pocketmine\item\Item $item): bool {
        $playerState = $this->plugin->getGameManager()->checkPlayerState($player);
        if ($playerState !== 'spectating') {
            return false;
        }
        
        
        $leaveGameItem = VanillaBlocks::BED()->setColor(DyeColor::RED())->asItem()->setCustomName("§cLeave Game");
        $spectateItem = VanillaItems::COMPASS()->setCustomName("§bSpectate Players");
        
        
        if ($item->equals($leaveGameItem, true, true) && $item->getCustomName() === "§cLeave Game") {
            $this->plugin->getGameManager()->removePlayerFromGame($player);
            $player->sendMessage("§aYou left the game!");
            return true;
        }
        
        
        if ($item->equals($spectateItem, true, true) && $item->getCustomName() === "§bSpectate Players") {
            $gameId = $this->plugin->getGameManager()->getPlayerGame($player);
            if ($gameId !== null) {
                $this->plugin->getGameManager()->showSpectatorMenu($player);
            } else {
            }
            return true;
        }
        
        return false;
    }
    
    private function teleportToLobbyAndCleanup(Player $player): void {
    $player->getInventory()->remove(VanillaItems::BLAZE_ROD());
    $player->getInventory()->remove(VanillaItems::REDSTONE_DUST());
    
    $lobbyWorld = "world";
    $lobby = $this->plugin->getServer()->getWorldManager()->getWorldByName($lobbyWorld);
    
    if ($lobby !== null) {
        $player->teleport($lobby->getSpawnLocation());
        $player->sendMessage("§aMap setup completed! You've been returned to the lobby.");
    } else {
        $defaultWorld = $this->plugin->getServer()->getWorldManager()->getDefaultWorld();
        if ($defaultWorld !== null) {
            $player->teleport($defaultWorld->getSpawnLocation());
            $player->sendMessage("§aMap setup completed! You've been returned to the default world.");
        }
    }
}
    
    public function onEntityDamage(EntityDamageByEntityEvent $event): void {
        $entity = $event->getEntity();
        $damager = $event->getDamager();
        
        if ($damager instanceof Player && $entity instanceof Entity) {
            $entityName = $entity->getNameTag();
            
            if (strpos($entityName, "Pillars Minigame") !== false) {
                $event->cancel();
                
                $form = GameMenuForm::createForm($this->plugin, $damager);
                $damager->sendForm($form);
            }
        }
    }
    
    public function onPlayerDropItem(PlayerDropItemEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $playerState = $this->plugin->getGameManager()->checkPlayerState($player);
        
        
        if ($playerState === 'spectating' || $playerState === 'playing') {
            $leaveGameItem = VanillaBlocks::BED()->setColor(DyeColor::RED())->asItem()->setCustomName("§cLeave Game");
            $spectateItem = VanillaItems::COMPASS()->setCustomName("§bSpectate Players");
            
            if (
                ($item->equals($spectateItem, true, true) && $item->getCustomName() === "§bSpectate Players") ||
                ($item->equals($leaveGameItem, true, true) && $item->getCustomName() === "§cLeave Game")
            ) {
                $event->cancel();
            }
        }
    }
    
    public function onInventoryTransaction(InventoryTransactionEvent $event): void {
        $transaction = $event->getTransaction();
        $player = $transaction->getSource();
        $playerState = $this->plugin->getGameManager()->checkPlayerState($player);
        
        
        if ($playerState === 'spectating' || $playerState === 'playing') {
            $leaveGameItem = VanillaBlocks::BED()->setColor(DyeColor::RED())->asItem()->setCustomName("§cLeave Game");
            $spectateItem = VanillaItems::COMPASS()->setCustomName("§bSpectate Players");
            
            foreach ($transaction->getActions() as $action) {
                $item = $action->getTargetItem();
                if (
                    ($item->equals($spectateItem, true, true) && $item->getCustomName() === "§bSpectate Players") ||
                    ($item->equals($leaveGameItem, true, true) && $item->getCustomName() === "§cLeave Game")
                ) {
                    $event->cancel();
                    break;
                }
            }
        }
    }
    
    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        
        
        $playerState = $this->plugin->getGameManager()->checkPlayerState($player);
        if ($playerState === 'spectating') {
            $event->cancel();
        }
    }
    
    public function onBlockPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();
        
        
        $playerState = $this->plugin->getGameManager()->checkPlayerState($player);
        if ($playerState === 'spectating') {
            $event->cancel();
        }
    }
}