<?php
namespace App\Http\Controllers\Match;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Lang;
use Carbon\Carbon;
use App\Player;
use App\Match;
use App\Game;
use App\GameRound;

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
    public function activeMatch(Request $request, $matchId)
    {
        // Get the current Match
        $match = Match::findOrFail($matchId);
        $status = $this->validateMatch($match, $request);
        $matchHasEnded = false;
        
        // Validate Match
        if (!empty($status['error'])) {
            if ($status['error'] == 'ended') {
                $matchHasEnded = true;
            } else if ($status['error'] == 'in_progress') {
                return redirect('match')->with('error', Lang::get('errors.match_in_progress'));
            }
        }

        // Keep the result in a separate array to avoid calculations in the view
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

        return view('templates.match.match', compact('match', 'results', 'matchHasEnded'));
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
        $this->validate($request, ['player_one' => 'required||min:1|max:80', 'player_two' => 'required||min:1|max:80']);

        // We don't want two players with the same name in a Match
        if ($request->get('player_one') == $request->get('player_two')) return redirect('match')->with('error', Lang::get('errors.different_names'));

        // Create our players
        $playerOneValues = array('name' => trim($request->get('player_one')), 'unique_identifier' => md5(microtime() . trim($request->get('player_one'))));
        $playerOne = Player::create($playerOneValues); 

        $playerTwoValues = array('name' => trim($request->get('player_two')), 'unique_identifier' => md5(microtime() . trim($request->get('player_two'))));
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
        return redirect()->route('active_match', ['match_id' => $match->id]);
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
        $status = $this->validateMatch($match, $request);
        
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

        if (!$currentGame) {
            // The Match is finished
            $match->end_datetime = $now->format('Y-m-d H:i:s');
            $match->save();
            return redirect()->route('active_match', ['match_id' => $match->id]);
        }

        // Get all GameRounds, for current Game
        $gameRounds = $currentGame->gameRounds;

        // Player GameRound's in array for easy access, they are sorted 1 - 2 (3) already
        $playerRounds = array();
        $gameStarted = false;

        $match->players->each(function($player) use (&$playerRounds, &$gameStarted , $gameRounds) {
            if (!isset($playerRounds[$player->id])) $playerRounds[$player->id] = array();
            
            $gameRounds->filter(function ($gameRound) use (&$playerRounds, &$gameStarted , $player) {           
                if ($gameRound->player_id == $player->id) {
                    $playerRounds[$player->id][$gameRound->number] = $gameRound;
                }

                // Check if Game is started
                if ($gameRound->created_at != $gameRound->updated_at) $gameStarted = true;
            });
        });
       
        if (!$gameStarted && $currentGame->number == 1) {
            // This is the first GameRound of the first Game, shuffle a Player that starts
            $currentPlayerId = $this->shufflePlayer($playerRounds); 

            // Find active GameRound, pass in array since function expects array in array
            $activeRounds = $playerRounds[$currentPlayerId];
           
            // TODO issue when player two starts...

            $currentGameRound = $this->findActiveGameRound(array($activeRounds));
        } else {
            // Find active round
            $currentGameRound = $this->findActiveGameRound($playerRounds);
        }

        // Try to find previous GameRound
        $previousGameRound = $this->findPreviousGameRound($playerRounds, $currentGameRound);
   
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

        // TODO Will need to refetch gameRounds here?? If strike at end?
        // TODO Present more details...
        // TODO Skip the last "Roll" to see "Match has ended"...  !$currentGame, gör en annan koll innan vi retunerar här nere.
        // TODO set Match winner
        // TODO will cover if all is Strike?, missinh 70 points? Issiue wit hseveral strikes in a row...
        // TODO test if all is Spare
        // TODO could be done more elegant...
        // Test if last roll should be locked, is i
        // Bugg om spelar nere börjar??

        $message = sprintf("%s %s: %s.", $currentGameRound->player->name, Lang::get('match.rolled'), $this->getRollMessage($currentGameRound));

        // Go back to the Match
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
    }

    /**
     * Check if Match is valid, session and if it has ended
     */
    private function validateMatch($match, $request)
    {
        $unique_identifier = $request->session()->get('unique_identifier', '');
        $status = array('error' => '', 'message' => '');
        
        if (!empty($match->end_datetime)) {
            // Check if Match has ended
            $status['error'] = 'ended';
            $status['message'] = Lang::get('errors.match_has_ended');
        } else if (empty($unique_identifier) || $unique_identifier != $match->unique_identifier) {
            // Check if this Match belongs to our current Session
            $status['error'] = 'in_progress';
            $status['message'] = Lang::get('errors.match_in_progress');
        }

        return $status;
    }

    /**
     * Shuffle a starting Player
     */
    private function shufflePlayer($playerRounds) 
    {
        $minPlayersKey = min(array_keys($playerRounds));
        $maxPlayersKey =  max(array_keys($playerRounds));
        return random_int($minPlayersKey, $maxPlayersKey);
    }

    /**
     * Get active GameRound, for this Game
     */
    private function findActiveGameRound($playerRounds) 
    {
        foreach($playerRounds as $key => $number) {
            foreach($number as $gameRound) {
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
        $gameRounds->filter(function ($gameRound) use (&$result) {
            if ($gameRound->created_at == $gameRound->updated_at) {
                $result = false;
                return;
            } else {
                $result = true;
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
                    $score += $round->score;
                } else {
                     // Empty space default
                    $score = "&emsp;";
                }
            }
        });

        return $score;
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

        // TODO TEST
         $gameRound->score = $this->testIt($gameRound, 1);
        // TODO END TEST

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
        
        if ($previousGameRound != false) {
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
        $previousWasStrike = false;

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
                        $item->save();
                    }
                } else if ($item->type == 2) {
                    // "Strike"
                    $previousWasStrike = true;
                    if ($gameRound->number <= 2) { 
                        // Award score for these roll's
                        $item->score += $gameRound->score;
                        $item->save();
                    }
                }
            });
        }

        // TODO clean up, will also need to chewck if strik. not working for test 2) 
        if ($previousGame) {
            // Find previous, previous Game
            $previousPreviousGame = $allGames->firstWhere('number', ($previousGame->number - 2));

            if ($previousPreviousGame) {
                $gameRoundsForPlayer = $previousPreviousGame->gameRounds->where('player_id', $gameRound->player_id);
    
                $gameRoundsForPlayer->each(function(&$item) use ($gameRound, $previousGame) {
                    if ($item->type == 2) {
                        // "Strike"
                        if ($gameRound->number <= 2) {  // 1  
                            // Award score for these roll's
                            $item->score += $gameRound->score;
                            $item->save();
                        }
                    }
                });
            }
        }
    }

    private function testIt(&$gameRound, $test) 
    {
        if ($test == 1) {
            switch ($gameRound->game->number) {
                case 1:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 7;
                    } else {
                        $gameRound->score = 2;
                    }    
                    break;         
                case 2:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 9;
                    } else {
                        $gameRound->score = 1;
                    } 
                    break;
                case 3:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 10;
                    }
                    break;
                case 4:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 9;
                    } else {
                        $gameRound->score = 0;
                    } 
                    break;
                case 5:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 7;
                    } else {
                        $gameRound->score = 3;
                    }
                    break;
                case 6:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 7;
                    } else {
                        $gameRound->score = 2;
                    }
                    break;
                case 7:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 8;
                    } else {
                        $gameRound->score = 2; 
                    }
                    break;
                case 8:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 10;
                    }
                    break;
                case 9:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 10;
                    }
                    break;
                case 10:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 6;
                    } else if ($gameRound->number == 2) {
                        $gameRound->score = 4;
                    } else {
                        $gameRound->score = 7;
                    }
                    break;
            }
             // 9 29 48 57 74 83 103 129 149 166
        } else if ($test == 2) {
            switch ($gameRound->game->number) {
                case 1:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 6;
                    } else {
                        $gameRound->score = 2;
                    }    
                    break;         
                case 2:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 8;
                    } else {
                        $gameRound->score = 2;
                    } 
                    break;
                case 3:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 10;
                    }
                    break;
                case 4:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 9;
                    } else {
                        $gameRound->score = 0;
                    } 
                    break;
                case 5:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 6;
                    } else {
                        $gameRound->score = 4;
                    }
                    break;
                case 6:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 8;
                    } else {
                        $gameRound->score = 1;
                    }
                    break;
                case 7:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 9;
                    } else {
                        $gameRound->score = 1;
                    }
                    break;
                case 8:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 10;
                    }
                    break;
                case 9:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 10;
                    }
                    break;
                case 10:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 8;
                    } else if ($gameRound->number == 2) {
                        $gameRound->score = 2;
                    } else {
                        $gameRound->score = 7;
                    }
                    break;
            }
            // 8 28 47 56 74 83 103 131 151 168
        } else if ($test == 3) {
            switch ($gameRound->game->number) {
                case 1:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 10;
                    }   
                    break;         
                case 2:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 10;
                    } 
                    break;
                case 3:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 10;
                    }
                    break;
                case 4:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 10;
                    } 
                    break;
                case 5:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 10;
                    } 
                    break;
                case 6:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 10;
                    }
                    break;
                case 7:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 10;
                    }
                    break;
                case 8:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 10;
                    }
                    break;
                case 9:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 10;
                    }
                    break;
                case 10:
                    if ($gameRound->number == 1) {
                        $gameRound->score = 10;
                    } else if ($gameRound->number == 2) {
                        $gameRound->score = 10;
                    } else if ($gameRound->number == 3) {
                        $gameRound->score = 10;
                    }
                    break;

                    // Maxpoängen man kan få på en serie är 300 p. och det är 12 st strikar på rad.
            }
        }
        return $gameRound->score;
    }

}