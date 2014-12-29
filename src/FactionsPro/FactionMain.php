<?php

namespace FactionsPro;

/*
 * 
 * v1.3.0 To Do List
 * [X] Separate into Command, Listener, and Main files
 * [ ] Implement commands (plot claim, plot del)
 * [ ] Get plots to work
 * [ ] Add plot to config
 * [ ] Add faction description /f desc <faction>
 * [ ] Only leaders can edit motd, only members can check
 * [ ] More beautiful looking (and working) config
 * 
 * 
 */

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\PluginTask;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\block\Snow;
use pocketmine\math\Vector3;


class FactionMain extends PluginBase implements Listener {
	
	public $db;
	public $prefs;
	
	public function onEnable() {
		@mkdir($this->getDataFolder());
		
		$this->getServer()->getPluginManager()->registerEvents(new FactionListener($this), $this);
		$this->fCommand = new FactionCommands($this);
		
		$this->prefs = new Config($this->getDataFolder() . "Prefs.yml", CONFIG::YAML, array(
				"MaxFactionNameLength" => 20,
				"MaxPlayersPerFaction" => 10,
				"OnlyLeadersCanInvite" => true,
		));
		$this->db = new \SQLite3($this->getDataFolder() . "FactionsPro.db");
		$this->db->exec("CREATE TABLE IF NOT EXISTS master (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, rank TEXT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS confirm (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, invitedby TEXT, timestamp INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS motdrcv (player TEXT PRIMARY KEY, timestamp INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS motd (faction TEXT PRIMARY KEY, message TEXT);");
	}
		
	public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
		$this->fCommand->onCommand($sender, $command, $label, $args);
	}
	public function isInFaction($player) {
		$player = strtolower($player);
		$result = $this->db->query("SELECT * FROM master WHERE player='$player';");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return empty($array) == false;
	}
	public function isLeader($player) {
		$faction = $this->db->query("SELECT * FROM master WHERE player='$player';");
		$factionArray = $faction->fetchArray(SQLITE3_ASSOC);
		return $factionArray["rank"] == "Leader";
	}
	public function isOfficer($player) {
		$faction = $this->db->query("SELECT * FROM master WHERE player='$player';");
		$factionArray = $faction->fetchArray(SQLITE3_ASSOC);
		return $factionArray["rank"] == "Officer";
	}
	public function getPlayerFaction($player) {
		$faction = $this->db->query("SELECT * FROM master WHERE player='$player';");
		$factionArray = $faction->fetchArray(SQLITE3_ASSOC);
		return $factionArray["faction"];
	}
	public function getLeader($faction) {
		$leader = $this->db->query("SELECT * FROM master WHERE faction='$faction' AND rank='Leader';");
		$leaderArray = $leader->fetchArray(SQLITE3_ASSOC);
		return $leaderArray['player'];
	}
	public function factionExists($faction) {
		$result = $this->db->query("SELECT * FROM master WHERE faction='$faction';");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return empty($array) == false;
	}
	public function sameFaction($player1, $player2) {
		$faction = $this->db->query("SELECT * FROM master WHERE player='$player1';");
		$player1Faction = $faction->fetchArray(SQLITE3_ASSOC);
		$faction = $this->db->query("SELECT * FROM master WHERE player='$player2';");
		$player2Faction = $faction->fetchArray(SQLITE3_ASSOC);
		return $player1Faction["faction"] == $player2Faction["faction"];
	}
	public function getNumberOfPlayers($faction) {
		$query = $this->db->query("SELECT COUNT(*) as count FROM master WHERE faction='$faction';");
		$number = $query->fetchArray();
		return $number['count'];
	}
	public function isFactionFull($faction) {
		return $this->getNumberOfPlayers($faction) >= $this->prefs->get("MaxPlayersPerFaction");
	}
	
	//Drawing Functions
	
	public function drawPlotSquare($x, $y, $z, $level, $size) {
		if($size % 2 != 0) {
			$size++;
		}
		$diag = ($size - 1) / 2;
		$x = $x;
		$y = $y;
		$z = $z;
		$this->getServer()->getLogger()->info("Ran!");
		$block = new Snow();
		$level->setBlock(new Vector3($x + $diag, $y, $z + $diag), $block);
		$level->setBlock(new Vector3($x + $diag, $y, $z - $diag), $block);
		$level->setBlock(new Vector3($x - $diag, $y, $z + $diag), $block);
		$level->setBlock(new Vector3($x - $diag, $y, $z - $diag), $block);
		
		/*for($i = 1; $i <= $size; $i++) {
			$level->setBlock(new Vector3($x + ($size + 1 / 2), $y, $z + ($size + 1 / 2)), $block);
			$z--;
		}*/
	}
	public function onDisable() {
		$this->db->close();
	}
}