<?php

namespace FactionsPro;

/*
 * 
 * v1.2.0
 * 
 * -Config file with 3 editable options
 * -Fixed bug which would spam console and cause massive lag
 * -/f info now shows motd leader and # of players
 * -/f info may be used with a parameter of another faction name to display info
 * -factions may now only have a configurable amount of players
 * -factions can only have alphanumeric characters, not doing this in earlier versions would give errors and sometimes crash the server
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


class Main extends PluginBase implements Listener {
	
	public $db;
	public $prefs;
	public $plot;
	
	public function onEnable() {
		@mkdir($this->getDataFolder());
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
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
		$this->plot = $this->getServer()->getPluginManager()->registerEvents(new PlotClaim($this), $this);
		}
	public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
		if($sender instanceof Player) {
			$player = $sender->getPlayer()->getName();
			if(strtolower($command->getName('f'))) {
				if(empty($args)) {
					$sender->sendMessage("[FactionsPro] Please use /f help for a list of commands");
				}
				if(count($args == 2)) {
					if($args[0] == "create") {
						if(!(ctype_alnum($args[1]))) {
							$sender->sendMessage("[FactionsPro] You may only use letters and numbers!");
							return true;
						}
						if($this->factionExists($args[1]) == true ) {
							$sender->sendMessage("[FactionsPro] Faction already exists");
							return true;
						}
						if(strlen($args[1]) > $this->prefs->get("MaxFactionNameLength")) {
							$sender->sendMessage("[FactionsPro] Faction name is too long. Please try again!");
							return true;
						}
						if($this->isInFaction($sender->getName())) {
							$sender->sendMessage("[FactionsPro] You must leave this faction first");
							return true;
						} else {
							$factionName = $args[1];
							$player = strtolower($player);
							$rank = "Leader";
							$stmt = $this->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
							$stmt->bindValue(":player", $player);
							$stmt->bindValue(":faction", $factionName);
							$stmt->bindValue(":rank", $rank);
							$result = $stmt->execute();
							$sender->sendMessage("[FactionsPro] Faction successfully created!");
							return true;
						}
					}
					if($args[0] == "invite") {
						if( $this->isFactionFull($this->getPlayerFaction($player)) ) {
							$sender->sendMessage("[FactionsPro] Faction is full. Please kick players to make room.");
							return true;
						}
						$invited = $this->getServer()->getPlayerExact($args[1]);
						if($this->isInFaction($invited) == true) {
							$sender->sendMessage("[FactionsPro] Player is currently in a faction");
							return true;
						}
						if($this->prefs->get("OnlyLeadersCanInvite") & !($this->isLeader($player))) {
							$sender->sendMessage("[FactionsPro] Only your faction leader may invite!");
							return true;
						}
						if(!$invited instanceof Player) {
							$sender->sendMessage("[FactionsPro] Player not online!");
							return true;
						}
						if($invited->isOnline() == true) {
							$factionName = $this->getPlayerFaction($player);
							$invitedName = $invited->getName();
							$rank = "Member";
							
							$stmt = $this->db->prepare("INSERT OR REPLACE INTO confirm (player, faction, invitedby, timestamp) VALUES (:player, :faction, :invitedby, :timestamp);");
							$stmt->bindValue(":player", strtolower($invitedName));
							$stmt->bindValue(":faction", $factionName);
							$stmt->bindValue(":invitedby", $sender->getName());
							$stmt->bindValue(":timestamp", time());
							$result = $stmt->execute();

							$sender->sendMessage("[FactionsPro] Successfully invited $invitedName!");
							$invited->sendMessage("[FactionsPro] You have been invited to $factionName. Type '/f accept' or '/f deny' into chat to accept or deny!");
						} else {
							$sender->sendMessage("[FactionsPro] Player not online!");
						}
					}
					if($args[0] == "leader") {
						if($this->isInFaction($sender->getName()) == true) {
							if($this->isLeader($player) == true) {
								if($this->getPlayerFaction($player) == $this->getPlayerFaction($args[1])) {
									if($this->getServer()->getPlayerExact($args[1])->isOnline() == true) {
										$factionName = $this->getPlayerFaction($player);
										$factionName = $this->getPlayerFaction($player);
										
										$stmt = $this->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
										$stmt->bindValue(":player", $player);
										$stmt->bindValue(":faction", $factionName);
										$stmt->bindValue(":rank", "Member");
										$result = $stmt->execute();
										
										$stmt = $this->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
										$stmt->bindValue(":player", strtolower($args[1]));
										$stmt->bindValue(":faction", $factionName);
										$stmt->bindValue(":rank", "Leader");
										$result = $stmt->execute();
										
										
										$sender->sendMessage("[FactionsPro] You are no longer leader!");
										$this->getServer()->getPlayerExact($args[1])->sendMessage("[FactionsPro] You are now leader \nof $factionName!");
									} else {
										$sender->sendMessage("[FactionsPro] Player not online!");
									}
								} else {
									$sender->sendMessage("[FactionsPro] Add player to faction first!");
								}
							} else {
								$sender->sendMessage("[FactionsPro] You must be leader to use this");
							}
						} else {
							$sender->sendMessage("[FactionsPro] You must be in a faction to use this!");
						}
					}
					if($args[0] == "kick") {
						if($this->isInFaction($sender->getName()) == false) {
							$sender->sendMessage("[FactionsPro] You must be in a faction to use this!");
							return true;
						}
						if($this->isLeader($player) == false) {
							$sender->sendMessage("[FactionsPro] You must be leader to use this");
							return true;
						}
						if($this->getPlayerFaction($player) != $this->getPlayerFaction($args[1])) {
							$sender->sendMessage("[FactionsPro] Player is not in this faction!");
							return true;
						}
						$kicked = $this->getServer()->getPlayerExact($args[1]);
						$factionName = $this->getPlayerFaction($player);
						$this->db->query("DELETE FROM master WHERE player='$args[1]';");
						$sender->sendMessage("[FactionsPro] You successfully kicked $args[1]!");
						$players[] = $this->getServer()->getOnlinePlayers();
						if(in_array($args[1], $players) == true) {
							$this->getServer()->getPlayerExact($args[1])->sendMessage("[FactionsPro] You have been kicked from \n $factionName!");	
							return true;
						}
					}
					if(strtolower($args[0]) == 'info') {
						if(isset($args[1])) {
							if( !(ctype_alnum($args[1])) | !($this->factionExists($args[1]))) {
								$sender->sendMessage("[FactionsPro] Faction does not exist");
								return true;
							}
							$faction = strtolower($args[1]);
							$leader = $this->getLeader($faction);
							$numPlayers = $this->getNumberOfPlayers($faction);
							$sender->sendMessage("-------------------------");
							$sender->sendMessage("$faction");
							$sender->sendMessage("Leader: $leader");
							$sender->sendMessage("# of Players: $numPlayers");
							$sender->sendMessage("MOTD: $message");
							$sender->sendMessage("-------------------------");
						} else {
							$faction = $this->getPlayerFaction(strtolower($sender->getName()));
							$result = $this->db->query("SELECT * FROM motd WHERE faction='$faction';");
							$array = $result->fetchArray(SQLITE3_ASSOC);
							$message = $array["message"];
							$leader = $this->getLeader($faction);
							$numPlayers = $this->getNumberOfPlayers($faction);
							$sender->sendMessage("-------------------------");
							$sender->sendMessage("$faction");
							$sender->sendMessage("Leader: $leader");
							$sender->sendMessage("# of Players: $numPlayers");
							$sender->sendMessage("MOTD: $message");
							$sender->sendMessage("-------------------------");
						}
					}
				}
				if(count($args == 1)) {
					if(strtolower($args[0]) == "motd") {
						if($this->isInFaction($sender->getName()) == false) {
							$sender->sendMessage("[FactionsPro] You must be in a faction to use this!");
							return true;
						}
						if($this->isLeader($player) == false) {
							$sender->sendMessage("[FactionsPro] You must be leader to use this");
							return true;
						}
						$sender->sendMessage("[FactionsPro] Type your message in chat. It will not be visible to other players");
						$stmt = $this->db->prepare("INSERT OR REPLACE INTO motdrcv (player, timestamp) VALUES (:player, :timestamp);");
						$stmt->bindValue(":player", strtolower($sender->getName()));
						$stmt->bindValue(":timestamp", time());
						$result = $stmt->execute();
					}					
					if(strtolower($args[0]) == "accept") {
						$player = $sender->getName();
						$lowercaseName = strtolower($player);
						$result = $this->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage("[FactionsPro] You have not been invited to any factions!");
							return true;
						}
						$invitedTime = $array["timestamp"];
						$currentTime = time();
						if( ($currentTime - $invitedTime) <= 45 ) { //This should be configurable
							$faction = $array["faction"];
							$stmt = $this->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
							$stmt->bindValue(":player", strtolower($player));
							$stmt->bindValue(":faction", $faction);
							$stmt->bindValue(":rank", "Member");
							$result = $stmt->execute();
							$this->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
							$sender->sendMessage("[FactionsPro] You successfully joined $faction!");
							$this->getServer()->getPlayerExact($array["invitedby"])->sendMessage("[FactionsPro] $player joined the faction!");
						} else {
							$sender->sendMessage("[FactionsPro] Invite has timed out!");
							$this->db->query("DELETE * FROM confirm WHERE player='$player';");
						}
					}
					if(strtolower($args[0]) == "deny") {
						$player = $sender->getName();
						$lowercaseName = strtolower($player);
						$result = $this->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage("[FactionsPro] You have not been invited to any factions!");
							return true;
						}
						$invitedTime = $array["timestamp"];
						$currentTime = time();
						if( ($currentTime - $invitedTime) <= 45 ) { //This should be configurable
							$this->db->query("DELETE * FROM confirm WHERE player='$lowercaseName';");
							$sender->sendMessage("[FactionsPro] Invite declined!");
							$this->getServer()->getPlayerExact($array["invitedby"])->sendMessage("[FactionsPro] $player declined the invite!");
						} else {
							$sender->sendMessage("[FactionsPro] Invite has timed out!");
							$this->db->query("DELETE * FROM confirm WHERE player='$lowercaseName';");
						}
					}
					if(strtolower($args[0]) == "del") {
						if($this->isInFaction($player) == true) {
							if($this->isLeader($player)) {
								$faction = $this->getPlayerFaction($player);
								$this->db->query("DELETE FROM master WHERE faction='$faction';");
								$sender->sendMessage("[FactionsPro] Faction successfully disbanded!");
							}	 else {
								$sender->sendMessage("[FactionsPro] You are not leader!");
							}
						} else {
							$sender->sendMessage("[FactionsPro] You are not in a faction!");
						}
					}
					if(strtolower($args[0] == "leave")) {
						if($this->isLeader($player) == false) {
							$remove = $sender->getPlayer()->getNameTag();
							$faction = $this->getPlayerFaction($player);
							$name = $sender->getName();
							$this->db->query("DELETE FROM master WHERE player='$name';");
							$sender->sendMessage("[FactionsPro] You successfully left $faction");
						} else {
							$sender->sendMessage("[FactionsPro] You must delete or give\nleadership first!");
						}
					}
					if(strtolower($args[0]) == "help") {
						$sender->sendMessage("FactionsPro Commands\n/f create <name>\n/f del\n/f help\n/f invite <player>\n/f kick <player>\n/f leave\n/f leader <player>\n/f leave\n/f motd\n/f info");
					}
				} else {
				$sender->sendMessage("[FactionsPro] Please use /f help for a list of commands");
				}
			}
		} else {
			$this->getServer()->getLogger()->info(TextFormat::RED . "[FactionsPro] Please run command in game");
		}
	}
	public function factionChat(PlayerChatEvent $PCE) {
		if($this->isInFaction($PCE->getPlayer()->getName()) == true) {
			$m = $PCE->getMessage();
			$p = $PCE->getPlayer()->getName();
			$lowerp = strtolower($p);
			$stmt = $this->db->query("SELECT * FROM master WHERE player='$p';");
			$result = $stmt->fetchArray(SQLITE3_ASSOC);
			$f = $result["faction"];
			$PCE->setFormat("[$f] $p: $m");
			//MOTD RECEIVER
			$p = strtolower($p);
			$stmt = $this->db->query("SELECT * FROM motdrcv WHERE player='$p';");
			$result = $stmt->fetchArray(SQLITE3_ASSOC);
			if(empty($result) == false) {
				if(time() - $result["timestamp"] > 30) {
					$PCE->getPlayer()->sendMessage("[FactionsPro] Timed out. Please use /f motd again.");
					$this->db->query("DELETE FROM motdrcv WHERE player='$p';");
					$PCE->setCancelled(true);
					return true;
				} else {
				$motd = $PCE->getMessage();
				$faction = $this->getPlayerFaction($p);
				$stmt = $this->db->prepare("INSERT OR REPLACE INTO motd (faction, message) VALUES (:faction, :message);");
				$stmt->bindValue(":faction", $faction);
				$stmt->bindValue(":message", $motd);
				$result = $stmt->execute();
				$PCE->setCancelled(true);
				$this->db->query("DELETE FROM motdrcv WHERE player='$p';");
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
			$result = $this->db->query("SELECT * FROM motd WHERE faction='$faction';");
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
			if(($this->isInFaction($factionDamage->getEntity()->getPlayer()->getName()) == false) or ($this->isInFaction($factionDamage->getDamager()->getPlayer()->getName()) == false) ) {
				return true;
			}
			if(($factionDamage->getEntity() instanceof Player) and ($factionDamage->getDamager() instanceof Player)) {
				$player1 = $factionDamage->getEntity()->getPlayer()->getName();
				$player2 = $factionDamage->getDamager()->getPlayer()->getName();
				if($this->sameFaction($player1, $player2) == true) {
					$factionDamage->setCancelled(true);
				}
			}
		}
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
	public function onDisable() {
		$this->db->close();
	}
}
