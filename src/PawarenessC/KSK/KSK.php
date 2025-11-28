<?php
namespace PawarenessC\KSK;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\Server;
class KSK extends PluginBase implements Listener {

    public array|null $type = null;
    /*
     * 0 = 参加前
     * 1 = 逃走者
     * 2 = 鬼*/
    const PLAYER_NONE = 0;
    const PLAYER_RUNNER = 1;
    const PLAYER_HUNTER = 2;

    const MAP_TOUSOU = 0;
    public $map_tousou;
    const MAP_SEIYO = 1;
    public $map_seiyo;
    const MAP_SUPER = 2;
    public $map_super;
    const MAP_SCHOOL = 3;
    public $map_school;
    const MAP_COREPVP = 4;
    public $map_corepvp;

    public $map_now = 0;

    public $game_running = false;
    public $time_left = 420;
    public $time_start = 60;

    public $remaining_runners = 0;
    public $remaining_hunters = 0;

    const PREFIX_ALL = "§bINFO§r>> ";
    const PREFIX = "INFO>> ";

    public $hunter_first_name = "";

    public function onEnable(): void {
        $this->getScheduler()->scheduleRepeatingTask(new StartTask($this), 20);
        $this->getScheduler()->scheduleRepeatingTask(new GameTask($this), 20);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->loadMaps();

    }

    public function game_End(): void {
        $this->game_running = false;
        $this->time_left = 420;
        $this->time_start = 80;
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

    public function setSkin(Player $player): void {
        //Todo ハンターのスキンを着せたい
        //Todo 元のスキンを着せたい
    }

    public function sendPopup(string $msg = ""): void {
        $players = Server::getInstance()->getOnlinePlayers();
        foreach ($players as $player) {
            $player->sendTip($msg);
        }
    }

    public function sendMessage(string $msg = ""): void {
        $players = Server::getInstance()->getOnlinePlayers();
        foreach ($players as $player) {
            $player->sendMessage($msg);
        }
    }

    public function selections_hunter(): void {
        $players = Server::getInstance()->getOnlinePlayers();
        if (count($players) > 0) {
            $player = $players[array_rand($players)];
            $name = $player->getName();
            $this->hunter_first_name = $name;
            $this->joinTeam($player, self::PLAYER_HUNTER);
        }
    }

    public function selections_runner(): void {
        $players = Server::getInstance()->getOnlinePlayers();
        foreach ($players as $player) {
            if ($this->type[$player->getName()] === self::PLAYER_NONE) {
                $this->joinTeam($player, self::PLAYER_RUNNER);
                $player->sendMessage("らんなー");
            }
        }
    }

    public function loadMaps(): void {
        $this->map_tousou = Server::getInstance()->getWorldManager()->getWorldByName("tousou");
        $this->map_seiyo = Server::getInstance()->getWorldManager()->getWorldByName("seiyo");
        $this->map_super = Server::getInstance()->getWorldManager()->getWorldByName("super");
        $this->map_school = Server::getInstance()->getWorldManager()->getWorldByName("school");
        $this->map_corepvp = Server::getInstance()->getWorldManager()->getWorldByName("corebg");
    }

    public function selections_map(): void {
        $rand = mt_rand(0, 4);
        switch ($rand) {
            case self::MAP_TOUSOU:
                $this->sendMessage(self::PREFIX_ALL . "マップは§a初代あわふわ逃走中§rに決定しました！");
                $this->sendMessage(self::PREFIX_ALL . "謎の住宅街で逃げまくれ！");
            break;

            case self::MAP_SEIYO:
                $this->sendMessage(self::PREFIX_ALL . "マップは§5西洋§rに決定しました！");
                $this->sendMessage(self::PREFIX_ALL . "西洋風の住宅が佇む町で逃げきれますか？");
            break;

            case self::MAP_SUPER:
                $this->sendMessage(self::PREFIX_ALL . "マップは§3あわふわモール§rに決定しました！");
                $this->sendMessage(self::PREFIX_ALL . "買い物ついでに鬼ごっこ^_^");
            break;

            case self::MAP_SCHOOL:
                $this->sendMessage(self::PREFIX_ALL . "マップは§lあわふわ高校§rに決定しました！");
                $this->sendMessage(self::PREFIX_ALL . "§l迫真鬼ごっこ部！鬼ごっこの裏技");
            break;

            case self::MAP_COREPVP:
                $this->sendMessage(self::PREFIX_ALL . "マップは§6CorePvP§rに決定しました！");
                $this->sendMessage(self::PREFIX_ALL . "CorePvPをしていたマップで鬼ごっこ！");
            break;
        }
    }

    public function joinTeam(Player $player, int $team): void {
        $name = $player->getName();
        if ($team === self::PLAYER_RUNNER) {
            $this->type[$name] = self::PLAYER_RUNNER;
            $this->remaining_runners++;
        } elseif ($team === self::PLAYER_HUNTER) {
            $this->type[$name] = self::PLAYER_HUNTER;
            $this->remaining_hunters++;
        } elseif ($team === self::PLAYER_NONE) {
            $this->type[$name] = self::PLAYER_NONE;
        } else {
            Server::getInstance()->broadcastMessage(self::PREFIX_ALL . "エラー joinTeam");
        }
    }

    public function onJoin(PlayerJoinEvent $event): void {
        $event->setJoinMessage("");
        $player = $event->getPlayer();
        $name = $player->getName();
        $this->type[$name] = 0;
        Server::getInstance()->broadcastMessage(self::PREFIX_ALL . "§a{$name}§rさんが参加しました！");
    }

    public function onQuit(PlayerQuitEvent $event): void {
        $event->setQuitMessage("");
        $player = $event->getPlayer();
        $name = $player->getName();
        Server::getInstance()->broadcastMessage(self::PREFIX_ALL . "§a{$name}§rさんが退出しました！");
        if ($this->type[$name] === self::PLAYER_RUNNER) {
            $this->remaining_runners--;
        } else
            if ($this->type[$name] === self::PLAYER_HUNTER) {
                $this->remaining_hunters--;
            }
    }

    public function onDamage(EntityDamageEvent $event): void {
        //ここはもう完成かも
        if ($event instanceof EntityDamageByEntityEvent) {
            $runner = $event->getEntity();
            $hunter = $event->getDamager();
            if ($runner instanceof Player && $hunter) {
                $runner_name = $runner->getName();
                $hunter_name = $hunter->getName();
                if ($this->type[$runner_name] === self::PLAYER_RUNNER && $this->type[$hunter_name] === self::PLAYER_HUNTER && $this->game_running) { //殴った人が鬼 殴られた人は逃走者 ゲームは進行中
                    $this->type[$runner_name] = self::PLAYER_HUNTER; //鬼にする
                    $runner->sendMessage(self::PREFIX . "§c{$hunter_name}§rにタッチされてしまった！");
                    $runner->sendMessage(self::PREFIX . "§c鬼になった。。。");
                    $hunter->sendMessage(self::PREFIX . "§a{$runner_name}§rを確保した！");
                    Server::getInstance()->broadcastMessage(self::PREFIX_ALL . "§c{$hunter_name}§rが§a{$runner_name}§rを捕まえた！");
                    $this->remaining_runners--;
                    $this->remaining_hunters++;

                    if ($this->remaining_runners <= 0 or $this->remaining_runners === 0) {
                        $this->game_End();
                    } else {
                        Server::getInstance()->broadcastMessage(self::PREFIX_ALL . "子ども 残り§e{$this->remaining_runners}§r人");
                    }
                }
            }
            $event->cancel();
        }
    }
}
