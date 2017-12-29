<?php
require 'vendor/autoload.php';
require 'HelperFunctions.php';
require 'Singleton.php';

//MongoDB class
class Database extends Singleton
{
	private $username, $password, $databaseName, $client, $database;

	public function setCredentials($username = 'alex', $password = 'proiectsac')
	{
		$this->username = $username;
		$this->password = $password;
		$this->databaseName = "fantasy-football-manager";
		$this->client = new MongoDB\Client("mongodb://ds249025.mlab.com:49025/fantasy-football-manager", 
						array(
							"username" => $this->username, 
							"password" => $this->password)
						);

		try
		{
			$this->database = $this->client->selectDatabase($this->databaseName);
		}
		catch (MongoDB\Driver\Exception\ConnectionTimeoutException $e)
		{
		    echo $e->getMessage();
		}
	}

	public function connectToTable($tableName = 'player_classes')
	{
		try
		{
			$playerCollection = $this->database->selectCollection($tableName);
		}
		catch (MongoDB\Driver\Exception\ConnectionTimeoutException $e)
		{
		    echo $e->getMessage();
		}

		return $playerCollection;
	}

	/**
		Returns the specified player or a random one
	*/
	public function getPlayer($collection, $random = True, $playerID = null)
	{
		$playersCount = $collection->count();

		if($random)
		{
			$playerID = mt_rand(0, $playersCount);
		}

		$cursor = $collection->find([
			'_id' => $playerID
		]);

		foreach ($cursor as $randomPlayer) 
		{
		   $playerArray = bsonUnserialize($randomPlayer);
		};

		return json_encode($playerArray);
	}

	public function getStartingEleven($activeTeamsCollection, $playerCollection, $managerID)
	{
		$cursor = $activeTeamsCollection->find([
			'_id' => $managerID
		]);

		foreach ($cursor as $activeTeam) 
		{
		   $startingEleven = bsonUnserialize($activeTeam);
		};

		$startingTeam = $startingEleven["players"];

		$startingPlayers = [];

		foreach($startingTeam as $playerID) 
		{
		   $player = $this->getPlayer($playerCollection, False, $playerID);
		   $startingPlayers[] = $player;
		};

		return $startingPlayers;
	}

	//Returns all available players, including benched ones
	public function getAvailablePlayers($managerCollection, $playerCollection, $managerID)
	{
		$cursor = $managerCollection->find([
			'_id' => $managerID
		]);

		foreach ($cursor as $availablePlayers) 
		{
		   $allPlayers = bsonUnserialize($availablePlayers);
		};

		$availablePlayers = $allPlayers["players"];

		$playersArray = [];

		foreach($availablePlayers as $playerID) 
		{
		   $player = $this->getPlayer($playerCollection, False, $playerID);
		   $playersArray[] = $player;
		};

		return $playersArray;
	}

	/**
		Returns an array of all manager's team with team name, starting 11, overall team rating and record
		except for the manager $managerID who is considered the manager that is currently playing
	*/
	public function getAllManagers($managerCollection, $playerCollection, $activeTeamsCollection, $managerID)
	{
		$cursorManagers = $managerCollection->find();

		$managers = [];

		foreach ($cursorManagers as $manager) 
		{
		   $objManager = bsonUnserialize($manager);

		   if($objManager["_id"] == $managerID)
		   {
		   		continue;
		   }

		   $startingEleven = $this->getStartingEleven($activeTeamsCollection, $playerCollection, $objManager['_id']);
		  
		   $overall = 0;
		   foreach($startingEleven as $player)
		   {
		   		$decodedPlayer = json_decode($player);
		   		$vars = get_object_vars($decodedPlayer);

		   		$overall += $vars["overall"];
		   }

		   $overall /= 11;		  
		   $managers[$objManager['_id']] = array(
		   								"team_name" => $objManager['team_name'],
		   								"starting_eleven" => $startingEleven,
		   								"overall" => $overall,
		   								"record" => null
		   							);
		};

		return $managers;
	}

	/*
		If $includeStartingEleven = True it returns all players of the specified manager
	*/
	public function getAllPlayers($managerID, $managerCollection, $playerCollection, $activeTeamsCollection, $includeStartingEleven = True)
	{
		$allAvailablePlayers = $this->getAvailablePlayers($managerCollection, $playerCollection, $managerID);
		$startingEleven = $this->getStartingEleven($activeTeamsCollection, $playerCollection, $managerID);

		if($includeStartingEleven)
		{
			return $allAvailablePlayers;
		}
		else
		{
			return getBenchedPlayers($allAvailablePlayers, $startingEleven);
		}
	}

	public function getDatabase()
	{
		return $this->database;
	}
}
?>