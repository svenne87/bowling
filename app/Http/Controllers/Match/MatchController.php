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
            $match->games->each(function($game) use (&$results, $player) {
                if (!isset($results[$player->id])) $results[$player->id] = array();
                $results[$player->id][$game->number] = $this->calculateResult($game, $player);
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

            // Find active round, pass in array since function expects array in array
            $activeRounds = $playerRounds[$currentPlayerId];
            $currentGameRound = $this->findActiveGameRound(array($activeRounds));
        } else {
            // Find active round
            $currentGameRound = $this->findActiveGameRound($playerRounds);
        }

        // Try to find previous GameRound
        $previousGameRound = $this->findPreviousGameRound($playerRounds, $currentGameRound);
   
        $currentGameRound = $this->performRollAction($currentGameRound, $previousGameRound);
        $currentGameRound->touch(); // touch will ensure update even if we score 0
        $currentGameRound->save();

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

        // TODO Present more details...
        // TODO calculate results and handle spare, strike etc.
        // TODO Set correct type
        // TODO Skip the last "Roll" to see "Match has ended"...
        // checkRoleType($thisGameRound, $previousGameRound = false) 

        $message = sprintf("%s %s: %d.", $currentGameRound->player->name, Lang::get('match.rolled'), $currentGameRound->score);

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
        $status = array('error' => '', 'message' => '');
        // Check if Match has ended
        if (!empty($match->end_datetime)) {
            $status['error'] = 'ended';
            $status['message'] = Lang::get('errors.match_has_ended');
        }
        
        $unique_identifier = $request->session()->get('unique_identifier', '');

        // Check if this Match belongs to our current Session
        if (empty($unique_identifier) || $unique_identifier != $match->unique_identifier) {
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
     * Get active GameRound
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
     * Get previous GameRound
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
     * Calculate Player result for a Game
     */
    private function calculateResult($game, $player) 
    {
        $score = 0;

        $game->gameRounds->each(function($round) use ($player, &$score) {
            // The score belongs to Player
            if ($round->player_id == $player->id) {
                // The score is not default
                if ($round->created_at != $round->updated_at) {
                    // TODO handle strike, spare, violation

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
    private function performRollAction(&$gameRound, $previousGameRound = false) 
    {
        // TODO perhaps we should include violation?
        $minValue = 0;
        $maxValue = 10;

        // There is a previous round, the new max will need to be adjusted
        if ($previousGameRound) $maxValue  = ($maxValue - $previousGameRound->score);

        $gameRound->score = random_int($minValue, $maxValue);
        return $gameRound;
    }

    /**
     * Check roll type
     */
    private function checkRoleType($thisGameRound, $previousGameRound = false) 
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
   
    // TODO use these rules...

    /**
     * Check if there is a bonus for roll type
     */
    private function checkIfBonus($roleType, $nextRoll, $nextNextRoll, $lastGame = false) 
    {
        $bonus = array('points' => 0, 'rolls' => 0);

        switch($roleType) {
            case 0:
                // No bonus
                break;
            case 1:
                // Spare gives a bonus of the points in next roll
                $bonus['points'] = $nextRoll->score;

                // Extra roll for the last Game
                if ($lastGame == true) $bonus['rolls'] = 1; 
                break;
            case 2:
                 // Strike gives a bonus of the points in next roll and next next roll
                 $bonus['points'] = $nextRoll->score + $nextNextRoll->score;

                // Extra roll for the last Game
                if ($lastGame == true) $bonus['rolls'] = 1; 
                break;
            case 3:
                // No bonus
                break;
        }

        return $bonus;
    }

}