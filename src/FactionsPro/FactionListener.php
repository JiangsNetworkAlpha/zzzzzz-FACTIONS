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


class FactionListener implements Listener {
	
	public $plugin;
	
	public function __construct(FactionMain $pg) {
		$this->plugin = $pg;
	}
	
	public function factionChat(PlayerChatEvent $PCE) {
		if($this->plugin->isInFaction($PCE->getPlayer()->getName()) == true) {
			$m = $PCE->getMessage();
			$p = $PCE->getPlayer()->getName();
			$lowerp = strtolower($p);
			$stmt = $this->plugin->db->query("SELECT * FROM master WHERE player='$p';");
			$result = $stmt->fetchArray(SQLITE3_ASSOC);
			$f = $result["faction"];
			$PCE->setFormat("[$f] $p: $m");
			//MOTD RECEIVER
			$p = strtolower($p);
			$stmt = $this->plugin->db->query("SELECT * FROM motdrcv WHERE player='$p';");
			$result = $stmt->fetchArray(SQLITE3_ASSOC);
			if(empty($result) == false) {
				if(time() - $result["timestamp"] > 30) {
					$PCE->getPlayer()->sendMessage("[FactionsPro] Timed out. Please use /f motd again.");
					$this->plugin->db->query("DELETE FROM motdrcv WHERE player='$p';");
					$PCE->setCancelled(true);
					return true;
				} else {
					$motd = $PCE->getMessage();
					$faction = $this->plugin->getPlayerFaction($p);
					$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO motd (faction, message) VALUES (:faction, :message);");
					$stmt->bindValue(":faction", $faction);
					$stmt->bindValue(":message", $motd);
					$result = $stmt->execute();
					$PCE->setCancelled(true);
					$this->plugin->db->query("DELETE FROM motdrcv WHERE player='$p';");
					$PCE->getPlayer()->sendMessage("[FactionsPro] Successfully updated faction message of the day!");
				}
			}
		} else {
			$m = $PCE->getMessage();
			$p = $PCE->getPlayer()->getName();
			$PCE->setFormat("$p: $m");
		}
	}
	
	//To be implemented later
	
	/*public function playerJoinInfo(PlayerJoinEvent $PJE) {
	 if($this->isInFaction($PJE->getPlayer()->getName()) == true) {
	 $player = $PJE->getPlayer();
	 $faction = $this->getPlayerFaction(strtolower($PJE->getPlayer()->getName()));
	 $result = db->query("SELECT * FROM motd WHERE faction='$faction';");
	 $array = $result->fetchArray(SQLITE3_ASSOC);
	 $message = $array["message"];
	 $player->sendMessage("-------------------------");
	 $player->sendMessage(Welcome Back, $player);
	 $player->sendMessage("$faction MOTD:");
	 $player->sendMessage("$message");
	 $player->sendMessage("-------------------------");
	 }
	 }*/
	
	public function factionPVP(EntityDamageEvent $factionDamage) {
		if($factionDamage instanceof EntityDamageByEntityEvent) {
			if(!($factionDamage->getEntity() instanceof Player) or !($factionDamage->getDamager() instanceof Player)) {
				return true;
			}
			if(($this->plugin->isInFaction($factionDamage->getEntity()->getPlayer()->getName()) == false) or ($this->plugin->isInFaction($factionDamage->getDamager()->getPlayer()->getName()) == false) ) {
				return true;
			}
			if(($factionDamage->getEntity() instanceof Player) and ($factionDamage->getDamager() instanceof Player)) {
				$player1 = $factionDamage->getEntity()->getPlayer()->getName();
				$player2 = $factionDamage->getDamager()->getPlayer()->getName();
				if($this->plugin->sameFaction($player1, $player2) == true) {
					$factionDamage->setCancelled(true);
				}
			}
		}
	}
}