<?php
namespace App\Http\Controllers\Match;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Lang;
use Event;
use Carbon\Carbon;
use App\Player;
use App\Match;
use App\Game;
use App\GameRound;
use App\Events\UserRolled;


class MatchController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return void
     */
    public function index()
    {
        return view('templates.match.index');
    }

    /**
     * Display the current Match
     *
     * @return void
     */
    public function activeMatch(Request $request, $matchId, $playerIdentifier = false)
    {
        // Get the current Match
        $match = Match::findOrFail($matchId);
        $status = $this->validateMatch($match, $request, $playerIdentifier);
        $matchHasEnded = false;
        
        // Validate Match
        if (!empty($status['error'])) {
            if ($status['error'] == 'ended') {
                $matchHasEnded = true;
            } else if ($status['error'] == 'in_progress') {
                return redirect('match')->with('error', Lang::get('errors.match_in_progress'));
            }
        }

        $currentPlayer = $match->startingPlayer;
        $activePlayer = $match->players->filter(function($item) use($playerIdentifier) {
            return $item->unique_identifier == $playerIdentifier;
        })->first();

        // If not all players have set name we can't start
        $waitingForJoin = false;

        $match->players->each(function($player) use(&$waitingForJoin) {
            if (empty($player->name)) $waitingForJoin = true;
        });

        if (!$matchHasEnded) {
            // Games sorted 1 - 10
            $games = $match->games->sortBy('number');

            // Get current Game
            $currentGame = $games->filter(function ($game) {
                if (empty($game->start_datetime) && empty($game->end_datetime)) {  
                    // First not started Game      
                    return $game;
                } else if (!empty($game->start_datetime) && empty($game->end_datetime)) { 
                    // First active Game   
                    return $game;
                }
            })->first();

            // Get all GameRounds, for current Game
            $gameRounds = $currentGame->gameRounds;

            if (!isset($playerRounds[$match->startingPlayer->id])) $playerRounds[$match->startingPlayer->id] = array();

            $match->players->each(function($player) use (&$playerRounds, $gameRounds) {
                if (!isset($playerRounds[$player->id])) $playerRounds[$player->id] = array();
            
                $gameRounds->filter(function($gameRound) use (&$playerRounds, $player) {           
                    if ($gameRound->player_id == $player->id) {
                        $playerRounds[$player->id][$gameRound->number] = $gameRound;
                    }
                });
            });

            $currentGameRound = $this->findActiveGameRound($playerRounds); 
            $currentPlayer = $currentGameRound->player;
        }
    
        // Fix when Player two starts a remote play
        $firstPlayer = $match->players->first();
        if ($waitingForJoin && $currentPlayer->id != $firstPlayer->id) $currentPlayer = $firstPlayer;
        
        // Keep the result in a separate array to avoid calculations in the view
        $results = $this->getMatchResults($match);
        return view('templates.match.match', compact('match', 'results', 'matchHasEnded', 'currentPlayer', 'activePlayer', 'playerIdentifier', 'waitingForJoin'));
    }

   /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     *
     * @return void
     */
    public function store(Request $request)
    {
        $this->validate($request, ['player_one' => 'required|min:1|max:80', 'player_two' => 'max:80']);

        // We don't want two players with the same name in a Match
        if ($request->get('player_one') == $request->get('player_two')) return redirect('match')->with('error', Lang::get('errors.different_names'));

        // Create our players
        $playerOneValues = array('name' => trim($request->get('player_one')), 'unique_identifier' => md5(microtime() . trim($request->get('player_one'))));
        $playerOne = Player::create($playerOneValues); 

        if ($request->has('remote_play') && $request->get('remote_play') == 1) {
            $playerTwoName = "";
        } else {
            $playerTwoName = trim($request->get('player_two'));
            $this->validate($request, ['player_two' => 'required|min:1|max:80']);
        }

        $playerTwoValues = array('name' => $playerTwoName, 'unique_identifier' => md5(microtime() . $playerTwoName));
        $playerTwo = Player::create($playerTwoValues); 

        $now = Carbon::now('Europe/Stockholm');

        // To make it easy, since we validate length just set the name of the Match to the players names
        $matchName = $playerOne->name . ' - ' . $playerTwo->name;

        // Create Match
        $matchValues = array('name' => $matchName, 'unique_identifier' => md5(microtime()), 'winner_player_id' => 0, 'start_datetime' => $now->format('Y-m-d H:i:s'));
        $match = Match::create($matchValues);

        // Initialize the Match
        $this->initializeMatch($match, array($playerOne, $playerTwo));
       
        // Keep track of the Match identifier, just to prevent direct URL access for a Match in progress
        $request->session()->put('unique_identifier', $matchValues['unique_identifier']);

        // Go to the Match
        if ($request->has('remote_play') && $request->get('remote_play') == 1) {
            return redirect()->route('active_match', ['match_id' => $match->id, 'player_identifer' => $playerOne->unique_identifier]);
        } 
        return redirect()->route('active_match', ['match_id' => $match->id]);
    }

    /**
     * Update a resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     *
     * @return void
     */
    public function joinMatch(Request $request, $matchId)
    {
        // Get the current Match
        $match = Match::findOrFail($matchId);
        $now = Carbon::now('Europe/Stockholm');
        $playerIdentifier = $request->get('player_identifier');
        
        $status = $this->validateMatch($match, $request, $playerIdentifier);
                
        // Validate Match
        if (!empty($status['error'])) {
            if ($status['error'] == 'ended') {
                return redirect()->route('active_match', ['match_id' => $match->id]);
            } else if ($status['error'] == 'in_progress') {
                return redirect('match')->with('error', Lang::get('errors.match_in_progress'));
            }
        }

        $this->validate($request, ['player' => 'required|min:1|max:80']);
        $sameName = false;

        $match->players->each(function($player) use ($request, &$sameName) {
            // We don't want two players with the same name in a Match
            if ($request->get('player') == $player->name) $sameName = true;   
        });
        if ($sameName) return redirect()->route('active_match', ['match_id' => $match->id, 'player_identifier' => $playerIdentifier])->with('error', Lang::get('errors.different_names'));
    
        $player = $match->players->filter(function($player) use ($playerIdentifier) {
            // We don't want two players with the same name in a Match
            if ($player->unique_identifier == $playerIdentifier) return $player; 
        })->first();

        if (!$player) return redirect()->route('active_match', ['match_id' => $match->id, 'player_identifier' => $playerIdentifier])->with('error', Lang::get('errors.can_find_game'));
        
        $player->name = trim($request->get('player'));
        $player->save();

        $match->name .= $player->name;
        $match->save();

        Event::fire(new UserRolled($match->unique_identifier, "", "", $player->name .' '. Lang::get('match.player_joined')));
        return redirect()->route('active_match', ['match_id' => $match->id, 'player_identifier' => $playerIdentifier]);
    }

    /**
     * Update a resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     *
     * @return void
     */
    public function update(Request $request, $matchId)
    {
        // Get the current Match
        $match = Match::findOrFail($matchId);
        $now = Carbon::now('Europe/Stockholm');
        $playerIdentifier = $request->get('player_identifier');

        $status = $this->validateMatch($match, $request, $playerIdentifier);
        
        // Validate Match
        if (!empty($status['error'])) {
            if ($status['error'] == 'ended') {
                return redirect()->route('active_match', ['match_id' => $match->id]);
            } else if ($status['error'] == 'in_progress') {
                return redirect('match')->with('error', Lang::get('errors.match_in_progress'));
            }
        }

        // Games sorted 1 - 10
        $games = $match->games->sortBy('number');

        // Get current Game
        $currentGame = $games->filter(function ($game) {
            if (empty($game->start_datetime) && empty($game->end_datetime)) {  
                // First not started Game      
                return $game;
            } else if (!empty($game->start_datetime) && empty($game->end_datetime)) { 
                // First active Game   
                return $game;
            }
        })->first();

        // Get all GameRounds, for current Game
        $gameRounds = $currentGame->gameRounds;

        // Player GameRound's in array for easy access, they are sorted 1 - 2 (3) already
        $playerRounds = array();
        $gameStarted = false;
        $startingPlayerId = $match->startingPlayer->id;

        if (!isset($playerRounds[$startingPlayerId])) $playerRounds[$startingPlayerId] = array();

        $match->players->each(function($player) use (&$playerRounds, &$gameStarted, $gameRounds) {
            if (!isset($playerRounds[$player->id])) $playerRounds[$player->id] = array();
            
            $gameRounds->filter(function($gameRound) use (&$playerRounds, &$gameStarted, $player) {           
                if ($gameRound->player_id == $player->id) {
                    $playerRounds[$player->id][$gameRound->number] = $gameRound;
                }

                // Check if Game is started
                if ($gameRound->created_at != $gameRound->updated_at) $gameStarted = true;
            });
            
        });
       
        $currentGameRound = $this->findActiveGameRound($playerRounds); 

        // Try to find previous GameRound
        $previousGameRound = $this->findPreviousGameRound($playerRounds, $currentGameRound);

        // Do actions
        $currentGameRound = $this->performRollAction($currentGameRound, $previousGameRound);
        
        // Touch will ensure update even if we score 0
        $currentGameRound->touch(); 
        $currentGameRound->save();

        // Check and handle if "Strike", or last GameRound
        $this->checkAndHandleNextGameRound($playerRounds, $currentGameRound);

        // Set the Game as started
        if (empty($currentGame->start_datetime)) {
            $currentGame->start_datetime = $now->format('Y-m-d H:i:s');
            $currentGame->save();
        }

        // Check if all GameRounds are played
        $gameFinished = $this->gameIsFinished($gameRounds);
        if ($gameFinished == true) {
            $currentGame->end_datetime = $now->format('Y-m-d H:i:s');
            $currentGame->save();
        }
        
        // Check if the Match is finished
        $lastGame = $games->last();
        $matchFinished = $this->gameIsFinished($lastGame->gameRounds);
        $message = sprintf("%s %s: %s.", $currentGameRound->player->name, Lang::get('match.rolled'), $this->getRollMessage($currentGameRound));

        if ($matchFinished == true) {
            $this->finishMatch($match, $now);
            // Broadcast Match finished
            $message = Lang::get('match.match_ended');
            Event::fire(new UserRolled($match->unique_identifier, "", "", $message));
        } else {
            // Find Next Player and send out broadcast
            $nextPlayer = $this->getNextPlayer($match, $playerRounds, $currentGameRound);
            Event::fire(new UserRolled($match->unique_identifier, $nextPlayer->unique_identifier, $nextPlayer->name, $message));
        }

        // Go back to the Match
        if ($playerIdentifier) {
            return redirect()->route('active_match', ['match_id' => $match->id, 'player_identifier' => $playerIdentifier]);
        } 
        
        return redirect()->route('active_match', ['match_id' => $match->id])->with('success', $message); 
    }

    /**
     * Set up a Match
     */
    private function initializeMatch($match, $players) 
    {
        $numberOfFrames = 10;

        // Set players
        foreach($players as $player) {
            $match->assignPlayer($player);
        }

        // Since a Bowling Match has 10 Frames (Games), we will create 10 Games
        for ($x = 1; $x <= $numberOfFrames; $x++) {
            // Name and number is the same for now
            $gameValues = array('name' => $x, 'number' => $x, 'match_id' => $match->id);
            $game = Game::create($gameValues);

            // Create empty GameRounds, will make it simple to present the data.
            // Each player has two GameRounds / Frame (Game)
            $numberOfRounds = 2;

            // The last Game has 3 GameRounds
            if ($x == 10) $numberOfRounds = 3;
            
            foreach ($players as $player) {
                // Create each GameRound
                for ($i = 1; $i <= $numberOfRounds; $i++) {
                    $gameRound = GameRound::create( array('name' => $i, 'number' => $i, 'game_id' => $game->id, 'player_id' => $player->id));
                }
            }
        }

        // Shuffle a Player that starts
        $players = $match->players;
        $startingPlayer = $this->shufflePlayer($players);
        $match->setStartingPlayer($startingPlayer);
        $match->save();
        
        Event::fire(new UserRolled($match->unique_identifier, $startingPlayer->unique_identifier, $startingPlayer->name, false));
    }

    /**
     * Check if Match is valid, session and if it has ended
     */
    private function validateMatch($match, $request, $playerIdentifier)
    {
        $unique_identifier = $request->session()->get('unique_identifier', '');
        $status = array('error' => '', 'message' => '');

        $player = $match->players->filter(function($item) use($playerIdentifier) {
            return $item->unique_identifier == $playerIdentifier;
        })->first();
        
        if (!empty($match->end_datetime)) {
            // Check if Match has ended
            $status['error'] = 'ended';
            $status['message'] = Lang::get('errors.match_has_ended');
        } else if (empty($unique_identifier) || $unique_identifier != $match->unique_identifier) {
            if (!$player) {
                // Check if this Match belongs to our current Session
                $status['error'] = 'in_progress';
                $status['message'] = Lang::get('errors.match_in_progress');
            }
        } 

        return $status;
    }

    /**
     * Find next Player
     */
    private function getNextPlayer($match, $playerRounds, $currentGameRound)
    {
        // Get next GameRound for this Player
        $nextGameRound = $this->findNextGameRound($playerRounds, $currentGameRound);

        // If "Strike" is rolled, and this is not the last game, we need to fetch next Game
        if ($currentGameRound->type == 2 && $currentGameRound->game->number != 10) $nextGameRound = false;

        // In the last Game and middle frame we need to roll "Spare" or "Strike" to continue
        if ($currentGameRound->game->number == 10 && $currentGameRound->number == 2) {
            if ($currentGameRound->type != 2 && $currentGameRound->type != 1) {
                $nextGameRound = false;
            }
        }

        // If don't find next GameRound for this Game, Just return the other PLayer
        if (!$nextGameRound) {
            $nextPlayer = $match->players->filter(function($player) use ($currentGameRound) {
                if($player->id != $currentGameRound->player_id) return $player; 
            })->first();
        } else {       
            $nextPlayer = $nextGameRound->player;
        }
        return $nextPlayer;
    }

    /**
     * Shuffle a starting Player
     */
    private function shufflePlayer($players) 
    {
        // Was issue with this on Heroku
        return $players->random();
    }

    /**
     * Get active GameRound, for this Game
     */
    private function findActiveGameRound($playerRounds) 
    {
        foreach ($playerRounds as $key => $number) {
            foreach ($number as $gameRound) {
                // First not modified GameRound
                if ($gameRound->created_at == $gameRound->updated_at) return $gameRound;
            }
        }
        return false;
    }

    /**
     * Get previous GameRound, for this Game
     */
    private function findPreviousGameRound($playerRounds, $currentGameRound) 
    {   
        // Previous Game Round will be this number - 1
        $previousGameRoundNumber = ($currentGameRound->number - 1);

        // Current Player GameRounds
        $currentPlayerRounds = $playerRounds[$currentGameRound->player_id];

        // Check if there is a updated GameRound 
        if (isset($currentPlayerRounds[$previousGameRoundNumber])) {
            $previousGameRound = $currentPlayerRounds[$previousGameRoundNumber];

            if ($previousGameRound->created_at != $previousGameRound->updated_at) {
                return $previousGameRound;
            }
        }

        return false;
    }

    /**
     * Get next GameRound, for this Game
     */
    private function findNextGameRound($playerRounds, $currentGameRound) 
    {   
        // Next Game Round will be this number + 1
        $nextGameRoundNumber = ($currentGameRound->number + 1);

        // Current Player GameRounds
        $currentPlayerRounds = $playerRounds[$currentGameRound->player_id];

        // Check if there is a next GameRound 
        if (isset($currentPlayerRounds[$nextGameRoundNumber])) {
            return $currentPlayerRounds[$nextGameRoundNumber];
        }

        return false;
    }

    /**
     * Check if all GameRounds in Game have been played
     */
    private function gameIsFinished($gameRounds) 
    {
        $result = true;

        $gameRounds->filter(function ($gameRound) use (&$result) {
            if ($gameRound->created_at == $gameRound->updated_at) {
                $result = false;
            }
        });

        return $result;
    }

    /**
     * Get Player result for a Game
     */
    private function getResult($game, $player) 
    {
        $score = 0;

        $game->gameRounds->each(function($round) use ($player, &$score) {
            // The score belongs to Player
            if ($round->player_id == $player->id) {
                // The score is not default
                if ($round->created_at != $round->updated_at) {
                    $score += (is_numeric($round->score) ? $round->score : 0);
                } else {
                     // Empty space default
                    $score = "&emsp;";
                }
            }
        });

        return $score;
    }

    /**
     * Get score for a Match
     */
    private function getMatchResults($match) 
    {
        $results = array();

        $match->players->each(function($player) use ($match, &$results) {
            $totalScore = 0;

            $match->games->each(function($game) use (&$results, &$totalScore, $player) {
                if (!isset($results[$player->id])) $results[$player->id] = array();

                // Increase the score if we are not showing default symbol instead of score.
                $score = $this->getResult($game, $player);
                $totalScore += (is_numeric($score) ? $score : 0); 

                // Make sure space symbol get's shown before Match starts
                $results[$player->id][$game->number] = (is_numeric($score) ? $totalScore : $score);
            });
        });

        return $results;
    }

     /**
     * Set Match as finished
     */
    private function finishMatch($match, $now) 
    {
        $playerResults = array();

        $match->players->each(function($player) use ($match, &$playerResults) {
            $playerResults[$player->id] = 0;
            
            $match->games->each(function($game) use (&$playerResults, $player) {
                $score = $this->getResult($game, $player);
                $playerResults[$player->id] += $score;
            });
        });

        // Will be several values if tied
        $winnerIds = array_keys($playerResults, max($playerResults));

        if (count($winnerIds) == 1) {
            $winner = $match->players->filter(function($item) use($winnerIds) {
                return $item->id == array_values($winnerIds)[0];
            })->first();

            $match->setWinner($winner, $playerResults[$winner->id]);
        } else {
            // Tied, just make sure this is set.
            $match->winner_score = array_values($playerResults)[0];
        }

        // We only handle two players, set winner and final score
        $match->display_score = array_values($playerResults)[0] . "/" . array_values($playerResults)[1];
        $match->end_datetime = $now->format('Y-m-d H:i:s');
        $match->save();
    }

    /**
     * Perform a roll action
     */
    private function performRollAction(&$gameRound, &$previousGameRound = false) 
    {
        // TODO perhaps we should include violation?

        $minValue = 0;
        $maxValue = 10;

        // There is a previous round, the new max will need to be adjusted
        if ($previousGameRound) $maxValue  = ($maxValue - $previousGameRound->score);

        $gameRound->score = random_int($minValue, $maxValue);
        
        // Check if "Spare", "Strike", "Violation" or "Regular"
        $gameRound->type = $this->getRollType($gameRound, $previousGameRound);

        // Check if we received any bonuses and collect these points
        // These point will be awarded to previous Game
        $this->checkAndAwardBonus($gameRound);

        return $gameRound;
    }

    /**
     * Check and handle next GameRound, if there is one.
     */
    function checkAndHandleNextGameRound($playerRounds, $currentGameRound)
    {    
        $nextGameRound = $this->findNextGameRound($playerRounds, $currentGameRound);
        $lockNextGameRound = false;

        // Check if this is the last Game
        if ($currentGameRound->game->number == 10) {
            if ($currentGameRound->number > 1) {
                $previousGameRound = $this->findPreviousGameRound($playerRounds, $currentGameRound);
                
                // If this is not a Spare and the first GameRound was not a Strike
                // We should "lock" the last GameRound
                // Since we are checking GameRound->number > 1, previosGameRound will exist
                // A "Spare" och current GameRound or a "Strike" on previous or current GameRound would leave next GameRound open
                if ($currentGameRound->type != 1 && $currentGameRound->type != 2 && $previousGameRound->type != 2) {
                    $lockNextGameRound = true;
                }
            }
        } else {
            if ($currentGameRound->type == 2) {
                // If the type for this GameRole is "Strike"
                $lockNextGameRound = true;
            }
        }

        if ($lockNextGameRound == true) {
            // We should "lock" next GameRound, if there is one
            if ($nextGameRound) {
                $nextGameRound->type = 4;
                $nextGameRound->save();
            } 
        }
    }

    /**
     * Return message for roll
     */
    private function getRollMessage($currentGameRound)
    {
        switch ($currentGameRound->type) {
            case 0:
                return $currentGameRound->score;
            case 1:
                return Lang::get('match.spare');
            case 2:
                return Lang::get('match.strike');
            case 3:
                return Lang::get('match.violation');
        }
    }

    /**
     * Check roll type
     */
    private function getRollType($thisGameRound, $previousGameRound = false) 
    {
        if ($thisGameRound->score == 10) {
            // Make sure that last Roll not was a violation 
            if ($previousGameRound != false && $previousGameRound->score < 0) {
                // This counts as a "Spare" type = 1
                return 1;
            }
            
            // This is a "Strike", type = 2
            return 2;     
        }

        if ($thisGameRound->score < 0) {
            // This is a "Violation", type = 3
            return 3;
        }
        
        // Check if type = 2 to fix bugg in last GameRound.
        if ($previousGameRound != false && $previousGameRound->type != 2) {
            if ($thisGameRound->score + $previousGameRound->score == 10) {
                // This is a "Spare" type = 1
                return 1;
            }
        }

        // Default "Regular" roll
        return 0;
    }

    /**
     * Check if there is any bonus to award to previous Game
     */
    function checkAndAwardBonus($gameRound)  
    {
        $thisGameNumber = $gameRound->game->number;
        $allGames = $gameRound->game->match->games;

        // Find previous Game
        $previousGame = $allGames->firstWhere('number', ($thisGameNumber - 1));

        if ($previousGame) {
            $gameRoundsForPlayer = $previousGame->gameRounds->where('player_id', $gameRound->player_id);

            $gameRoundsForPlayer->each(function(&$item) use ($gameRound) {
                if ($item->type == 1) {
                    // "Spare"
                    if ($gameRound->number == 1) {
                        // Award score for this roll
                        $item->score += $gameRound->score;
                        $item->award_count += 1;
                        $item->save();
                    }
                } else if ($item->type == 2) {
                    // "Strike"
                    if ($gameRound->number <= 2) { 
                        // Award score for these roll's and keep track of times awarded
                        $item->score += $gameRound->score;
                        if ($gameRound->type != 4) $item->award_count += 1;
                        $item->save();
                    }
                }
            });

            // Will keep tack of times awarded, if you roll a "Strike", you will need to check previous, previous game (since strike is a singel roll).
            // Find previous, previous Game
            $previousPreviousGame = $allGames->firstWhere('number', ($previousGame->number - 1));

            if ($previousPreviousGame) {
                $gameRoundsForPlayer = $previousPreviousGame->gameRounds->where('player_id', $gameRound->player_id);
    
                $gameRoundsForPlayer->each(function(&$item) use ($gameRound, $previousGame) { 
                    if ($item->type == 2) {
                        // "Strike"
                        if ($gameRound->number == 1) {
                            if ($item->award_count < 2) {
                                // Award score for this roll
                                $item->score += $gameRound->score;
                                $item->award_count += 1;
                                $item->save();
                            }
                        }
                    }
                });
            }
        }
    }
}
