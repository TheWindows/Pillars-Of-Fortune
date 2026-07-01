<?php
declare(strict_types=1);
namespace TheWindows\Pillars\Tasks;

use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use xenialdan\apibossbar\BossBar;
use TheWindows\Pillars\Main;

class ItemDistributionTask extends Task {

    private Main $plugin;
    private array $players;
    private string $gameId;
    private int $maxPlayers;
    private int $itemInterval;
    private int $timer;
    private array $bossBars = [];

    /** @var Item[] */
    private array $itemPool = [];

    public function __construct(Main $plugin, array $players, string $gameId, int $maxPlayers) {
        $this->plugin     = $plugin;
        $this->players    = $players;
        $this->gameId     = $gameId;
        $this->maxPlayers = $maxPlayers;

        $intervalSeconds    = (int) $plugin->getConfigManager()->getMapSetting($gameId, 'item-interval', 30);
        $this->itemInterval = $intervalSeconds * 20;
        $this->timer        = $this->itemInterval;

        foreach ($players as $player) {
            if ($player->isOnline()) {
                $seconds = (int) ceil($this->timer / 20);
                $bar = new BossBar();
                $bar->setTitle("§cNext item in: §6{$seconds}s");
                $bar->setSubTitle('');
                $bar->setPercentage(1.0);
                $bar->setColor(2);
                $bar->addPlayer($player);
                $this->bossBars[$player->getId()] = $bar;
            }
        }

        $this->buildItemPool();
    }

    public function onRun(): void {
        $this->timer--;

        $alive = [];
        foreach ($this->players as $player) {
            if ($player->isOnline() && !$player->isClosed()) {
                if ($this->plugin->getSessionManager()->getState($player) === 'playing') {
                    $alive[] = $player;
                }
            }
        }
        $this->players = $alive;

        if (count($this->players) <= 1) {
            $this->plugin->getGameManager()->endGame($this->gameId);
            $this->getHandler()?->cancel();
            return;
        }

        $seconds    = (int) ceil(max(0, $this->timer) / 20);
        $percentage = max(0.0, min(1.0, $this->timer / $this->itemInterval));

        if ($this->timer <= 0) {
            $this->distributeItems();
            $this->timer = $this->itemInterval;
            $seconds     = (int) ceil($this->itemInterval / 20);
            $percentage  = 1.0;
        }

        $this->updateBossBars($percentage, "§cNext item in: §6{$seconds}s");
    }

    private function buildItemPool(): void {
        $pool = [];
        foreach (VanillaItems::getAll() as $item) {
            if ($item->getName() !== 'Air' && !$item->isNull()) {
                $pool[] = $item;
            }
        }
        foreach (VanillaBlocks::getAll() as $block) {
            $item = $block->asItem();
            if ($item->getName() !== 'Air' && !$item->isNull()) {
                $pool[] = $item;
            }
        }
        $this->filterItems($pool);
        $this->itemPool = array_values($pool);
    }

    private function filterItems(array &$itemPool): void {
        foreach ($itemPool as $index => $item) {
            if (in_array(strtolower($item->getName()), $this->plugin->getConfigManager()->getConfigValue("itemBlackList", []), true)) {
                unset($itemPool[$index]);
            }
        }
    }

    private function distributeItems(): void {
        if (empty($this->itemPool)) return;

        foreach ($this->players as $player) {
            if (!$player->isOnline()) continue;
            $item = clone $this->itemPool[array_rand($this->itemPool)];
            $item->setCount(1);
            $inventory = $player->getInventory();
            if ($inventory->canAddItem($item)) {
                $inventory->addItem($item);
                $player->sendMessage("§aYou received a §e{$item->getName()}§a!");
            } else {
                $player->getWorld()->dropItem($player->getPosition(), $item);
                $player->sendMessage("§cInventory full! §e{$item->getName()} §cdropped.");
            }
        }
    }

    private function updateBossBars(float $percentage, string $title): void {
        $onlineIds = array_map(fn(Player $p) => $p->getId(), $this->players);
        foreach ($this->bossBars as $playerId => $bar) {
            if (in_array($playerId, $onlineIds, true)) {
                $bar->setTitle($title);
                $bar->setPercentage($percentage);
                $bar->setColor(2);
            } else {
                $bar->removeAllPlayers();
                unset($this->bossBars[$playerId]);
            }
        }
    }

    public function onCancel(): void {
        foreach ($this->bossBars as $bar) {
            $bar->removeAllPlayers();
        }
        $this->bossBars = [];
    }
}
