<?php

namespace PawarenessC\KSK;

use pocketmine\Server;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;

class GameTask extends Task {
    public $main;

    public function __construct(KSK $main) {
        $this->main = $main;
    }

    /*
     * 待機時間 100秒
     * ゲーム時間 500秒*/
    public function onRun(): void {
        $main = $this->main;
        $game_running = $main->game_running;
        if ($game_running) {
            $main->time_left--;
            $blinkColor = ($main->time_left % 2 === 0) ? "§l" : "§7";
            $minutes = intdiv($main->time_left, 60) % 60;
            $seconds = str_pad($main->time_left % 60, 2, "0", STR_PAD_LEFT);
            $main->sendPopup("   §f残り時間:§l§f{$blinkColor}{$minutes}§r§b:§r{$blinkColor}{$seconds}§r§e§r\n  §l§c鬼 " . $main->remaining_hunters . "§r vs §l§b子ども " . $main->remaining_runners . "\n");
            if(!$main->game_finished) {
                if ($main->remaining_runners <= 0) {
                    Server::getInstance()->getLogger()->info("RUNNERS: {$main->remaining_runners}, HUNTERS: {$main->remaining_hunters}");
                    $main->game_finished = true;
                    $main->bad_end();
                }
            }
            if(!$main->game_finished) {
                if ($main->remaining_hunters <= 0) {
                    Server::getInstance()->getLogger()->info("RUNNERS: {$main->remaining_runners}, HUNTERS: {$main->remaining_hunters}");
                    $main->game_finished = true;
                    $main->wtf_end();
                }
            }
            switch ($this->main->time_left) {
                case 440:
                case 380:
                case 320:
                case 260:
                case 200:
                case 140:
                case 80:
                case 30:
                    Server::getInstance()->broadcastMessage("=-=-=-=-=-§c途中結果発表§a！§f-=-=-=-=-=");
                    Server::getInstance()->broadcastMessage("残り{$main->remaining_runners}人！");
                    Server::getInstance()->broadcastMessage("逃げ残っている者は、、、");
                    foreach (Server::getInstance()->getOnlinePlayers() as $player) {
                        $name = $player->getName();
                        if ($main->type[$name] === KSK::PLAYER_RUNNER){
                            $main->sendMessage("§b{$name}");
                        }
                    }
            break;

                case 0:
                    $main->happy_end();
                break;
            }
        }
    }
}
