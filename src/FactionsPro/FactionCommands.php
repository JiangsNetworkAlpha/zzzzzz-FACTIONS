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

class FactionCommands {
	
	public $plugin;
	
	public function __construct(FactionMain $pg) {
		$this->plugin = $pg;
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
						if($this->plugin->factionExists($args[1]) == true ) {
							$sender->sendMessage("[FactionsPro] Faction already exists");
							return true;
						}
						if(strlen($args[1]) > $this->plugin->prefs->get("MaxFactionNameLength")) {
							$sender->sendMessage("[FactionsPro] Faction name is too long. Please try again!");
							return true;
						}
						if($this->plugin->isInFaction($sender->getName())) {
							$sender->sendMessage("[FactionsPro] You must leave this faction first");
							return true;
						} else {
							$factionName = $args[1];
							$player = strtolower($player);
							$rank = "Leader";
							$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
							$stmt->bindValue(":player", $player);
							$stmt->bindValue(":faction", $factionName);
							$stmt->bindValue(":rank", $rank);
							$result = $stmt->execute();
							$sender->sendMessage("[FactionsPro] Faction successfully created!");
							return true;
						}
					}
					if($args[0] == "invite") {
						if( $this->plugin->isFactionFull($this->plugin->getPlayerFaction($player)) ) {
							$sender->sendMessage("[FactionsPro] Faction is full. Please kick players to make room.");
							return true;
						}
						$invited = $this->plugin->getServer()->getPlayerExact($args[1]);
						if($this->plugin->isInFaction($invited) == true) {
							$sender->sendMessage("[FactionsPro] Player is currently in a faction");
							return true;
						}
						if($this->plugin->prefs->get("OnlyLeadersCanInvite") & !($this->plugin->isLeader($player))) {
							$sender->sendMessage("[FactionsPro] Only your faction leader may invite!");
							return true;
						}
						if(!$invited instanceof Player) {
							$sender->sendMessage("[FactionsPro] Player not online!");
							return true;
						}
						if($invited->isOnline() == true) {
							$factionName = $this->plugin->getPlayerFaction($player);
							$invitedName = $invited->getName();
							$rank = "Member";
								
							$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO confirm (player, faction, invitedby, timestamp) VALUES (:player, :faction, :invitedby, :timestamp);");
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
						if($this->plugin->isInFaction($sender->getName()) == true) {
							if($this->plugin->isLeader($player) == true) {
								if($this->plugin->getPlayerFaction($player) == $this->plugin->getPlayerFaction($args[1])) {
									if($this->plugin->getServer()->getPlayerExact($args[1])->isOnline() == true) {
										$factionName = $this->plugin->getPlayerFaction($player);
										$factionName = $this->plugin->getPlayerFaction($player);
	
										$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
										$stmt->bindValue(":player", $player);
										$stmt->bindValue(":faction", $factionName);
										$stmt->bindValue(":rank", "Member");
										$result = $stmt->execute();
	
										$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
										$stmt->bindValue(":player", strtolower($args[1]));
										$stmt->bindValue(":faction", $factionName);
										$stmt->bindValue(":rank", "Leader");
										$result = $stmt->execute();
	
	
										$sender->sendMessage("[FactionsPro] You are no longer leader!");
										$this->plugin->getServer()->getPlayerExact($args[1])->sendMessage("[FactionsPro] You are now leader \nof $factionName!");
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
						if($this->plugin->isInFaction($sender->getName()) == false) {
							$sender->sendMessage("[FactionsPro] You must be in a faction to use this!");
							return true;
						}
						if($this->plugin->isLeader($player) == false) {
							$sender->sendMessage("[FactionsPro] You must be leader to use this");
							return true;
						}
						if($this->plugin->getPlayerFaction($player) != $this->getPlayerFaction($args[1])) {
							$sender->sendMessage("[FactionsPro] Player is not in this faction!");
							return true;
						}
						$kicked = $this->plugin->getServer()->getPlayerExact($args[1]);
						$factionName = $this->plugin->getPlayerFaction($player);
						$this->plugin->db->query("DELETE FROM master WHERE player='$args[1]';");
						$sender->sendMessage("[FactionsPro] You successfully kicked $args[1]!");
						$players[] = $this->plugin->getServer()->getOnlinePlayers();
						if(in_array($args[1], $players) == true) {
							$this->plugin->getServer()->getPlayerExact($args[1])->sendMessage("[FactionsPro] You have been kicked from \n $factionName!");
							return true;
						}
					}
					if(strtolower($args[0]) == 'info') {
						if(isset($args[1])) {
							if( !(ctype_alnum($args[1])) | !($this->plugin->factionExists($args[1]))) {
								$sender->sendMessage("[FactionsPro] Faction does not exist");
								return true;
							}
							$faction = strtolower($args[1]);
							$leader = $this->plugin->getLeader($faction);
							$numPlayers = $this->plugin->getNumberOfPlayers($faction);
							$sender->sendMessage("-------------------------");
							$sender->sendMessage("$faction");
							$sender->sendMessage("Leader: $leader");
							$sender->sendMessage("# of Players: $numPlayers");
							$sender->sendMessage("MOTD: $message");
							$sender->sendMessage("-------------------------");
						} else {
							$faction = $this->plugin->getPlayerFaction(strtolower($sender->getName()));
							$result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
							$array = $result->fetchArray(SQLITE3_ASSOC);
							$message = $array["message"];
							$leader = $this->plugin->getLeader($faction);
							$numPlayers = $this->plugin->getNumberOfPlayers($faction);
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
						if($this->plugin->isInFaction($sender->getName()) == false) {
							$sender->sendMessage("[FactionsPro] You must be in a faction to use this!");
							return true;
						}
						if($this->plugin->isLeader($player) == false) {
							$sender->sendMessage("[FactionsPro] You must be leader to use this");
							return true;
						}
						$sender->sendMessage("[FactionsPro] Type your message in chat. It will not be visible to other players");
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO motdrcv (player, timestamp) VALUES (:player, :timestamp);");
						$stmt->bindValue(":player", strtolower($sender->getName()));
						$stmt->bindValue(":timestamp", time());
						$result = $stmt->execute();
					}
					if(strtolower($args[0]) == "accept") {
						$player = $sender->getName();
						$lowercaseName = strtolower($player);
						$result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage("[FactionsPro] You have not been invited to any factions!");
							return true;
						}
						$invitedTime = $array["timestamp"];
						$currentTime = time();
						if( ($currentTime - $invitedTime) <= 45 ) { //This should be configurable
							$faction = $array["faction"];
							$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
							$stmt->bindValue(":player", strtolower($player));
							$stmt->bindValue(":faction", $faction);
							$stmt->bindValue(":rank", "Member");
							$result = $stmt->execute();
							$this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
							$sender->sendMessage("[FactionsPro] You successfully joined $faction!");
							$this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage("[FactionsPro] $player joined the faction!");
						} else {
							$sender->sendMessage("[FactionsPro] Invite has timed out!");
							$this->plugin->db->query("DELETE * FROM confirm WHERE player='$player';");
						}
					}
					if(strtolower($args[0]) == "deny") {
						$player = $sender->getName();
						$lowercaseName = strtolower($player);
						$result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage("[FactionsPro] You have not been invited to any factions!");
							return true;
						}
						$invitedTime = $array["timestamp"];
						$currentTime = time();
						if( ($currentTime - $invitedTime) <= 45 ) { //This should be configurable
							$this->plugin->db->query("DELETE * FROM confirm WHERE player='$lowercaseName';");
							$sender->sendMessage("[FactionsPro] Invite declined!");
							$this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage("[FactionsPro] $player declined the invite!");
						} else {
							$sender->sendMessage("[FactionsPro] Invite has timed out!");
							$this->plugin->db->query("DELETE * FROM confirm WHERE player='$lowercaseName';");
						}
					}
					if(strtolower($args[0]) == "del") {
						if($this->plugin->isInFaction($player) == true) {
							if($this->plugin->isLeader($player)) {
								$faction = $this->plugin->getPlayerFaction($player);
								$this->plugin->db->query("DELETE FROM master WHERE faction='$faction';");
								$sender->sendMessage("[FactionsPro] Faction successfully disbanded!");
							}	 else {
								$sender->sendMessage("[FactionsPro] You are not leader!");
							}
						} else {
							$sender->sendMessage("[FactionsPro] You are not in a faction!");
						}
					}
					if(strtolower($args[0] == "leave")) {
						if($this->plugin->isLeader($player) == false) {
							$remove = $sender->getPlayer()->getNameTag();
							$faction = $this->plugin->getPlayerFaction($player);
							$name = $sender->getName();
							$this->plugin->db->query("DELETE FROM master WHERE player='$name';");
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
			$this->plugin->getServer()->getLogger()->info(TextFormat::RED . "[FactionsPro] Please run command in game");
		}
	}
}