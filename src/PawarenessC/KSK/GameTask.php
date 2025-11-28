<?php

namespace PawarenessC\KSK;

use pocketmine\Server;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;
use function pocketmine\server;

class GameTask extends Task {
    public $main;

    public function __construct(KSK $main) {
        $this->main = $main;
    }

    /*
     * 待機時間 80秒
     * ゲーム時間 420秒*/
    public function onRun(): void {
        $main = $this->main;
        $game_running = $main->game_running;
        if ($game_running) {
            $main->time_left--;
            $blinkColor = ($main->time_left % 2 === 0) ? "§l" : "§7§o";
            $minutes = floor(($main->time_left / 60) % 60);
            $seconds = str_pad($main->time_left % 60, 2, "0", STR_PAD_LEFT);
            $main->sendPopup("§f残り時間:§l§f{$blinkColor}{$minutes}§r§b:§r{$blinkColor}{$seconds}§r§e§r\n     §l§a鬼" . $main->remaining_hunters . " §cvs §b子ども " . $main->remaining_runners . "\n\n");
        }
    }
}
