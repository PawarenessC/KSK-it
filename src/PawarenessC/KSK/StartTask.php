<?php

namespace PawarenessC\KSK;

use pocketmine\Server;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;
use function pocketmine\server;

class StartTask extends Task {
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
        if ($game_running === false) {
            $main->time_start--;
            $main->sendPopup("§l{$main->time_start}§r§a秒後にスタート");
            switch ($main->time_start) {
                case 70:
                    Server::getInstance()->broadcastMessage(KSK::PREFIX_ALL . "鬼ごっこを開催します！");
                break;

                case 30:
                    $main->selections_hunter();
                    $main->selections_runner();
                    Server::getInstance()->broadcastMessage(KSK::PREFIX_ALL . "§c鬼が決定しました！");
                    Server::getInstance()->broadcastMessage($main->hunter_first_name . "さんです！");
                break;

                case 20:
                    $main->selections_map();
                break;

                case 0:
                    $main->game_running = true;
                break;
            }
        }
    }
}