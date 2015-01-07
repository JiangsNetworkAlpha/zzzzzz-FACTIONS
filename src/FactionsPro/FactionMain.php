<?php

namespace FactionsPro;

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
	public $work;
	
	public function onEnable() {
		@mkdir($this->getDataFolder());
		
		$this->getServer()->getPluginManager()->registerEvents(new FactionListener($this), $this);
		$this->fCommand = new FactionCommands($this);
		
		$this->prefs = new Config($this->getDataFolder() . "Prefs.yml", CONFIG::YAML, array(
				"MaxFactionNameLength" => 20,
				"MaxPlayersPerFaction" => 10,
				"OnlyLeadersAndOfficersCanInvite" => true,
				"OfficersCanClaim" => true,
				"PlotSize" => 25,
				"OfficerIdentifier" => '*',
				"LeaderIdentifier" => '**',
		));
		$this->db = new \SQLite3($this->getDataFolder() . "FactionsPro.db");
		$this->db->exec("CREATE TABLE IF NOT EXISTS master (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, rank TEXT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS confirm (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, invitedby TEXT, timestamp INT);");
		//$this->db->exec("CREATE TABLE IF NOT EXISTS descRCV (player TEXT PRIMARY KEY, timestamp INT);");
		//$this->db->exec("CREATE TABLE IF NOT EXISTS desc (faction TEXT PRIMARY KEY, description TEXT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS plots(faction TEXT PRIMARY KEY, x1 INT, z1 INT, x2 INT, z2 INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS home(faction TEXT PRIMARY KEY, x INT, y INT, z INT);");
		
		/*
		 * Will implement when it only alerts you if you step into a plot for the first time
		 * 
		 * $task = new FactionTask($this);
		 * $this->getServer()->getScheduler()->scheduleRepeatingTask($task, 20);
		 * 
		 */
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
		if(empty($factionArray)) {
			return false;
		}
		return $factionArray["rank"] == "Leader";
	}
	public function isOfficer($player) {
		$faction = $this->db->query("SELECT * FROM master WHERE player='$player';");
		$factionArray = $faction->fetchArray(SQLITE3_ASSOC);
		if(empty($factionArray)) {
			return false;
		}
		return $factionArray["rank"] == "Officer";
	}
	public function isMember($player) {
		$faction = $this->db->query("SELECT * FROM master WHERE player='$player';");
		$factionArray = $faction->fetchArray(SQLITE3_ASSOC);
		return $factionArray["rank"] == "Member";
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
	
	public function newPlot($faction, $x1, $z1, $x2, $z2) {
		$stmt = $this->db->prepare("INSERT OR REPLACE INTO plots (faction, x1, z1, x2, z2) VALUES (:faction, :x1, :z1, :x2, :z2);");
		$stmt->bindValue(":faction", $faction);
		$stmt->bindValue(":x1", $x1);
		$stmt->bindValue(":z1", $z1);
		$stmt->bindValue(":x2", $x2);
		$stmt->bindValue(":z2", $z2);
		$result = $stmt->execute();
	}
	public function drawPlot($sender, $faction, $x, $y, $z, $level, $size) {
		$arm = ($size - 1) / 2;
		$block = new Snow();
		if($this->cornerIsInPlot($x + $arm, $z + $arm, $x - $arm, $z - $arm)) {
			$sender->sendMessage("[FactionsPro] Cannot place plot here.");
			return true;
		}
		$level->setBlock(new Vector3($x + $arm, $y, $z + $arm), $block);
		$level->setBlock(new Vector3($x - $arm, $y, $z - $arm), $block);
		$this->newPlot($faction, $x + $arm, $z + $arm, $x - $arm, $z - $arm);
		$sender->sendMessage("[FactionsPro] Plot claimed.");
	}
	
	public function updatePlots() {
	}
	
	public function plotChecker($onlinePlayers) {
		foreach($onlinePlayers as $player) {
			if($this->isInPlot($player)) {
				$player->sendMessage("[FactionsPro] You are in a plot.");
			}
		}
	}
	
	public function isInPlot($player) {
		$x = $player->getFloorX();
		$z = $player->getFloorZ();
		$result = $this->db->query("SELECT * FROM plots WHERE $x <= x1 AND $x >= x2 AND $z <= z1 AND $z >= z2;");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return empty($array) == false;
	}
	
	public function factionFromPoint($x,$z) {
		$result = $this->db->query("SELECT * FROM plots WHERE $x <= x1 AND $x >= x2 AND $z <= z1 AND $z >= z2;");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return $array["faction"];
	}
	
	public function inOwnPlot($player) {
		$playerName = $player->getName();
		$x = $player->getFloorX();
		$z = $player->getFloorZ();
		$result = $this->db->query("SELECT * FROM plots WHERE $x <= x1 AND $x >= x2 AND $z <= z1 AND $z >= z2;");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return $this->getPlayerFaction($playerName) == $array['faction'];
	}
	
	public function pointIsInPlot($x,$z) {
		$result = $this->db->query("SELECT * FROM plots WHERE $x <= x1 AND $x >= x2 AND $z <= z1 AND $z >= z2;");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return !empty($array);
	}
	
	public function cornerIsInPlot($x1, $z1, $x2, $z2) {
		return($this->pointIsInPlot($x1, $z1) || $this->pointIsInPlot($x1, $z2) || $this->pointIsInPlot($x2, $z1) || $this->pointIsInPlot($x2, $z2));
	}
	
	public function onDisable() {
		$this->db->close();
	}
}
