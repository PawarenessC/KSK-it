<?php
namespace PawarenessC\KSK;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\Server;
class KSK extends PluginBase implements Listener{

    public array | null $type = null;
    /*
     * 0 = 参加前
     * 1 = 逃走者
     * 2 = 鬼*/
    const int PLAYER_NONE = 0;
    const int PLAYER_RUNNER = 1;
    const int PLAYER_HUNTER = 2;
    public bool $game_running = false;
    public int $time_left = 0;
    public int $time_start = 0;

    public int $remaining_runners = 0;
    public int $remaining_hunters = 0;

    const string PREFIX_ALL = "§bINFO§r>> ";
    const string PREFIX = "INFO>> ";

    public function onEnable() : void {
        $this->getScheduler()->scheduleRepeatingTask(new GameTask($this),20);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

    }
    public function game_End() : void{
        $this->game_running = false;
        $this->time_left = 0;
        $this->time_start = 0;
        $this->remaining_runners = 0;
        $this->remaining_hunters = 0;
        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            if ($player instanceof Player) {
                $name = $player->getName();
                $player->setNameTag($name);
                $this->type[$name] = self::PLAYER_NONE;
                //Todo ロビーに戻す
                //ToDo スキン戻す
            }
        }
    }
    public function setSkin(Player $player) : void{
        //Todo ハンターのスキンを着せたい
        //Todo 元のスキンを着せたい
    }

    public function onJoin(PlayerJoinEvent $event) : void {
        $event->setJoinMessage("");
        $player = $event->getPlayer();
        $name = $player->getName();
        $this->type[$name] = 0;
        Server::getInstance()->broadcastMessage(self::PREFIX_ALL."§a{$name}§eさんが参加しました！");
    }
    public function onQuit(PlayerQuitEvent $event) : void {
        $event->setQuitMessage("");
        $player = $event->getPlayer();
        $name = $player->getName();
        $this->type[$name] = 0;
        Server::getInstance()->broadcastMessage(self::PREFIX_ALL."§a{$name}§eさんが退出しました！");
    }

    public function onDamage(EntityDamageEvent $event) : void {
        //ここはもう完成かも
        if($event instanceof EntityDamageByEntityEvent){
            $runner = $event->getEntity();
            $hunter = $event->getDamager();
            if($runner instanceof Player && $hunter instanceof Player){
                $runner_name = $runner->getName();
                $hunter_name = $runner->getName();
                if ($this->type[$runner_name] === self::PLAYER_RUNNER && $this->type[$hunter_name] === self::PLAYER_HUNTER && $this->game_running){ //殴った人が鬼 殴られた人は逃走者 ゲームは進行中
                    $this->type[$runner_name] = self::PLAYER_HUNTER; //鬼にする
                    $runner->sendMessage(self::PREFIX."§c{$hunter_name}§rにタッチされてしまった！");
                    $runner->sendMessage(self::PREFIX."§c鬼になった。。。");
                    $hunter->sendMessage(self::PREFIX."§a{$runner_name}§rを確保した！");
                    Server::getInstance()->broadcastMessage(self::PREFIX_ALL."§c{$hunter_name}§rが§a{$runner_name}§rを捕まえた！");
                    $this->remaining_runners--;
                    $this->remaining_hunters++;
                    //ToDo リピーティングたすくで5秒間動けなくしてやる
                    //ToDo ↑HIVEみたいな演出もつける
                    if ($this->remaining_runners > 0){
                        $this->game_End();
                    }else{
                        Server::getInstance()->broadcastMessage(self::PREFIX_ALL."子ども 残り§e{$this->remaining_runners}§r人");
                    }
                }
            }
        }
    }
}