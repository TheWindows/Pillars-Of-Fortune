<?php
declare(strict_types=1);
namespace TheWindows\Pillars\Commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use TheWindows\Pillars\Forms\AdminSettingsForm;
use TheWindows\Pillars\Forms\GameMenuForm;
use TheWindows\Pillars\Forms\StatsForm;
use TheWindows\Pillars\Main;

class GameCommand extends Command {

    private Main $plugin;

    public function __construct(Main $plugin) {
        parent::__construct(
            'pillars',
            'Main Pillars command',
            '/pillars [join|leave|list|stats|info|admin|npc|reset]'
        );
        $this->setPermission('pillars.join');
        $this->setAliases(['p']);
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$this->testPermission($sender)) {
            return false;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage('This command can only be used in-game.');
            return false;
        }

        if (empty($args)) {
            $sender->sendForm(GameMenuForm::createForm($this->plugin, $sender));
            return true;
        }

        match (strtolower($args[0])) {
            'join', 'j' => $this->handleJoin($sender, $args),
            'leave', 'l', 'quit' => $this->handleLeave($sender),
            'list', 'ls', 'games'=> $this->handleList($sender),
            'stats', 'stat', 's' => $sender->sendForm(StatsForm::createForm($this->plugin, $sender)),
            'info', 'i' => $this->handleInfo($sender),
            'admin', 'a' => $this->handleAdmin($sender),
            'npc', 'n' => $this->handleNpc($sender, $args),
            'reset' => $this->handleReset($sender, $args),
            default => $sender->sendMessage('§cUnknown command. Use /pillars for help.'),
        };

        return true;
    }

    private function handleJoin(Player $sender, array $args): void {
        if (!isset($args[1])) {
            $sender->sendForm(GameMenuForm::createForm($this->plugin, $sender));
            return;
        }

        $session = $this->plugin->getSessionManager()->get($sender);
        if ($session !== null && !$session->isInLobby()) {
            $sender->sendMessage('§cYou are already in a game! Use /pillars leave first.');
            return;
        }

        $gm = $this->plugin->getGameManager();
        if (!$gm->gameExists($args[1])) {
            $sender->sendMessage("§cMap '§6{$args[1]}§c' not found!");
            $games = $gm->getAvailableGames();
            if (!empty($games)) {
                $names = implode('§7, §6', array_column($games, 'world'));
                $sender->sendMessage("§aAvailable: §6{$names}");
            }
            return;
        }

        $gm->addPlayerToGame($sender, $args[1]);
    }

    private function handleLeave(Player $sender): void {
        $session = $this->plugin->getSessionManager()->get($sender);
        if ($session === null || $session->isInLobby()) {
            $sender->sendMessage('§cYou are not in any game!');
            return;
        }

        $this->plugin->getGameManager()->removePlayerFromGame($sender);
        $sender->sendMessage('§aYou have left the game!');
    }

    private function handleList(Player $sender): void {
        $games = $this->plugin->getGameManager()->getAvailableGames();
        if (empty($games)) {
            $sender->sendMessage('§cNo games available!');
            return;
        }

        $sender->sendMessage('§6§lPillars Games:');
        foreach ($games as $game) {
            $sender->sendMessage(
                "§e{$game['world']} §7- §a{$game['players']}§7/§c{$game['max_players']} §7- {$game['status']}"
            );
        }
    }

    private function handleInfo(Player $sender): void {
        $games   = $this->plugin->getGameManager()->getAvailableGames();
        $total   = count($games);
        $players = array_sum(array_column($games, 'players'));
        $desc    = $this->plugin->getDescription();

        $sender->sendMessage('§6§lPillars Plugin:');
        $sender->sendMessage("§eVersion: §a" . $desc->getVersion());
        $sender->sendMessage("§eAuthors: §a" . implode(', ', $desc->getAuthors()));
        $sender->sendMessage("§eTotal Games: §a{$total} §7| §ePlayers: §a{$players}");
    }

    private function handleAdmin(Player $sender): void {
        if (!$sender->hasPermission('pillars.admin')) {
            $sender->sendMessage('§cNo permission!');
            return;
        }

        $sender->sendForm(AdminSettingsForm::createForm($this->plugin, $sender));
    }

    private function handleNpc(Player $sender, array $args): void {
        if (!$sender->hasPermission('pillars.admin')) {
            $sender->sendMessage('§cNo permission!');
            return;
        }

        $nm     = $this->plugin->getNPCManager();
        $npcSub = strtolower($args[1] ?? '');

        match ($npcSub) {
            'create', 'c'         => $nm->createNPC($sender),
            'list', 'ls', 'l'     => $this->handleNpcList($sender),
            'remove', 'r'         => $this->handleNpcRemove($sender, $args),
            'removeall', 'ra'     => $this->handleNpcRemoveAll($sender),
            default               => $sender->sendMessage('§cUsage: /p npc <create|list|remove|removeall>'),
        };
    }

    private function handleNpcList(Player $sender): void {
        $npcs = $this->plugin->getNPCManager()->getNPCs();
        if (empty($npcs)) {
            $sender->sendMessage('§cNo NPCs found.');
            return;
        }

        $sender->sendMessage('§aNPC List:');
        foreach ($npcs as $id => $data) {
            $sender->sendMessage("§7#{$id}: {$data['world']} (§e{$data['x']}§7, §e{$data['y']}§7, §e{$data['z']}§7)");
        }
    }

    private function handleNpcRemove(Player $sender, array $args): void {
        if (!isset($args[2]) || !is_numeric($args[2])) {
            $sender->sendMessage('§cUsage: /p npc remove <index>');
            return;
        }

        $this->plugin->getNPCManager()->removeNPC((int) $args[2])
            ? $sender->sendMessage('§aNPC removed!')
            : $sender->sendMessage('§cInvalid NPC index.');
    }

    private function handleNpcRemoveAll(Player $sender): void {
        $this->plugin->getNPCManager()->removeAllNPCs();
        $sender->sendMessage('§aAll NPCs removed!');
    }

    private function handleReset(Player $sender, array $args): void {
        if (!$sender->hasPermission('pillars.admin')) {
            $sender->sendMessage('§cNo permission!');
            return;
        }

        if (empty($args[1])) {
            $sender->sendMessage('§eUsage: /pillars reset <mapname|all>');
            return;
        }

        $mapManager = $this->plugin->getMapManager();

        if ($args[1] === 'all') {
            $count = 0;
            foreach ($this->plugin->getGameManager()->getAvailableGames() as $game) {
                if ($mapManager->resetWorld($game['world'])) {
                    $count++;
                }
            }
            $sender->sendMessage("§aReset §6{$count} §amaps.");
            return;
        }

        $mapManager->resetWorld($args[1])
            ? $sender->sendMessage("§aReset map: §6{$args[1]}")
            : $sender->sendMessage("§cFailed to reset: §6{$args[1]}");
    }
}