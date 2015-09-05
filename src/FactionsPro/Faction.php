<?php

namespace FactionsPro;

use pocketmine\Player;
class Faction
{
	private $name;
	private $members = array();
	private $hasPlot;
	private $plugin;
	
	public function __construct($plugin, $name, $leader)
	{
		$this->addPlayer($leader, "Leader");
		$this->name = $name;
		$this->hasPlot = false;
		$this->plugin = $plugin;
		$this->plugin->addFaction($this);
	}
	
	public static function import($string, $plugin)
	{
		$name = strstr($string, "$", true);
		$members = explode(",", substr(strstr($string, "$"), 1));
		$leaderID = 0;
		foreach($members as $num => $text)
		{
			if(strpos($text, ":Leader"))
			{
				$leaderID = $num;
			}
		}
		$leader = strstr($members[$leaderID], ":", true);
		$faction = new self($plugin, $name, $leader);
		unset($members[$leaderID]);
		foreach($members as $num => $text)
		{
			$player = strstr($text, ":", true);
			$rank = substr(strstr($text, ":"), 1);
			$faction->addPlayer($player, $rank);
		}
		$plugin->getServer()->getLogger()->info($plugin->formatMessage("[X] $name", true));
	}
	
	public function addPlayer($player, $rank)
	{
		if($rank != "Leader" && $rank != "Officer" && $rank != "Member")
		{
			return false;
		}
		if($player instanceof Player)
		{
			$this->members[$player->getName()] = $rank;
			return true;
		} else {
			$this->members[$player] = $rank;
			return true;
		}
	}
	
	public function removePlayer(Player $player)
	{
		if(isset($this->members[$player->getName()]))
		{
			unset($this->members[$player->getName()]);
			return true;
		}
		return false;
	}
	
	public function removePlayer_string($playerName)
	{
		if(isset($this->members[$playerName]))
		{
			unset($this->members[$playerName]);
			return true;
		}
		return false;
	}
	
	public function delete()
	{
		foreach($this->members as $name => $rank)
		{
			if($this->plugin->getServer()->getPlayer($name) instanceof Player)
			{
				$this->plugin->getSession($this->plugin->getServer()->getPlayer($name))->leaveFaction();
				$this->plugin->getServer()->getPlayer($name)->sendMessage($this->plugin->formatMessage("Your faction has been disbanded"));
			}
		}
		$this->plugin->removeFaction($this);
	}
	
	public function export()
	{
		return "" . $this->name . "$" . $this->exportMembers();
	}
	
	public function setRank(Player $player, $rank)
	{
		$this->members[$player->getName()] = $rank;
	}
	
	public function exportMembers()
	{
		$export = "";
		foreach($this->members as $member => $rank)
		{
			$export = $export . "$member:$rank,";
		}
		return substr($export, 0, -1);
	}
	
	public function hasPlayer(Player $player)
	{
		foreach($this->members as $name => $rank)
		{
			if($player->getName() == $name) { return true; }
		}
		return false;
	}
	
	public function hasPlayer_string($playerName)
	{
		foreach($this->members as $name => $rank)
		{
			if(strtolower($playerName) == strtolower($name)) { return true; }
		}
		return false;
	}
	
	public function getRank(Player $player)
	{
		return $this->members[$player->getName()];
	}
	
	public function getLeader() // returns name as string
	{
		foreach($this->members as $member => $rank)
		{
			if($rank == "Leader")
			{
				return $member;
			}
		}
	}
	
	public function isFull()
	{
		return $this->getNumberMembers() >= $this->plugin->prefs->get("MaxPlayersPerFaction");
	}
	
	public function getNumberMembers()
	{
		return count($this->members);
	}
	
	public function getName()
	{
		return $this->name;
	}
}