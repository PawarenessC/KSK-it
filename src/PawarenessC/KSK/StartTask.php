<?php

namespace PawarenessC\KSK;

use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;
use function pocketmine\server;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;

class StartTask extends Task {
    public $main;

    public function __construct(KSK $main) {
        $this->main = $main;
    }

    /*
     * 待機時間 100秒
     * ゲーム時間 300秒*/
    public function onRun(): void {
        $main = $this->main;
        $main->game_finished = false;
        $game_running = $main->game_running;
        if ($main->game_running_ready) {
            if (!$game_running) {
                $main->time_start--;
                if($main->time_start >= 51 && $main->time_start <= 100) {
                    $time = $main->time_start - 50;
                    $main->sendPopup("§aマップ抽選まで §l§b{$time}§r秒");
                }else {
                    $main->sendPopup("§l§c{$main->time_start}§r§a秒後にスタート");
                }
                switch ($main->time_start) {
                    case 60:
                        $count = count(Server::getInstance()->getOnlinePlayers());
                        if ($count === 1 or $count === 0) {
                            Server::getInstance()->broadcastMessage(KSK::PREFIX_ALL."人数が少ないためゲーム開始までのカウントダウンをリセットします。");
                            $main->time_start = 100;
                        }
                    break;

                    case 50:
                        $main->playSound(KSK::SOUND_POP);
                        $main->selections_map();
                    break;

                    case 45:
                        $main->playSound(KSK::SOUND_POP);
                        Server::getInstance()->broadcastMessage(KSK::PREFIX_ALL."§c鬼の抽選を開始します。。。");
                        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
                            $main->teleportPlayer($player);
                            $player->setInvisible(true);
                        }
                    break;

                    case 35:
                        Server::getInstance()->broadcastMessage(KSK::PREFIX_ALL . "§c鬼が決定しました！");
                        $main->selections_hunter();
                        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
                            if ($main->type[$player->getName()] === KSK::PLAYER_RUNNER) {
                                $player->setNameTag("");
                            }
                            $main->teleportPlayer($player);
                        }
                    break;

                    case 30:
                        Server::getInstance()->broadcastMessage(KSK::PREFIX_ALL . "まもなく鬼ごっこが開始されます！");
                        Server::getInstance()->broadcastMessage(KSK::PREFIX_ALL . "子どもたちは鬼から逃げましょう！！");
                        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
                            if ($main->type[$player->getName()] === KSK::PLAYER_RUNNER) {
                                $player->setInvisible(false);
                                $main->removeBarrierUnder($player);
                            }
                        }
                    break;

                    case 3:
                    case 2:
                        $main->playSound(KSK::SOUND_POP);
                    break;


                    case 1:
                        $main->playSound(KSK::SOUND_POP);
                        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
                            $player->setNameTag("");
                        }
                        break;

                    case 0:
                        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
                            if ($main->type[$player->getName()] === KSK::PLAYER_RUNNER) {
                                $bread = VanillaItems::BREAD();
                                $bread->setCount(64);
                                $player->getInventory()->addItem($bread);
                            }
                            if ($main->type[$player->getName()] === KSK::PLAYER_HUNTER) {
                                $steak = VanillaItems::COOKED_CHICKEN();
                                $steak->setCount(64);
                                $player->getInventory()->addItem($steak);
                                $player->getEffects()->add(new EffectInstance(VanillaEffects::SPEED(),20 * 99999,0,true,));
                                $player->getEffects()->add(new EffectInstance(VanillaEffects::JUMP_BOOST(),20 * 99999,1,true));
                            }
                        }
                        $main->playSound(KSK::SOUND_ANVIL);
                        Server::getInstance()->broadcastMessage(KSK::PREFIX_ALL."鬼ごっこが始まりました！");
                        Server::getInstance()->broadcastMessage(KSK::PREFIX_ALL."鬼に捕まらないよう逃げ切りましょう！");
                        $main->game_running = true;
                    break;
                }
            }
        }
    }
}
