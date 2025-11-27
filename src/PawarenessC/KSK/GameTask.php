<?php

namespace PawarenessC\KSK;

use PawarenessC\KSK\KSK;
use pocketmine\scheduler\Task;

class GameTask extends Task{
    public KSK $main;
    public function __construct(KSK $main){
    }
    /*
     * 待機時間 80秒
     * ゲーム時間 420秒*/
    public function onRun() : void{
        $main = $this->main;
        $game_running = $main->game_running;
        if(!$game_running){
            $main->time_start--;
            //何秒後にスタートと表示
            //残り30秒ぐらいでマップ抽選
            //残り10秒ぐらいでテレポート スプラみたいにハンターだけを見下ろす
        }
        if($game_running){
            $main->time_left--;
            //60秒ごとに生きてる人の名前公表しますか
        }
    }

}