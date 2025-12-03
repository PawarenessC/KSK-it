<?php
namespace PawarenessC\KSK;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\entity\Skin;
use pocketmine\world\Position;
use pocketmine\math\Vector3;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\sound\ClickSound;
use pocketmine\world\World;
use pocketmine\world\particle\HugeExplodeParticle;
use pocketmine\world\sound\ExplodeSound;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\sound\PopSound;
use pocketmine\world\sound\AnvilUseSound;
use pocketmine\world\sound\AnvilFallSound;
use pocketmine\world\sound\XpLevelUpSound;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\object\ItemEntity;

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
    const MAP_NARITA = 5;
    public $map_narita;

    public $map_now = 0;

    public $game_running = false;
    public $game_running_ready = false;
    public $game_finished = false;
    public $time_left = 300;
    public $time_start = 80;

    public $remaining_runners = 0;
    public $remaining_hunters = 0;

    const PREFIX_ALL = "§bINFO§r>> ";
    const PREFIX = "INFO>> ";

    public $hunter_first_name = "";

    public $savedSkins = [];

    public $immobile = [];

    const SOUND_POP = 0;
    const SOUND_ANVIL = 1;
    const SOUND_ANVIL_USE = 2;
    const SOUND_LEVEL_UP = 3;
    const SOUND_CLICK = 4;

    private $countdownSeconds = [];


    public function onEnable(): void {
        if (!is_dir($this->getDataFolder())) {
            @mkdir($this->getDataFolder(), 0777, true);
        }

        // skinsフォルダがない場合 → 作成
        $skinsFolder = $this->getDataFolder() . "skins/";
        if (!is_dir($skinsFolder)) {
            @mkdir($skinsFolder, 0777, true);
            $this->getLogger()->info("skinsフォルダを作成しました！");
        }

        $this->getLogger()->info("KSK プラグインが有効化されました！");
        $this->getScheduler()->scheduleRepeatingTask(new StartTask($this), 20);
        $this->getScheduler()->scheduleRepeatingTask(new GameTask($this), 20);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->loadMaps();

    }

    public function happy_end(): void {
        Server::getInstance()->broadcastMessage(self::PREFIX_ALL."§c結果発表！");
        Server::getInstance()->broadcastMessage(self::PREFIX_ALL."ゲームが終了しました！");
        Server::getInstance()->broadcastMessage(self::PREFIX_ALL."§b子どもの勝ち！！！");
        Server::getInstance()->broadcastMessage(self::PREFIX_ALL."逃げ切ったのは{$this->remaining_runners}人");
        Server::getInstance()->broadcastMessage(self::PREFIX_ALL."逃げ切った人は、、、");
        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            if ($this->type[$player->getName()] === self::PLAYER_RUNNER){

                $this->sendMessage("§l{$player->getName()}");
            }
        }
        $this->game_End();
    }

    public function bad_end(): void {
        Server::getInstance()->broadcastMessage(self::PREFIX_ALL."§c結果発表！");
        Server::getInstance()->broadcastMessage(self::PREFIX_ALL."ゲームが終了しました！");
        Server::getInstance()->broadcastMessage(self::PREFIX_ALL."§c鬼の勝ち！！！！");
        Server::getInstance()->broadcastMessage(self::PREFIX_ALL."§l§d{$this->hunter_first_name}§rは§b最速§rかつ§c最強§rの§4鬼§rだった。。。");
        $this->game_End();
    }

    public function wtf_end(): void {
        Server::getInstance()->broadcastMessage(self::PREFIX_ALL."鬼が居なくなってしまったみたいだ。。。");
        Server::getInstance()->broadcastMessage(self::PREFIX_ALL."ゲームを終了します。");
        $this->game_End();
    }

    public function game_End(): void {
        $this->game_running = false;
        $this->game_finished = true;
        $this->time_left = 300;
        $this->time_start = 80;
        $this->remaining_runners = 0;
        $this->remaining_hunters = 0;
        $this->hunter_first_name = "";
        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            if ($player instanceof Player) {
                $name = $player->getName();
                $player->setNameTag($name);
                $player->getInventory()->clearAll();
                $this->joinTeam($player, self::PLAYER_NONE);
                $player->getEffects()->clear();
                $worldManager = Server::getInstance()->getWorldManager();
                $world = $worldManager->getWorldByName("world");
                $spawn = $world->getSpawnLocation();
                $player->teleport($spawn);
                $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player) {
                    $skin = $player->getSkin();
                    $player->setSkin($skin);
                    $player->sendSkin();
                }), 5);
                $this->restoreSkin($player);
            }
        }
        $this->playSound(self::SOUND_LEVEL_UP);
    }

    public function teleportPlayer(Player $player): void {
        $name = $player->getName();
        $positions_runner = [
            "tousou" => [246, 6, 353],
            "seiyo" => [180, 6, 185],
            "super" => [85, 6, 171],
            "school" => [142, 6, 127],
            "corebg" => [30, 12, 37]
        ];
        $positions_hunter = [
            "tousou" => [246, 4, 357],
            "seiyo" => [185, 4, 185],
            "super" => [81, 4, 175],
            "school" => [136, 4, 127],
            "corebg" => [35, 9, 37]
        ];
        $worldManager = Server::getInstance()->getWorldManager();
        switch ($this->map_now) {
            case self::MAP_TOUSOU;
                $world = $worldManager->getWorldByName("tousou");
                if ($this->type[$name] === self::PLAYER_NONE or $this->type[$name] === self::PLAYER_RUNNER) {
                    [$x, $y, $z] = $positions_runner["tousou"];
                    $blockPos = new Vector3($x, $y - 1, $z);
                    $barrier = VanillaBlocks::BARRIER();
                    $world->setBlock($blockPos, $barrier, false);
                } elseif ($this->type[$name] === self::PLAYER_HUNTER) {
                    [$x, $y, $z] = $positions_hunter["tousou"];
                    $player->setInvisible(false);
                }
            break;

            case self::MAP_SEIYO:
                $world = $worldManager->getWorldByName("seiyo");
                if ($this->type[$name] === self::PLAYER_NONE or $this->type[$name] === self::PLAYER_RUNNER) {
                    [$x, $y, $z] = $positions_runner["seiyo"];
                    $blockPos = new Vector3($x, $y - 1, $z);
                    $barrier = VanillaBlocks::BARRIER();
                    $world->setBlock($blockPos, $barrier, false);
                } elseif ($this->type[$name] === self::PLAYER_HUNTER) {
                    [$x, $y, $z] = $positions_hunter["seiyo"];
                    $player->setInvisible(false);
                }
            break;

            case self::MAP_SUPER:
                $world = $worldManager->getWorldByName("super");
                if ($this->type[$name] === self::PLAYER_NONE or $this->type[$name] === self::PLAYER_RUNNER) {
                    [$x, $y, $z] = $positions_runner["super"];
                    $blockPos = new Vector3($x, $y - 1, $z);
                    $barrier = VanillaBlocks::BARRIER();
                    $world->setBlock($blockPos, $barrier, false);
                } elseif ($this->type[$name] === self::PLAYER_HUNTER) {
                    [$x, $y, $z] = $positions_hunter["super"];
                    $player->setInvisible(false);
                }
            break;

            case self::MAP_SCHOOL:
                $world = $worldManager->getWorldByName("school");
                if ($this->type[$name] === self::PLAYER_NONE or $this->type[$name] === self::PLAYER_RUNNER) {
                    [$x, $y, $z] = $positions_runner["school"];
                    $blockPos = new Vector3($x, $y - 1, $z);
                    $barrier = VanillaBlocks::BARRIER();
                    $world->setBlock($blockPos, $barrier, false);
                } elseif ($this->type[$name] === self::PLAYER_HUNTER) {
                    [$x, $y, $z] = $positions_hunter["school"];
                    $player->setInvisible(false);
                }
            break;

            case self::MAP_COREPVP:
                $world = $worldManager->getWorldByName("corebg");
                if ($this->type[$name] === self::PLAYER_NONE or $this->type[$name] === self::PLAYER_RUNNER) {
                    [$x, $y, $z] = $positions_runner["corebg"];
                    $blockPos = new Vector3($x, $y - 1, $z);
                    $barrier = VanillaBlocks::BARRIER();
                    $world->setBlock($blockPos, $barrier, false);
                } elseif ($this->type[$name] === self::PLAYER_HUNTER) {
                    [$x, $y, $z] = $positions_hunter["corebg"];
                    $player->setInvisible(false);
                }
            break;
        }
        $player->teleport(new Position($x, $y, $z, $world));
        foreach($world->getEntities() as $entity){
            if($entity instanceof ItemEntity){
                $entity->flagForDespawn();
            }
        }
        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player) {
            $skin = $player->getSkin();
            $player->setSkin($skin);
            $player->sendSkin();
        }), 5);
    }

    public function removeBarrierUnder(Player $player): void {
        $pos = $player->getPosition();
        $world = $player->getWorld();
        $blockPos = new Vector3($pos->x, $pos->y - 1, $pos->z);
        if ($this->type[$player->getName()] === self::PLAYER_RUNNER) {
            $world->setBlock($blockPos, VanillaBlocks::AIR());
        }
    }

    public function hunterAwakening(Player $hunter): void {
        $world = $hunter->getWorld();
        $pos = $hunter->getPosition();
        $world->addSound($pos, new ExplodeSound());
        $world->addParticle($pos, new HugeExplodeParticle());
    }

    public function makeHunter(Player $player): void {
        // 元のスキン保存
        $this->savedSkins[$player->getName()] = $player->getSkin();

        // スキンファイル読み込み
        $path = $this->getDataFolder() . "skins/oni.png";
        if (!file_exists($path)) {
            $player->sendMessage("スキンファイルがありません: oni.png");
            return;
        }

        $pngData = file_get_contents($path);
        $skinData = $this->pngToSkinData($pngData);

        // スキン適用
        $skin = new Skin("HunterSkin", $skinData);
        $player->setSkin($skin);
        $player->sendSkin();
    }

    public function restoreSkin(Player $player): void {
        $name = $player->getName();
        if (isset($this->savedSkins[$name])) {
            $player->setSkin($this->savedSkins[$name]);
            $player->sendSkin();
            unset($this->savedSkins[$name]);
        }
    }

    private function pngToSkinData(string $pngData): string {
        $img = imagecreatefromstring($pngData);
        $height = imagesy($img);
        $width = imagesx($img);
        $skinData = "";

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($img, $x, $y);
                $a = ((~($color >> 24)) << 1) & 0xff;
                $r = ($color >> 16) & 0xff;
                $g = ($color >> 8) & 0xff;
                $b = $color & 0xff;
                $skinData .= chr($r) . chr($g) . chr($b) . chr($a);
            }
        }
        return $skinData;
    }

    public function sendPopup(string $msg = ""): void {
        $players = Server::getInstance()->getOnlinePlayers();
        foreach ($players as $player) {
            $player->sendActionBarMessage($msg);
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
            $hunter = $players[array_rand($players)];
            $name = $hunter->getName();
            $this->hunter_first_name = $name;
            $this->joinTeam($hunter, self::PLAYER_HUNTER);
            $this->makeHunter($hunter);
        }
        $this->hunterAwakening($hunter);
        $this->selections_runner($hunter);
        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            $player->sendTitle("§c{$name}§r","は§r§c§l鬼§r§lになった");
        }
    }

    public function selections_runner(Player $hunter): void {
        $players = Server::getInstance()->getOnlinePlayers();
        foreach ($players as $runner) {
            if ($this->type[$runner->getName()] === self::PLAYER_NONE) {
                $this->joinTeam($runner, self::PLAYER_RUNNER);
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
                $this->map_now = self::MAP_TOUSOU;
            break;

            case self::MAP_SEIYO:
                $this->sendMessage(self::PREFIX_ALL . "マップは§5西洋§rに決定しました！");
                $this->sendMessage(self::PREFIX_ALL . "西洋風の住宅が佇む町で逃げきれますか？");
                $this->map_now = self::MAP_SEIYO;
            break;

            case self::MAP_SUPER:
                $this->sendMessage(self::PREFIX_ALL . "マップは§3あわふわモール§rに決定しました！");
                $this->sendMessage(self::PREFIX_ALL . "買い物ついでに鬼ごっこ^_^");
                $this->map_now = self::MAP_SUPER;
            break;

            case self::MAP_SCHOOL:
                $this->sendMessage(self::PREFIX_ALL . "マップは§lあわふわ高校§rに決定しました！");
                $this->sendMessage(self::PREFIX_ALL . "§l迫真鬼ごっこ部！鬼ごっこの裏技");
                $this->map_now = self::MAP_SCHOOL;
            break;

            case self::MAP_COREPVP:
                $this->sendMessage(self::PREFIX_ALL . "マップは§6CorePvP§rに決定しました！");
                $this->sendMessage(self::PREFIX_ALL . "CorePvPをしていたマップで鬼ごっこ！");
                $this->map_now = self::MAP_COREPVP;
            break;
        }
    }

    public function joinTeam(Player $player, int $team): void {
        $name = $player->getName();
        if ($team === self::PLAYER_RUNNER) {
            $player->getEffects()->clear();
            $this->type[$name] = self::PLAYER_RUNNER;
            $this->remaining_runners++;
        } elseif ($team === self::PLAYER_HUNTER) {
            $this->type[$name] = self::PLAYER_HUNTER;
            $this->remaining_hunters++;
            $player->getInventory()->clearAll();
            $steak = VanillaItems::COOKED_CHICKEN();
            $steak->setCount(64);
            $player->getInventory()->addItem($steak);
        } elseif ($team === self::PLAYER_NONE) {
            $this->type[$name] = self::PLAYER_NONE;
            $player->getInventory()->clearAll();
            $player->getEffects()->clear();
        } else {
            Server::getInstance()->broadcastMessage(self::PREFIX_ALL . "エラー joinTeam");
        }
    }


    public function countdown(Player $player) :void {
        if (!$this->game_running) return;

        $name = $player->getName();
        $this->countdownSeconds[$name] = 5;

        $this->countdownTaskHandler = $this->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(function() use ($player, $name): void {

                // 🔥ゲーム停止時＆プレイヤー不在時に終了
                if(!$this->game_running || !$player->isOnline()){
                    $this->stopCountdown($name);
                    return;
                }

                // 🔥キー存在チェック
                if(!isset($this->countdownSeconds[$name])){
                    $this->stopCountdown($name);
                    return;
                }

                $seconds = $this->countdownSeconds[$name];

                switch ($seconds) {
                    case 5: $player->sendTitle("■■■■■ ■■■■■"); break;
                    case 4: $player->sendTitle("§l§e□■■■■ ■■■■□"); break;
                    case 3: $player->sendTitle("§l§c□□■■■ ■■■□□"); break;
                    case 2: $player->sendTitle("§l§c□□□■■ ■■□□□"); break;
                    case 1: $player->sendTitle("§l§c□□□□■ ■□□□□"); break;
                    case -1:
                        $this->stopCountdown($name);
                        return;
                }

                if ($seconds <= 3) {
                    $player->getWorld()->addSound($player->getLocation(), new PopSound());
                }

                $this->immobile[$name] = true;

                if ($seconds <= 0) {
                    $player->sendTitle("§l§c子どもを捕まえろ！");
                    unset($this->immobile[$name]);
                    $player->getWorld()->addSound($player->getLocation(), new AnvilFallSound());
                    $this->stopCountdown($name);
                    return;
                }

                $this->countdownSeconds[$name]--;
            }), 20
        );
    }

    private function stopCountdown(string $name): void {
        $this->countdownTaskHandler?->cancel();
        $this->countdownTaskHandler = null;
        unset($this->countdownSeconds[$name], $this->immobile[$name]);
    }

    public function playSound(int $sound_number = self::SOUND_POP): void {
        switch ($sound_number) {
            case self::SOUND_POP:
                foreach (Server::getInstance()->getOnlinePlayers() as $player) {
                    $world = $player->getWorld();
                    $world->addSound($player->getPosition(), new PopSound());
                }
            break;

            case self::SOUND_ANVIL:
                foreach (Server::getInstance()->getOnlinePlayers() as $player) {
                    $world = $player->getWorld();
                    $world->addSound($player->getPosition(), new AnvilFallSound());
                }
            break;

            case self::SOUND_ANVIL_USE:
                foreach (Server::getInstance()->getOnlinePlayers() as $player) {
                    $world = $player->getWorld();
                    $world->addSound($player->getPosition(), new AnvilUseSound());
                }
            break;

            case  self::SOUND_LEVEL_UP:
                foreach (Server::getInstance()->getOnlinePlayers() as $player) {
                    $world = $player->getWorld();
                    $world->addSound($player->getPosition(), new XpLevelUpSound(10));
                }
            break;

            case self::SOUND_CLICK:
                foreach (Server::getInstance()->getOnlinePlayers() as $player) {
                    $world = $player->getWorld();
                    $world->addSound($player->getPosition(), new ClickSound());
                }
            break;
        }
    }



    public function onLogin(PlayerLoginEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();
        $this->joinTeam($player, self::PLAYER_NONE);
    }

    public function onJoin(PlayerJoinEvent $event): void {
        $event->setJoinMessage("");
        $player = $event->getPlayer();
        $player->getEffects()->clear();
        $worldManager = Server::getInstance()->getWorldManager();
        $world = $worldManager->getWorldByName("world");
        $spawn = $world->getSpawnLocation();
        $player->teleport($spawn);
        Server::getInstance()->broadcastMessage(self::PREFIX_ALL . "§a{$player->getName()}§rさんが参加しました！");
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
                $this->restoreSkin($player);
            }
    }

    public function onDamage(EntityDamageEvent $event): void {
        if($event->getCause() === EntityDamageEvent::CAUSE_FALL){
            $event->cancel();
        }
        if ($event instanceof EntityDamageByEntityEvent) {
            $runner = $event->getEntity();
            $hunter = $event->getDamager();
            if ($runner instanceof Player && $hunter instanceof Player) {
                $runner_name = $runner->getName();
                $hunter_name = $hunter->getName();
                if ($this->type[$runner_name] === self::PLAYER_RUNNER && $this->type[$hunter_name] === self::PLAYER_HUNTER && $this->game_running) { //殴った人が鬼 殴られた人は逃走者 ゲームは進行中
                    if($this->remaining_hunters === 1){
                        $hunter->getEffects()->clear();
                    }
                    $this->joinTeam($runner, self::PLAYER_HUNTER);
                    $runner->sendMessage(self::PREFIX . "§c{$hunter_name}§rにタッチされてしまった！");
                    $runner->sendMessage(self::PREFIX . "§c鬼になった。。。");
                    $this->countdown($runner);
                    $this->makeHunter($runner);
                    $hunter->sendMessage(self::PREFIX . "§a{$runner_name}§rを捕まえた！");
                    Server::getInstance()->broadcastMessage(self::PREFIX_ALL . "§c{$hunter_name}§rが§a{$runner_name}§rを捕まえた！");
                    $this->remaining_runners--;

                    if ($this->remaining_runners === 0) {
                        $this->bad_end();
                    } else {
                        Server::getInstance()->broadcastMessage(self::PREFIX_ALL . "子ども 残り§e{$this->remaining_runners}§r人");
                    }
                }
            }
            $event->cancel();
        }
    }

    public function onChat(PlayerChatEvent $event): void {
        $chat = $event->getMessage();
        if ($chat === "oni sta") {
            $this->game_running_ready = true;
            $event->cancel();
        }

        if($chat === "cut"){
            $this->time_left = 20;
            $event->cancel();
        }

        if($chat === "cut map"){
            $this->time_start = 52;
            $event->cancel();
        }

        if ($chat === "cut run"){
            $this->time_start = 5;
            $event->cancel();
        }
    }

    public function onMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();
        $time = $this->time_start;
        $from = $event->getFrom();
        $to = $event->getTo();
        if ($time >= 30 && $time <= 45) {
            if ($from->getX() !== $to->getX() || $from->getZ() !== $to->getZ()) {
                $event->cancel();
            }
        }

        if ($time >= 0 && $time <= 35 && $this->type[$name] === self::PLAYER_HUNTER && !$this->game_running) {
            if ($from->getX() !== $to->getX() || $from->getZ() !== $to->getZ()) {
                $event->cancel();
            }
        }

        if(isset($this->immobile[$player->getName()])) {
            if ($from->getX() !== $to->getX() || $from->getZ() !== $to->getZ()) {
                $event->cancel(); // 動き禁止！
            }
        }
    }

    public function onInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();
        $item = $event->getItem();
        if ($item->getTypeId() === ItemTypeIds::NETHER_STAR) {
            $player->getInventory()->remove($item);
            $this->playSound(self::SOUND_CLICK);
            $player->sendMessage(self::PREFIX."§bスピードアイテム§rを使用！！！！！！！");
            $player->getEffects()->add(new EffectInstance(
                VanillaEffects::SPEED(),
                20 * 5, // 5秒（20tick = 1秒）
                1,     // レベル(0=Lv1)
                true  // パーティクルを隠すか？ false = 表示
            ));
            // 5秒後に効果を削除
            $this->getScheduler()->scheduleDelayedTask(
                new ClosureTask(function() use ($player) : void{
                    $player->sendMessage(self::PREFIX."効果が切れたようだ");
                }),
                20 * 5 // delay
            );
        }

        if($item->getTypeId() === ItemTypeIds::FEATHER){
            $player->getInventory()->remove($item);
            $direction = $player->getDirectionVector()->normalize();
            $force = 1.2;
            $yBoost = 0.6;
            $velocity = new Vector3(
                $direction->getX() * $force,
                $direction->getY() * $force + $yBoost,
                $direction->getZ() * $force
            );
            $player->setMotion($velocity);
            $player->sendMessage(self::PREFIX."§aジャンプ§r！！！！！！！");
        }

        if($item->getTypeId() ===  ItemTypeIds::GHAST_TEAR){
            $player->getInventory()->remove($item);
            $player->sendMessage(self::PREFIX."§7透明アイテム§rを使用！！！！！！！");
            $player->getEffects()->add(new EffectInstance(
                VanillaEffects::INVISIBILITY(),
                20 * 10, // 5秒（20tick = 1秒）
                1,     // レベル(0=Lv1)
                true  // パーティクルを隠すか？ false = 表示
            ));
            // 5秒後に効果を削除
            $this->getScheduler()->scheduleDelayedTask(
                new ClosureTask(function() use ($player) : void{
                    $player->sendMessage(self::PREFIX."効果が切れたようだ");
                }),
                20 * 10 // delay
            );
        }
        
        if($item->getTypeId() === ItemTypeIds::COMPASS){
            //ToDo 位置わかりますアイテムうふふ
        }
    }
}
