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
	public $commands;
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
		
		$this->getServer()->getPluginManager()->registerEvents(new FactionListener($this), $this);
		$this->fCommand = new FactionCommands($this);
		
		$this->prefs = new Config($this->getDataFolder() . "Prefs.yml", CONFIG::YAML, array(
				"Leader Idenfitier" => "**",
				"Officer Identifier" => "*",
				"Factions In Overhead Nametag" => true,
				"Maximum Faction Name Length" => 20,
				"Maximum Players Per Faction" => 10,
				"Developer Mode" => false,
		));
		
		$this->commands = new Config($this->getDataFolder() . "Commands.yml", CONFIG::YAML, array(
				"/f create" => true,
				"/f delete" => true,
				"/f demote" => true,
				"/f desc" => true,
				"/f home" => true,
				"/f info" => true,
				"/f invite" => true,
				"/f kick" => true,
				"/f leader" => true,
				"/f leave" => true,
				"/f promote" => true,	
		));
		
		if($this->devModeEnabled())
		{
			$this->getServer()->getLogger()->info(TextFormat::RED . "FactionsPro Developer Mode has been enabled, you may turn this off any time by setting 'DeveloperMode' to false in settings.");
		}
	
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
		if($this->devModeEnabled())
		{
			$this->getServer()->getLogger()->info(TextFormat::GREEN . $faction->getName() . " has been registered");
		}
	}
	
	public function removeFaction(Faction $faction)
	{
		$key = array_search($faction, $this->factions);
		if($key !== false)
		$name = $faction->getName();
		{
			unset($this->factions[$key]);
			{
				if($this->devModeEnabled())
				{
					$this->getServer()->getLogger(TextFormat::GREEN . "" . $name . " has been disbanded");
				}
			}
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
	
	public function formatMessage($string, $confirm = false) {
		if($confirm) {
			return "[" . TextFormat::BLUE . "FactionsPro" . TextFormat::WHITE . "] " . TextFormat::GREEN . "$string";
		} else {	
			return "[" . TextFormat::BLUE . "FactionsPro" . TextFormat::WHITE . "] " . TextFormat::RED . "$string";
		}
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
			return $this->getSession($this->getServer()->getPlayer($playerName));
		}
		return false;
	}
	
	public function devModeEnabled()
	{
		return $this->prefs->get("Developer Mode");
	}
	
	public function onDisable()
	{
		$this->saveAll();
	}
}
