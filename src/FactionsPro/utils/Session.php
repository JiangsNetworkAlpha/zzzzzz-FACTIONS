<?php

namespace FactionsPro\utils;

use FactionsPro\FactionMain;
use pocketmine\Player;
use FactionsPro\Faction;

class Session
{
	private $player;
	private $plugin;
	private $faction;
	private $rank;
	private $invite;
	
	public function __construct(FactionMain $main, Player $player)
	{
		$this->plugin = $main;
		$this->player = $player;
		$this->updateFaction();
		$this->updateTag();
		$this->plugin->getServer()->getLogger()->info($this->plugin->formatMessage($player->getName() . " session initialized!", true));
	}
	
	public function registerInvite(FactionInvite $invite)
	{
		$this->invite = $invite;
	}
	
	public function deregisterInvite()
	{
		$this->invite = null;
	}
	
	public function joinFaction(Faction $faction)
	{
		$faction->addPlayer($this->getPlayer(), "Member");
		$this->updateFaction();
		$this->updateTag();
	}
	
	public function hasInvite()
	{
		return $this->invite != null;
	}
	
	public function getInvite()
	{
		return $this->invite;
	}
	
	public function updateFaction()
	{
		foreach($this->plugin->getFactions() as $faction)
		{
			if($faction->hasPlayer($this->player))
			{
				$this->faction = $faction->getName();
				$this->updateRank();
				return true;
			}
			$this->faction = null;
		}
	}
	
	public function leaveFaction()
	{
		$this->getFaction()->removePlayer($this->getPlayer());
		$this->faction = null;
		$this->rank = null;
	}
	
	public function updateTag()
	{
		$this->updateFaction();
		if(!$this->inFaction()) {
			$this->getPlayer()->setNameTag($this->getPlayer()->getName());
		} elseif($this->isLeader()) {
			$this->getPlayer()->setNameTag("**[" . $this->getFactionName() . "] " . $this->getPlayer()->getName());
		} elseif($this->isOfficer()) {
			$this->getPlayer()->setNameTag("*[" . $this->getFactionName() . "] " . $this->getPlayer()->getName());
		} elseif($this->isMember()) {
			$this->getPlayer()->setNameTag("[" . $this->getFactionName() . "] " . $this->getPlayer()->getName());
		}
	}
	
	public function getPlayer() { return $this->player; }
	
	public function updateRank()
	{
		if($this->inFaction())
		{
			$this->rank = $this->plugin->getFaction($this->faction)->getRank($this->player);
			return true;
		}
		$this->rank = false;
	}
	
	public function getFactionName()
	{
		if($this->faction == null) { return null; }
		return $this->faction;
	}
	
	public function getFaction()
	{
		return $this->plugin->getFaction($this->getFactionName());
	}
	
	public function inFaction()
	{
		return $this->faction != null;
	}
	
	public function isLeader() { return $this->rank == "Leader"; }
	public function isMember() { return $this->rank == "Member"; }
	public function isOfficer() { return $this->rank == "Officer"; }
	
	
}