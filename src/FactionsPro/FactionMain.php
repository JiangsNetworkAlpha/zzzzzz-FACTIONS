<?php

namespace FactionsPro;

/*
 * 
 * v1.3.0 To Do List
 * [X] Separate into Command, Listener, and Main files
 * [ ] Implement commands (plot claim, plot del)
 * [ ] Get plots to work
 * [X] Add plot to config
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
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\block\Snow;
use pocketmine\math\Vector3;
use pocketmine\level\Level;
use FactionsPro\utils\Session;


class FactionMain extends PluginBase implements Listener
{
	
	public $db;
	public $prefs;
	public $factions = array();
	public $sessions = array();
	
	public function onEnable()
	{	
		@mkdir($this->getDataFolder());
		
		if(!file_exists($this->getDataFolder() . "Factions.fp"))
		{
			$file = fopen($this->getDataFolder() . "Factions.fp", "w");
			$txt = "";
			fwrite($file, $txt);
		}
		
		if(!file_exists($this->getDataFolder() . "BannedNames.txt"))
		{
			$file = fopen($this->getDataFolder() . "BannedNames.txt", "w");
			$txt = "Admin:admin:Staff:staff:Owner:owner:Builder:builder:Op:OP:op";
			fwrite($file, $txt);
		}
		
		if(!file_exists($this->getDataFolder() . "NoClaimWorlds.txt"))
		{
			$file = fopen($this->getDataFolder() . "NoClaimWorlds.txt", "w");
			$txt = "Delete the contents of this file and replace with the names of the worlds which you would like claiming to be disabled in this format: world1:world2:world3 leave this file empty if you wish to enable claiming in every world";
			fwrite($file, $txt);
		}
		
		$this->getServer()->getPluginManager()->registerEvents(new FactionListener($this), $this);
		$this->fCommand = new FactionCommands($this);
		
		$this->prefs = new Config($this->getDataFolder() . "Prefs.yml", CONFIG::YAML, array(
				"MaxFactionNameLength" => 20,
				"MaxPlayersPerFaction" => 10,
				"OnlyLeadersAndOfficersCanInvite" => true,
				"OfficersCanClaim" => true,
				"PlotSize" => 25,
				"PlaceSnowBlocksOnClaim" => true,
		));
		$this->db = new \SQLite3($this->getDataFolder() . "FactionsPro.db");
		$this->db->exec("CREATE TABLE IF NOT EXISTS motdrcv (player TEXT PRIMARY KEY, timestamp INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS motd (faction TEXT PRIMARY KEY, message TEXT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS plots(faction TEXT PRIMARY KEY, x1 INT, z1 INT, x2 INT, z2 INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS home(faction TEXT PRIMARY KEY, x INT, y INT, z INT);");
	
		if(!empty(file_get_contents($this->getDataFolder() . "Factions.fp")))
		{
			$this->loadAll();
		}
	}
		
	public function onCommand(CommandSender $sender, Command $command, $label, array $args)
	{
		$this->fCommand->onCommand($sender, $command, $label, $args);
	}
	
	public function addFaction(Faction $faction)
	{
		array_push($this->factions, $faction);
	}
	
	public function removeFaction(Faction $faction)
	{
		$key = array_search($faction, $this->factions);
		if($key !== false)
		{
			unset($this->factions[$key]);
		}
	}
	
	public function getFactions()
	{
		return $this->factions;
	}
	
	public function getFaction($name)
	{
		foreach($this->factions as $faction)
		{
			if($faction->getName() == $name)
			{
				return $faction;
			}
		}
		return false;
	}
	
	public function factionExists($faction)
	{
		return isset($this->factions[$faction]);
	}
	
	public function sameFaction($player1, $player2) 
	{
		$this->getSession($player1)->getFaction() == $this->getSession($player2)->getFaction();
	}
	
	public function isNameBanned($name) {
		$bannedNames = explode(":", file_get_contents($this->getDataFolder() . "BannedNames.txt"));
		return in_array($name, $bannedNames);
	}
	
	public function claimingIsDisabled(Level $level)
	{
		$disabledWorlds = explode(":", file_get_contents($this->getDataFolder() . "NoClaimWorlds.txt"));
		return in_array($level->getName(), $disabledWorlds);
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
			$claimedBy = $this->factionFromPoint($x, $z);
			$sender->sendMessage($this->formatMessage("This area is aleady claimed by $claimedBy."));
			return false;
		}
		if($this->prefs->get("PlaceSnowBlockOnClaim")) {
			$level->setBlock(new Vector3($x + $arm, $y, $z + $arm), $block);
			$level->setBlock(new Vector3($x - $arm, $y, $z - $arm), $block);
		}
		$this->newPlot($faction, $x + $arm, $z + $arm, $x - $arm, $z - $arm);
		return true;
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
		return $this->getPlayerFaction($playerName) == $this->factionFromPoint($x, $z);
	}
	
	public function pointIsInPlot($x,$z) {
		$result = $this->db->query("SELECT * FROM plots WHERE $x <= x1 AND $x >= x2 AND $z <= z1 AND $z >= z2;");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return !empty($array);
	}
	
	public function cornerIsInPlot($x1, $z1, $x2, $z2) {
		return($this->pointIsInPlot($x1, $z1) || $this->pointIsInPlot($x1, $z2) || $this->pointIsInPlot($x2, $z1) || $this->pointIsInPlot($x2, $z2));
	}
	
	public function formatMessage($string, $confirm = false) {
		if($confirm) {
			return "[" . TextFormat::BLUE . "FactionsPro" . TextFormat::WHITE . "] " . TextFormat::GREEN . "$string";
		} else {	
			return "[" . TextFormat::BLUE . "FactionsPro" . TextFormat::WHITE . "] " . TextFormat::RED . "$string";
		}
	}
	
	public function motdWaiting($player) {
		$stmt = $this->db->query("SELECT * FROM motdrcv WHERE player='$player';");
		$array = $stmt->fetchArray(SQLITE3_ASSOC);
		$this->getServer()->getLogger()->info("\$player = " . $player);
		return !empty($array);
	}
	
	public function getMOTDTime($player) {
		$stmt = $this->db->query("SELECT * FROM motdrcv WHERE player='$player';");
		$array = $stmt->fetchArray(SQLITE3_ASSOC);
		return $array['timestamp'];
	}
	
	public function setMOTD($faction, $player, $msg) {
		$stmt = $this->db->prepare("INSERT OR REPLACE INTO motd (faction, message) VALUES (:faction, :message);");
		$stmt->bindValue(":faction", $faction);
		$stmt->bindValue(":message", $msg);
		$result = $stmt->execute();
		
		$this->db->query("DELETE FROM motdrcv WHERE player='$player';");
	}
	
	public function saveAll()
	{
		$this->getServer()->getLogger()->info($this->formatMessage("Saving Factions..."));
		$exportArray = [];
		foreach($this->factions as $faction)
		{
			$exportArray[] = $faction->export();
		}
		$txt = implode("*", $exportArray);
		$file = fopen($this->getDataFolder() . "Factions.fp", "w");
		fwrite($file, $txt);
		$this->getServer()->getLogger()->info($this->formatMessage("Factions Saved!", true));
	}
	
	public function loadAll()
	{
		$this->getServer()->getLogger()->info($this->formatMessage("Loading Factions..."));
		$file = explode("*", file_get_contents($this->getDataFolder() . "Factions.fp"));
		foreach($file as $faction)
		{
			Faction::import($faction, $this);
		}
		$this->getServer()->getLogger()->info($this->formatMessage("Factions Loaded!", true));
	}
	
	public function addSession(Player $player)
	{
		$this->sessions[$player->getId()] = new Session($this, $player);
	}
	
	public function removeSession(Player $player)
	{
		if(isset($this->sessions[$id = $player->getId()])){
			unset($this->sessions[$id]);
		}
	}
	
	public function getSession(Player $player)
	{
		return isset($this->sessions[$id = $player->getId()]) ? $this->sessions[$id] : null;
	}
	
	public function getSessionFromName($playerName)
	{
		if($player = $this->getServer()->getPlayer($playerName) instanceof Player)
		{
			return $this->getSession($player);
		}
		return false;
	}
	
	public function onDisable()
	{
		$this->saveAll();
		$this->db->close();
	}
}
